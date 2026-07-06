<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use AvatarTok\Core\Database;
use Ramsey\Uuid\Uuid;

class LiveStreamService
{
    public function hasActiveStream(string $userId): bool
    {
        return (bool) Database::fetchOne(
            "SELECT 1 FROM live_streams WHERE user_id = ? AND status = 'live'",
            [$userId]
        );
    }

    public function startStream(string $userId, array $data): object
    {
        $id       = Uuid::uuid4()->toString();
        $rtmpKey  = bin2hex(random_bytes(20));
        $wsToken  = $this->issueWsToken($userId, $id);

        $rtmpUrl = "rtmp://live.avatartok.com/stream/{$rtmpKey}";
        $hlsUrl  = "{$_ENV['AWS_CLOUDFRONT_URL']}/live/{$id}/index.m3u8";
        $wsUrl   = "{$_ENV['WS_HOST']}/live/{$id}";

        Database::insert('live_streams', [
            'id'            => $id,
            'user_id'       => $userId,
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'category'      => $data['category'],
            'rtmp_key'      => $rtmpKey,
            'hls_url'       => $hlsUrl,
            'status'        => 'live',
            'allow_gifts'   => ($data['allow_gifts'] ?? true) ? 1 : 0,
            'min_gift_coins'=> (int) ($data['minimum_gift_coins'] ?? 0),
            'started_at'    => date('Y-m-d H:i:s'),
        ]);

        $stream = Database::fetchOne("SELECT * FROM live_streams WHERE id = ?", [$id]);
        $stream->rtmp_url   = $rtmpUrl;
        $stream->stream_key = $rtmpKey;
        $stream->ws_url     = $wsUrl;
        $stream->ws_token   = $wsToken;

        return $stream;
    }

    public function endStream(string $streamId): array
    {
        $stream = Database::fetchOne("SELECT * FROM live_streams WHERE id = ?", [$streamId]);

        $duration = (int) (time() - strtotime($stream->started_at));

        Database::update('live_streams', [
            'status'   => 'ended',
            'ended_at' => date('Y-m-d H:i:s'),
        ], ['id' => $streamId]);

        // Trigger replay generation job
        // Queue::push('generate_replay', ['stream_id' => $streamId]);

        $gifts = Database::fetchOne(
            "SELECT COUNT(*) AS cnt, SUM(creator_cut) AS coins
             FROM gift_transactions WHERE stream_id = ?",
            [$streamId]
        );

        return [
            'duration'       => $duration,
            'peak_viewers'   => (int) $stream->peak_viewers,
            'total_viewers'  => (int) $stream->total_viewers,
            'gifts_received' => (int) $gifts->cnt,
            'coins_earned'   => (int) $gifts->coins,
            'replay_url'     => null, // Populated once replay is processed
        ];
    }

    public function findById(string $streamId): ?object
    {
        $stream = Database::fetchOne(
            "SELECT ls.*, u.username, av.avatar_url
             FROM live_streams ls
             JOIN users u ON u.id = ls.user_id
             LEFT JOIN avatars av ON av.user_id = u.id
             WHERE ls.id = ?",
            [$streamId]
        );

        return $stream ?: null;
    }

    public function findOwned(string $streamId, string $userId): ?object
    {
        return Database::fetchOne(
            "SELECT * FROM live_streams WHERE id = ? AND user_id = ?",
            [$streamId, $userId]
        );
    }

    public function listActive(?string $category, int $page, int $limit): array
    {
        $where  = "WHERE ls.status = 'live'";
        $params = [];

        if ($category) {
            $where   .= " AND ls.category = ?";
            $params[] = $category;
        }

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM live_streams ls {$where}",
            $params
        )->c;

        $streams = Database::fetchAll(
            "SELECT ls.*, u.username, av.avatar_url
             FROM live_streams ls
             JOIN users u ON u.id = ls.user_id
             LEFT JOIN avatars av ON av.user_id = u.id
             {$where} ORDER BY ls.viewer_count DESC LIMIT ? OFFSET ?",
            [...$params, $limit, ($page - 1) * $limit]
        );

