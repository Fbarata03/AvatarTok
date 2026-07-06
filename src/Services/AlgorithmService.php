<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use AvatarTok\Core\Database;
use Ramsey\Uuid\Uuid;

/**
 * Personalized feed algorithm ("For You Page").
 *
 * Scoring model (weighted sum):
 *   score = w1*completion + w2*like_prob + w3*comment_prob + w4*share_prob
 *           + w5*follow_prob + w6*not_interested_penalty + w7*diversity_bonus
 *
 * Weights are A/B-tested and updated via the admin config panel.
 * The heavy ML scoring runs in a Python microservice; this service
 * orchestrates the call, applies business rules, and assembles the page.
 */
class AlgorithmService
{
    private CacheService $cache;

    // Default feed weights (can be overridden via platform config)
    private array $weights = [
        'completion_rate'   => 0.35,
        'like_probability'  => 0.20,
        'comment_probability' => 0.10,
        'share_probability' => 0.10,
        'follow_probability'=> 0.05,
        'recency'           => 0.10,
        'diversity_bonus'   => 0.10,
    ];

    public function __construct()
    {
        $this->cache = new CacheService();
    }

    public function buildForYouFeed(
        string $userId,
        ?string $cursor,
        int $limit,
        array $context
    ): array {
        $feedId       = $cursor ? $this->decodeCursor($cursor)['feed_id'] : Uuid::uuid4()->toString();
        $offset       = $cursor ? $this->decodeCursor($cursor)['offset']  : 0;
        $cacheKey     = "feed:foryou:{$userId}:{$feedId}";

        // Feed pool is cached for 5 minutes to enable fast cursor pagination
        $pool = $this->cache->get($cacheKey);

        if (!$pool) {
            $pool = $this->buildCandidatePool($userId, $context);
            $this->cache->set($cacheKey, serialize($pool), 300);
        } else {
            $pool = unserialize($pool);
        }

        $slice = array_slice($pool, $offset, $limit);

        $nextCursor = ($offset + $limit) < count($pool)
            ? $this->encodeCursor($feedId, $offset + $limit)
            : null;

        return [
            'videos'      => $slice,
            'next_cursor' => $nextCursor,
            'feed_id'     => $feedId,
        ];
    }

    private function buildCandidatePool(string $userId, array $context): array
    {
        $userProfile   = $this->getUserSignals($userId);
        $blockedUsers  = $this->getBlockedUserIds($userId);
        $seenVideoIds  = $this->getRecentlySeenIds($userId, hours: 48);

        // 1. Candidate retrieval: multiple sources
        $candidates = array_merge(
            $this->toArray($this->fetchInterestBasedCandidates($userProfile, 200)),
            $this->toArray($this->fetchSocialGraphCandidates($userId, 100)),
            $this->toArray($this->fetchTrendingCandidates($context['timezone'], 50)),
            $this->toArray($this->fetchDiversityCandidates($userProfile, 30)), // Burst filter bubbles
        );

        // 2. Deduplication and filtering
        $seen = [];
        $candidates = array_values(array_filter($candidates, function ($v) use (&$seen, $blockedUsers, $seenVideoIds) {
            if (isset($seen[$v['id']])) {
                return false;
            }
            if (in_array($v['author_id'], $blockedUsers, true)) {
                return false;
            }
            if (in_array($v['id'], $seenVideoIds, true)) {
                return false;
            }
            $seen[$v['id']] = true;
            return true;
        }));

        // 3. Scoring
        foreach ($candidates as &$video) {
            $video['_score'] = $this->scoreVideo($video, $userProfile);
        }

        // 4. Sort by score descending
        usort($candidates, fn($a, $b) => $b['_score'] <=> $a['_score']);

        // 5. Interleave ads (every 5 organic videos, insert 1 ad slot)
        $candidates = $this->interleaveAds($candidates, $userId);

        return $candidates;
    }

