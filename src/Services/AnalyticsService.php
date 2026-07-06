<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use AvatarTok\Core\Database;

class AnalyticsService
{
    public function recordVideoView(string $videoId, ?string $userId, array $context): void
    {
        if (!$userId) {
            return; // Only track authenticated views
        }

        Database::query(
            "INSERT INTO watch_history (id, user_id, video_id, source, feed_id) VALUES (UUID(), ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE id = id", // debounce repeated fast views
            [$userId, $videoId, $context['source'] ?? null, $context['feed_id'] ?? null]
        );

        Database::query(
            "UPDATE videos SET view_count = view_count + 1 WHERE id = ?",
            [$videoId]
        );
    }

    public function recordShare(string $videoId, string $userId, string $platform): void
    {
        Database::query(
            "UPDATE videos SET share_count = share_count + 1 WHERE id = ?",
            [$videoId]
        );
    }

    public function getCreatorOverview(string $userId, string $period): array
    {
        [$start, $end] = $this->periodToDates($period);

        $totals = Database::fetchOne(
            "SELECT
                SUM(v.view_count)    AS total_views,
                SUM(v.like_count)    AS total_likes,
                SUM(v.comment_count) AS total_comments,
                SUM(v.share_count)   AS total_shares,
                COUNT(v.id)          AS video_count
             FROM videos v
             WHERE v.author_id = ? AND v.created_at BETWEEN ? AND ?",
            [$userId, $start, $end]
        );

        $followers = Database::fetchOne(
            "SELECT COUNT(*) AS c FROM follows WHERE following_id = ?",
            [$userId]
        );

        return [
            'period'         => $period,
            'total_views'    => (int) $totals->total_views,
            'total_likes'    => (int) $totals->total_likes,
            'total_comments' => (int) $totals->total_comments,
            'total_shares'   => (int) $totals->total_shares,
            'video_count'    => (int) $totals->video_count,
            'followers'      => (int) $followers->c,
        ];
    }

    public function getVideoDetail(string $videoId, string $userId, string $period): ?array
    {
        $video = Database::fetchOne(
            "SELECT * FROM videos WHERE id = ? AND author_id = ?",
            [$videoId, $userId]
        );

        if (!$video) {
            return null;
        }

        [$start, $end] = $this->periodToDates($period);

        $viewData = Database::fetchOne(
            "SELECT
                COUNT(*)          AS views,
                COUNT(DISTINCT user_id) AS unique_viewers,
                AVG(completion_pct)     AS avg_completion
             FROM watch_history
             WHERE video_id = ? AND watched_at BETWEEN ? AND ?",
            [$videoId, $start, $end]
        );

        $sources = Database::fetchAll(
            "SELECT source, COUNT(*) AS cnt FROM watch_history
             WHERE video_id = ? AND watched_at BETWEEN ? AND ?
             GROUP BY source ORDER BY cnt DESC",
            [$videoId, $start, $end]
        );

        return [
            'views'            => (int)   $viewData->views,
            'unique_viewers'   => (int)   $viewData->unique_viewers,
            'likes'            => (int)   $video->like_count,
            'comments'         => (int)   $video->comment_count,
            'shares'           => (int)   $video->share_count,
            'avg_watch_time'   => null,
            'completion_rate'  => round((float) $viewData->avg_completion, 2),
            'reach'            => (int)   $viewData->unique_viewers,
            'traffic_sources'  => $sources,
            'audience'         => $this->getAudienceInsights($userId, $period),
            'engagement_chart' => $this->getEngagementTimeSeries($videoId, $period),
        ];
    }

    public function getFollowerGrowth(string $userId, string $period): array
    {
        [$start, $end] = $this->periodToDates($period);

        return Database::fetchAll(
            "SELECT DATE(created_at) AS date, COUNT(*) AS new_followers
             FROM follows WHERE following_id = ? AND created_at BETWEEN ? AND ?
             GROUP BY DATE(created_at) ORDER BY date",
            [$userId, $start, $end]
        );
    }

