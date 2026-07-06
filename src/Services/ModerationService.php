<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use AvatarTok\Core\Database;
use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;

class ModerationService
{
    private Client $http;
    private float  $threshold;

    public function __construct()
    {
        $this->threshold = (float) ($_ENV['MODERATION_THRESHOLD'] ?? 0.85);
        $this->http = new Client([
            'base_uri' => rtrim($_ENV['MODERATION_SERVICE_URL'], '/'),
            'timeout'  => 3.0,
            'headers'  => ['X-Service-Key' => $_ENV['MODERATION_SERVICE_KEY']],
        ]);
    }

    /**
     * Synchronous text screen for request-time blocking.
     * Uses a fast local banned-word list + lightweight regex patterns.
     * Heavy ML analysis runs asynchronously via queueVideoReview().
     */
    public function quickScreen(string $text): array
    {
        $text = mb_strtolower($text);

        $bannedWords = $this->getCachedBannedWords();

        foreach ($bannedWords as $word) {
            if (str_contains($text, $word->word)) {
                return ['blocked' => true, 'reason' => "Prohibited content: {$word->category}"];
            }
        }

        // Basic pattern checks
        $patterns = [
            '/\b(buy|sell)\s+(drugs?|weapons?|guns?)\b/i' => 'illegal goods',
            '/\b(csam|child.{0,10}abuse)\b/i'              => 'CSAM',
        ];

        foreach ($patterns as $pattern => $reason) {
            if (preg_match($pattern, $text)) {
                return ['blocked' => true, 'reason' => $reason];
            }
        }

        return ['blocked' => false];
    }