    private function scoreVideo(array $video, array $profile): float
    {
        $w = $this->weights;

        // Historical completion rate on similar content
        $completion = $this->predictCompletionRate($video, $profile);

        // Engagement probabilities from collaborative filtering
        $likeProb    = $this->predictEngagement($video, $profile, 'like');
        $commentProb = $this->predictEngagement($video, $profile, 'comment');
        $shareProb   = $this->predictEngagement($video, $profile, 'share');
        $followProb  = $this->predictEngagement($video, $profile, 'follow');

        // Recency decay: ln(1 + hours) decays slowly
        $ageHours    = (time() - strtotime($video['created_at'])) / 3600;
        $recency     = 1.0 / (1.0 + log(1 + $ageHours) * 0.1);

        // Diversity bonus for content from underrepresented categories
        $diversity   = $this->diversityBonus($video, $profile);

        return (
            $w['completion_rate']    * $completion +
            $w['like_probability']   * $likeProb   +
            $w['comment_probability']* $commentProb +
            $w['share_probability']  * $shareProb  +
            $w['follow_probability'] * $followProb  +
            $w['recency']            * $recency     +
            $w['diversity_bonus']    * $diversity
        );
    }

    public function buildFollowingFeed(string $userId, ?string $cursor, int $limit): array
    {
        $offset    = $cursor ? (int) base64_decode($cursor) : 0;
        $following = $this->getFollowingIds($userId);

        if (empty($following)) {
            return ['videos' => [], 'next_cursor' => null];
        }

        $placeholders = implode(',', array_fill(0, count($following), '?'));

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM videos WHERE author_id IN ({$placeholders}) AND status = 'public'",
            $following
        )->c;

        $videos = Database::fetchAll(
            "SELECT v.*, u.username, u.display_name, av.avatar_url
             FROM videos v
             JOIN users u  ON u.id = v.author_id
             JOIN avatars av ON av.user_id = u.id
             WHERE v.author_id IN ({$placeholders}) AND v.status = 'public'
             ORDER BY v.created_at DESC
             LIMIT ? OFFSET ?",
            [...$following, $limit, $offset]
        );

        $nextCursor = ($offset + $limit) < $total
            ? base64_encode((string)($offset + $limit))
            : null;