    public function getViewGrowth(string $userId, string $period): array
    {
        [$start, $end] = $this->periodToDates($period);

        return Database::fetchAll(
            "SELECT DATE(wh.watched_at) AS date, COUNT(*) AS views
             FROM watch_history wh
             JOIN videos v ON v.id = wh.video_id
             WHERE v.author_id = ? AND wh.watched_at BETWEEN ? AND ?
             GROUP BY DATE(wh.watched_at) ORDER BY date",
            [$userId, $start, $end]
        );
    }

    public function getEngagementGrowth(string $userId, string $period): array
    {
        return []; // Aggregates likes+comments+shares per day
    }

    public function getAudienceInsights(string $userId, string $period): array
    {
        return [
            'top_countries' => Database::fetchAll(
                "SELECT u.country, COUNT(*) AS cnt
                 FROM watch_history wh
                 JOIN videos v ON v.id = wh.video_id
                 JOIN users u ON u.id = wh.user_id
                 WHERE v.author_id = ?
                 GROUP BY u.country ORDER BY cnt DESC LIMIT 10",
                [$userId]
            ),
            'age_groups' => [],  // Requires demographics table
            'gender'     => [],
        ];
    }

    public function getEarningsSummary(string $userId, string $period): array
    {
        [$start, $end] = $this->periodToDates($period);

        $gifts = Database::fetchOne(
            "SELECT SUM(creator_cut) AS total FROM gift_transactions
             WHERE receiver_id = ? AND created_at BETWEEN ? AND ?",
            [$userId, $start, $end]
        );

        return [
            'total_coins'      => (int) $gifts->total,
            'estimated_usd'    => round((int) $gifts->total / 100, 2),
            'pending_payout'   => $this->getPendingPayout($userId),
        ];
    }

    public function getGiftEarnings(string $userId, string $period): array
    {
        [$start, $end] = $this->periodToDates($period);

        return Database::fetchAll(
            "SELECT DATE(created_at) AS date, SUM(creator_cut) AS coins
             FROM gift_transactions WHERE receiver_id = ? AND created_at BETWEEN ? AND ?
             GROUP BY DATE(created_at) ORDER BY date",
            [$userId, $start, $end]
        );
    }

    public function getCreatorFundEarnings(string $userId, string $period): array
    {
        return []; // Creator fund not yet implemented
    }

    public function getSubscriptionEarnings(string $userId, string $period): array
    {
        return []; // Creator subscriptions future feature
    }

    public function getAdShareEarnings(string $userId, string $period): array
    {
        return []; // Revenue share from ads on videos
    }

    // ── Platform analytics (admin) ────────────────────────────────────────────

    public function getDauTimeSeries(int $days): array
    {
        return Database::fetchAll(
            "SELECT DATE(last_login_at) AS date, COUNT(*) AS dau
             FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(last_login_at) ORDER BY date",
            [$days]
        );
    }

    public function getMauTimeSeries(int $months): array
    {
        return Database::fetchAll(
            "SELECT DATE_FORMAT(last_login_at, '%Y-%m') AS month, COUNT(*) AS mau
             FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY month ORDER BY month",
            [$months]
        );
    }

    public function getRetentionCohorts(string $cohortSize, int $periods): array
    {
        return []; // Complex cohort query — typically done in analytics DB / dbt
    }

    public function getPlatformRevenue(string $period): array
    {
        [$start, $end] = $this->periodToDates($period);

        $row = Database::fetchOne(
            "SELECT SUM(amount_cents) AS total FROM transactions
             WHERE status = 'completed' AND created_at BETWEEN ? AND ?",
            [$start, $end]
        );

        return ['total_cents' => (int) $row->total, 'period' => $period];
    }