    /**
     * Enqueues a video for AI-powered moderation (visual + audio + text analysis).
     * Runs asynchronously; result is stored and may trigger automated actions.
     */
    public function queueVideoReview(string $videoId): void
    {
        Database::insert('moderation_queue', [
            'id'         => Uuid::uuid4()->toString(),
            'content_id' => $videoId,
            'type'       => 'video',
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // In production, publish to a queue (Redis/SQS) for the worker to pick up
        // Queue::push('video_moderation', ['video_id' => $videoId]);
    }

    public function reviewReport(string $reportId, string $reviewerId, string $action, ?string $notes): array
    {
        $report = Database::fetchOne("SELECT * FROM reports WHERE id = ?", [$reportId]);

        if (!$report) {
            throw new \RuntimeException('Report not found.');
        }

        Database::update('reports', [
            'status'      => 'reviewed',
            'action'      => $action,
            'reviewer_id' => $reviewerId,
            'notes'       => $notes,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ], ['id' => $reportId]);

        match($action) {
            'remove'  => $this->banContent($report->content_id, $report->content_type, 'Reported content', $reviewerId),
            'warn'    => $this->warnUser($report->reported_user_id, 'Reported content', null),
            'ban'     => $this->permanentBan($report->reported_user_id, 'Multiple violations', $reviewerId),
            default   => null,
        };

        return ['report_id' => $reportId, 'action' => $action];
    }

    public function banContent(string $contentId, string $type, string $reason, string $adminId): void
    {
        $table = match($type) {
            'video'   => 'videos',
            'comment' => 'comments',
            'sound'   => 'sounds',
            default   => throw new \InvalidArgumentException("Unknown content type: {$type}"),
        };

        Database::update($table, ['status' => 'removed', 'removed_at' => date('Y-m-d H:i:s')], ['id' => $contentId]);

        Database::insert('moderation_actions', [
            'id'         => Uuid::uuid4()->toString(),
            'admin_id'   => $adminId,
            'target_id'  => $contentId,
            'target_type'=> $type,
            'action'     => 'ban_content',
            'reason'     => $reason,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function restoreContent(string $contentId, string $type, string $adminId): void
    {
        $table = match($type) {
            'video'   => 'videos',
            'comment' => 'comments',
            'sound'   => 'sounds',
            default   => throw new \InvalidArgumentException("Unknown type: {$type}"),
        };

        Database::update($table, ['status' => 'public', 'removed_at' => null], ['id' => $contentId]);
    }

    public function warnUser(string $userId, string $reason, ?string $message): void
    {
        Database::insert('user_warnings', [
            'id'         => Uuid::uuid4()->toString(),
            'user_id'    => $userId,
            'reason'     => $reason,
            'message'    => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Reduce trust score
        Database::query(
            "UPDATE users SET trust_score = GREATEST(trust_score - 10, 0) WHERE id = ?",
            [$userId]
        );
    }

    public function suspendUser(string $userId, string $reason, \DateTime $until): void
    {
        Database::update('users', [
            'status'       => 'suspended',
            'suspended_until' => $until->format('Y-m-d H:i:s'),
        ], ['id' => $userId]);
    }

    public function permanentBan(string $userId, string $reason, string $adminId): void
    {
        Database::update('users', ['status' => 'banned'], ['id' => $userId]);

        Database::insert('moderation_actions', [
            'id'         => Uuid::uuid4()->toString(),
            'admin_id'   => $adminId,
            'target_id'  => $userId,
            'target_type'=> 'user',
            'action'     => 'ban',
            'reason'     => $reason,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function unban(string $userId, string $reason, string $adminId): void
    {
        Database::update('users', [
            'status'          => 'active',
            'suspended_until' => null,
        ], ['id' => $userId]);
    }

    public function listReports(string $status, ?string $type, int $page, int $limit): array
    {
        $where  = "WHERE r.status = ?";
        $params = [$status];

        if ($type) {
            $where   .= " AND r.content_type = ?";
            $params[] = $type;
        }

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM reports r {$where}",
            $params
        )->c;

        $reports = Database::fetchAll(
            "SELECT r.*, u.username AS reporter_username
             FROM reports r JOIN users u ON u.id = r.reporter_id
             {$where} ORDER BY r.created_at DESC LIMIT ? OFFSET ?",
            [...$params, $limit, ($page - 1) * $limit]
        );

        return ['reports' => $reports, 'total' => $total];
    }

    public function getReport(string $reportId): mixed
    {
        return Database::fetchOne("SELECT * FROM reports WHERE id = ?", [$reportId]);
    }

    public function getQueue(string $type, int $limit): array
    {
        $where  = $type !== 'all' ? "AND type = ?" : "";
        $params = $type !== 'all' ? [$type] : [];

        return Database::fetchAll(
            "SELECT * FROM moderation_queue WHERE status = 'pending' {$where} ORDER BY created_at ASC LIMIT {$limit}",
            $params
        );
    }

    public function getBannedWords(?string $lang): array
    {
        if ($lang) {
            return Database::fetchAll("SELECT * FROM banned_words WHERE language = ?", [$lang]);
        }
        return Database::fetchAll("SELECT * FROM banned_words ORDER BY language, word");
    }

    public function addBannedWord(array $data): object
    {
        $id = Uuid::uuid4()->toString();
        Database::insert('banned_words', [
            'id'       => $id,
            'word'     => mb_strtolower($data['word']),
            'language' => $data['language'],
            'severity' => $data['severity'],
            'category' => $data['category'] ?? 'general',
        ]);
        (new CacheService())->delete('banned_words');

        return Database::fetchOne("SELECT * FROM banned_words WHERE id = ?", [$id]);
    }

    public function removeBannedWord(string $wordId): void
    {
        Database::query("DELETE FROM banned_words WHERE id = ?", [$wordId]);
        (new CacheService())->delete('banned_words');
    }

    public function getAiFlagged(float $threshold, ?string $type, int $page, int $limit): array
    {
        $where  = "WHERE mq.ai_score >= ?";
        $params = [$threshold];

        if ($type) {
            $where   .= " AND mq.type = ?";
            $params[] = $type;
        }

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM moderation_queue mq {$where} AND mq.status = 'ai_flagged'",
            $params
        )->c;

        $items = Database::fetchAll(
            "SELECT mq.* FROM moderation_queue mq
             {$where} AND mq.status = 'ai_flagged'
             ORDER BY mq.ai_score DESC LIMIT ? OFFSET ?",
            [...$params, $limit, ($page - 1) * $limit]
        );

        return ['items' => $items, 'total' => $total];
    }

    private function getCachedBannedWords(): array
    {
        $cache = new CacheService();
        $key   = 'banned_words';
        $words = $cache->get($key);

        if (!$words) {
            $words = Database::fetchAll("SELECT word, category FROM banned_words");
            $cache->set($key, serialize($words), 3600);
        } else {
            $words = unserialize($words);
        }

        return $words;
    }
}