        return ['videos' => $videos, 'next_cursor' => $nextCursor];
    }

    public function getTrending(string $country, ?string $category, int $page, int $limit): array
    {
        $cacheKey = "trending:{$country}:{$category}:{$page}";
        $cached   = $this->cache->get($cacheKey);

        if ($cached) {
            return unserialize($cached);
        }

        $where  = $country === 'global' ? '' : "AND v.country = :country";
        $catWhere = $category ? "AND v.category = :category" : "";

        // Trending score: (views + 2*likes + 4*shares + 8*comments) / age_hours^1.8
        $sql = "SELECT v.*, u.username,
                    (v.view_count + 2*v.like_count + 4*v.share_count + 8*v.comment_count)
                    / POW(GREATEST(TIMESTAMPDIFF(HOUR, v.created_at, NOW()), 1), 1.8) AS trend_score
                FROM videos v
                JOIN users u ON u.id = v.author_id
                WHERE v.status = 'public'
                  AND v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  {$where} {$catWhere}
                ORDER BY trend_score DESC
                LIMIT :limit OFFSET :offset";

        $params = ['limit' => $limit, 'offset' => ($page - 1) * $limit];
        if ($country !== 'global') $params['country']  = $country;
        if ($category)             $params['category'] = $category;

        $videos = Database::fetchAll($sql, $params);
        $total  = 1000; // Approximated for trending; exact count is expensive

        $result = ['videos' => $videos, 'total' => $total];
        $this->cache->set($cacheKey, serialize($result), 120);

        return $result;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function getUserSignals(string $userId): array
    {
        $key = "user_signals:{$userId}";
        $cached = $this->cache->get($key);
        if ($cached) return unserialize($cached);

        // Aggregate recent interaction history
        $signals = Database::fetchOne(
            "SELECT
                GROUP_CONCAT(DISTINCT vi.category SEPARATOR ',') AS top_categories,
                AVG(wh.completion_pct) AS avg_completion
             FROM watch_history wh
             JOIN videos vi ON vi.id = wh.video_id
             WHERE wh.user_id = ? AND wh.watched_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$userId]
        );

        $result = [
            'top_categories' => $signals ? array_filter(explode(',', $signals->top_categories ?? '')) : [],
            'top_hashtags'   => [],
            'avg_completion' => (float) ($signals->avg_completion ?? 0.5),
        ];

        $this->cache->set($key, serialize($result), 600);
        return $result;
    }

    private function predictCompletionRate(array $video, array $profile): float
    {
        // Simplified heuristic; real model is in the ML microservice
        $categoryBoost = in_array($video['category'], $profile['top_categories'], true) ? 0.15 : 0.0;
        $base          = $profile['avg_completion'];
        return min(1.0, $base + $categoryBoost);
    }

    private function predictEngagement(array $video, array $profile, string $type): float
    {
        // Simplified; real scores come from the ML ranking service
        $base = match($type) {
            'like'    => 0.05,
            'comment' => 0.01,
            'share'   => 0.005,
            'follow'  => 0.002,
            default   => 0.01,
        };

        $boost = in_array($video['category'], $profile['top_categories'], true) ? 2.0 : 1.0;
        return $base * $boost;
    }

    private function diversityBonus(array $video, array $profile): float
    {
        return in_array($video['category'], $profile['top_categories'], true) ? 0.0 : 0.3;
    }

    private function interleaveAds(array $videos, string $userId): array
    {
        $result  = [];
        $adEvery = 5;

        foreach ($videos as $i => $video) {
            $result[] = $video;
            if (($i + 1) % $adEvery === 0) {
                $result[] = ['_type' => 'ad_slot', 'position' => $i + 1];
            }
        }

        return $result;
    }

    private function fetchInterestBasedCandidates(array $profile, int $n): array
    {
        if (empty($profile['top_categories'])) return [];

        $placeholders = implode(',', array_fill(0, count($profile['top_categories']), '?'));
        return Database::fetchAll(
            "SELECT v.* FROM videos v
             WHERE v.status = 'public' AND v.category IN ({$placeholders})
             ORDER BY v.created_at DESC LIMIT {$n}",
            $profile['top_categories']
        );
    }

    private function fetchSocialGraphCandidates(string $userId, int $n): array
    {
        return Database::fetchAll(
            "SELECT v.* FROM videos v
             JOIN follows f ON f.following_id = v.author_id
             WHERE f.follower_id = ? AND v.status = 'public'
             ORDER BY v.created_at DESC LIMIT {$n}",
            [$userId]
        );
    }

    private function fetchTrendingCandidates(string $timezone, int $n): array
    {
        return Database::fetchAll(
            "SELECT v.* FROM videos v
             WHERE v.status = 'public' AND v.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY (v.like_count + v.share_count * 3) DESC LIMIT {$n}"
        );
    }

    private function fetchDiversityCandidates(array $profile, int $n): array
    {
        $excluded = $profile['top_categories'] ?: [''];
        $placeholders = implode(',', array_fill(0, count($excluded), '?'));

        return Database::fetchAll(
            "SELECT v.* FROM videos v
             WHERE v.status = 'public' AND v.category NOT IN ({$placeholders})
             ORDER BY RAND() LIMIT {$n}",
            $excluded
        );
    }

    private function getBlockedUserIds(string $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT blocked_id FROM blocks WHERE blocker_id = ?",
            [$userId]
        );
        return array_column($rows, 'blocked_id');
    }

    private function getRecentlySeenIds(string $userId, int $hours): array
    {
        $rows = Database::fetchAll(
            "SELECT video_id FROM watch_history WHERE user_id = ? AND watched_at > DATE_SUB(NOW(), INTERVAL ? HOUR)",
            [$userId, $hours]
        );
        return array_column($rows, 'video_id');
    }

    private function getFollowingIds(string $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT following_id FROM follows WHERE follower_id = ?",
            [$userId]
        );
        return array_column($rows, 'following_id');
    }

    private function encodeCursor(string $feedId, int $offset): string
    {
        return base64_encode(json_encode(['feed_id' => $feedId, 'offset' => $offset]));
    }

    private function decodeCursor(string $cursor): array
    {
        return json_decode(base64_decode($cursor), true);
    }

    private function toArray(array $rows): array
    {
        return array_map(fn($row) => (array) $row, $rows);
    }
}