    public function getCoinSalesRevenue(string $period): array   { return []; }
    public function getAdsRevenue(string $period): array         { return []; }
    public function getSubscriptionRevenue(string $period): array{ return []; }
    public function getGiftPlatformCut(string $period): array    { return []; }

    public function getContentReport(string $period): array
    {
        [$start, $end] = $this->periodToDates($period);

        return [
            'videos_published' => (int) Database::fetchOne(
                "SELECT COUNT(*) AS c FROM videos WHERE created_at BETWEEN ? AND ? AND status = 'public'",
                [$start, $end]
            )->c,
            'videos_removed' => (int) Database::fetchOne(
                "SELECT COUNT(*) AS c FROM videos WHERE removed_at BETWEEN ? AND ?",
                [$start, $end]
            )->c,
            'reports_received' => (int) Database::fetchOne(
                "SELECT COUNT(*) AS c FROM reports WHERE created_at BETWEEN ? AND ?",
                [$start, $end]
            )->c,
        ];
    }

    public function getLiveReport(string $period): array
    {
        [$start, $end] = $this->periodToDates($period);

        return Database::fetchAll(
            "SELECT COUNT(*) AS streams, SUM(total_viewers) AS total_views, SUM(peak_viewers) AS peak
             FROM live_streams WHERE started_at BETWEEN ? AND ?",
            [$start, $end]
        );
    }

    public function getRegionBreakdown(string $metric, string $period): array
    {
        return Database::fetchAll(
            "SELECT country, COUNT(*) AS value FROM users GROUP BY country ORDER BY value DESC"
        );
    }

    public function generateExport(string $type, string $period, string $format): array
    {
        return [
            'url'        => "https://cdn.avatartok.com/exports/report_{$type}_{$period}.{$format}",
            'expires_at' => date('c', strtotime('+1 hour')),
            'rows'       => 0,
        ];
    }

    public function getVideosList(string $userId, string $period, int $page, int $limit, string $sort): array
    {
        $col = match($sort) {
            'likes'    => 'like_count',
            'comments' => 'comment_count',
            'shares'   => 'share_count',
            default    => 'view_count',
        };

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM videos WHERE author_id = ?",
            [$userId]
        )->c;

        $videos = Database::fetchAll(
            "SELECT id, title, thumbnail_key, view_count, like_count, comment_count, share_count, created_at
             FROM videos WHERE author_id = ? ORDER BY {$col} DESC LIMIT ? OFFSET ?",
            [$userId, $limit, ($page - 1) * $limit]
        );

        return ['videos' => $videos, 'total' => $total];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function periodToDates(string $period): array
    {
        $end   = date('Y-m-d 23:59:59');
        $start = match($period) {
            '7d'   => date('Y-m-d 00:00:00', strtotime('-7 days')),
            '14d'  => date('Y-m-d 00:00:00', strtotime('-14 days')),
            '30d'  => date('Y-m-d 00:00:00', strtotime('-30 days')),
            '90d'  => date('Y-m-d 00:00:00', strtotime('-90 days')),
            '365d' => date('Y-m-d 00:00:00', strtotime('-365 days')),
            default => date('Y-m-d 00:00:00', strtotime('-7 days')),
        };

        return [$start, $end];
    }

    private function getEngagementTimeSeries(string $videoId, string $period): array
    {
        [$start, $end] = $this->periodToDates($period);

        return Database::fetchAll(
            "SELECT DATE(watched_at) AS date, COUNT(*) AS views, AVG(completion_pct) AS avg_completion
             FROM watch_history WHERE video_id = ? AND watched_at BETWEEN ? AND ?
             GROUP BY DATE(watched_at) ORDER BY date",
            [$videoId, $start, $end]
        );
    }

    private function getPendingPayout(string $userId): int
    {
        $row = Database::fetchOne("SELECT pending_balance FROM wallets WHERE user_id = ?", [$userId]);
        return (int) ($row->pending_balance ?? 0);
    }
}