        return ['streams' => $streams, 'total' => $total];
    }

    public function joinStream(string $streamId, string $userId): array
    {
        $stream = $this->findById($streamId);

        if (!$stream || $stream->status !== 'live') {
            return ['success' => false, 'message' => 'Stream is not live.'];
        }

        Database::query(
            "UPDATE live_streams SET viewer_count = viewer_count + 1,
             total_viewers = total_viewers + 1,
             peak_viewers = GREATEST(peak_viewers, viewer_count)
             WHERE id = ?",
            [$streamId]
        );

        $token = $this->issueViewerToken($streamId, $userId);
        $count = (int) Database::fetchOne("SELECT viewer_count FROM live_streams WHERE id = ?", [$streamId])->viewer_count;

        // Publish viewer join event for WebSocket broadcast
        (new CacheService())->publish("stream:{$streamId}:events", [
            'type'    => 'viewer_join',
            'user_id' => $userId,
            'count'   => $count,
        ]);

        return [
            'success'      => true,
            'token'        => $token,
            'ws_url'       => "{$_ENV['WS_HOST']}/live/{$streamId}",
            'viewer_count' => $count,
        ];
    }

    public function leaveStream(string $streamId, string $userId): void
    {
        Database::query(
            "UPDATE live_streams SET viewer_count = GREATEST(viewer_count - 1, 0) WHERE id = ?",
            [$streamId]
        );

        (new CacheService())->publish("stream:{$streamId}:events", [
            'type'    => 'viewer_leave',
            'user_id' => $userId,
        ]);
    }

    public function getViewers(string $streamId): array
    {
        $cache = new CacheService();
        $key   = "stream:{$streamId}:viewers";

        $cached = $cache->get($key);
        if ($cached) {
            return json_decode($cached, true);
        }

        return [];
    }

    public function issueViewerToken(string $streamId, string $userId): string
    {
        $payload = json_encode(['user_id' => $userId, 'stream_id' => $streamId, 'exp' => time() + 14400]);
        return base64_encode(hash_hmac('sha256', $payload, $_ENV['WS_SECRET']) . '.' . $payload);
    }

    public function inviteCoHost(string $streamId, string $hostId, string $guestId): array
    {
        Database::query(
            "INSERT INTO live_co_hosts (stream_id, user_id, status) VALUES (?, ?, 'invited')
             ON DUPLICATE KEY UPDATE status = 'invited'",
            [$streamId, $guestId]
        );

        (new CacheService())->publish("user:{$guestId}:events", [
            'type'      => 'co_host_invite',
            'stream_id' => $streamId,
            'host_id'   => $hostId,
        ]);

        return ['invited_user_id' => $guestId];
    }

    public function scheduleStream(string $userId, array $data): object
    {
        $id = Uuid::uuid4()->toString();

        Database::insert('live_streams', [
            'id'           => $id,
            'user_id'      => $userId,
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'status'       => 'scheduled',
            'scheduled_at' => $data['scheduled_at'],
            'started_at'   => $data['scheduled_at'],
        ]);

        return Database::fetchOne("SELECT * FROM live_streams WHERE id = ?", [$id]);
    }

    public function getScheduled(string $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM live_streams WHERE user_id = ? AND status = 'scheduled' AND scheduled_at > NOW()
             ORDER BY scheduled_at ASC",
            [$userId]
        );
    }

    public function getReplay(string $streamId, ?string $userId): ?object
    {
        return Database::fetchOne(
            "SELECT id, title, hls_url, replay_key, started_at, ended_at, peak_viewers
             FROM live_streams WHERE id = ? AND replay_key IS NOT NULL",
            [$streamId]
        );
    }

    private function issueWsToken(string $userId, string $streamId): string
    {
        $payload = json_encode(['user_id' => $userId, 'stream_id' => $streamId, 'role' => 'host', 'exp' => time() + 14400]);
        return base64_encode(hash_hmac('sha256', $payload, $_ENV['WS_SECRET']) . '.' . $payload);
    }
}
