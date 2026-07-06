<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Analytics;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\AnalyticsService;

class AnalyticsController
{
    public function __construct(
        private readonly AnalyticsService $analytics = new AnalyticsService()
    ) {}

    // ── Creator analytics ─────────────────────────────────────────────────────

    public function creatorOverview(Request $request): Response
    {
        $userId   = $request->user()->id;
        $period   = $request->query('period', '7d');
        $overview = $this->analytics->getCreatorOverview($userId, $period);

        return Response::ok($overview);
    }

    public function videoAnalytics(Request $request): Response
    {
        $userId  = $request->user()->id;
        $period  = $request->query('period', '7d');
        $page    = max((int) $request->query('page', 1), 1);
        $limit   = min((int) $request->query('limit', 20), 50);
        $sort    = $request->query('sort', 'views'); // views|likes|comments|shares

        $result = $this->analytics->getVideosList($userId, $period, $page, $limit, $sort);
        return Response::paginated($result['videos'], $result['total'], $page, $limit);
    }

    public function videoDetail(Request $request): Response
    {
        $videoId = $request->routeParam('videoId');
        $period  = $request->query('period', '7d');

        $data = $this->analytics->getVideoDetail($videoId, $request->user()->id, $period);

        if (!$data) {
            return Response::error('Video not found or access denied.', 404);
        }

        return Response::ok([
            'summary' => [
                'views'           => $data['views'],
                'unique_viewers'  => $data['unique_viewers'],
                'likes'           => $data['likes'],
                'comments'        => $data['comments'],
                'shares'          => $data['shares'],
                'avg_watch_time'  => $data['avg_watch_time'],
                'completion_rate' => $data['completion_rate'],
                'reach'           => $data['reach'],
            ],
            'traffic_sources' => $data['traffic_sources'],
            'audience'        => $data['audience'],
            'engagement_over_time' => $data['engagement_chart'],
        ]);
    }

    public function audienceInsights(Request $request): Response
    {
        $userId  = $request->user()->id;
        $period  = $request->query('period', '30d');

        return Response::ok($this->analytics->getAudienceInsights($userId, $period));
    }

    public function growthMetrics(Request $request): Response
    {
        $userId = $request->user()->id;
        $period = $request->query('period', '30d');

        return Response::ok([
            'followers_chart'    => $this->analytics->getFollowerGrowth($userId, $period),
            'views_chart'        => $this->analytics->getViewGrowth($userId, $period),
            'engagement_chart'   => $this->analytics->getEngagementGrowth($userId, $period),
        ]);
    }

    public function earningsSummary(Request $request): Response
    {
        $userId = $request->user()->id;
        $period = $request->query('period', '30d');

        return Response::ok($this->analytics->getEarningsSummary($userId, $period));
    }

    public function earningsBreakdown(Request $request): Response
    {
        $userId = $request->user()->id;
        $period = $request->query('period', '30d');

        return Response::ok([
            'gifts'         => $this->analytics->getGiftEarnings($userId, $period),
            'creator_fund'  => $this->analytics->getCreatorFundEarnings($userId, $period),
            'subscriptions' => $this->analytics->getSubscriptionEarnings($userId, $period),
            'ads_share'     => $this->analytics->getAdShareEarnings($userId, $period),
        ]);
    }

    // ── Platform analytics (admin) ────────────────────────────────────────────

    public function dailyActiveUsers(Request $request): Response
    {
        $days = min((int) $request->query('days', 30), 365);
        return Response::ok($this->analytics->getDauTimeSeries($days));
    }

    public function monthlyActiveUsers(Request $request): Response
    {
        $months = min((int) $request->query('months', 12), 24);
        return Response::ok($this->analytics->getMauTimeSeries($months));
    }

    public function retentionCohorts(Request $request): Response
    {
        $cohortSize = $request->query('cohort', 'week'); // week|month
        $periods    = min((int) $request->query('periods', 8), 24);

        return Response::ok($this->analytics->getRetentionCohorts($cohortSize, $periods));
    }

    public function revenueReport(Request $request): Response
    {
        $period = $request->query('period', '30d');
        return Response::ok($this->analytics->getPlatformRevenue($period));
    }

    public function revenueBreakdown(Request $request): Response
    {
        $period = $request->query('period', '30d');

        return Response::ok([
            'coin_sales'       => $this->analytics->getCoinSalesRevenue($period),
            'ads'              => $this->analytics->getAdsRevenue($period),
            'subscriptions'    => $this->analytics->getSubscriptionRevenue($period),
            'gift_platform_cut'=> $this->analytics->getGiftPlatformCut($period),
        ]);
    }

    public function contentReport(Request $request): Response
    {
        $period = $request->query('period', '7d');
        return Response::ok($this->analytics->getContentReport($period));
    }

    public function liveStreamReport(Request $request): Response
    {
        $period = $request->query('period', '7d');
        return Response::ok($this->analytics->getLiveReport($period));
    }

    public function regionBreakdown(Request $request): Response
    {
        $metric = $request->query('metric', 'users'); // users|revenue|views
        $period = $request->query('period', '30d');

        return Response::ok($this->analytics->getRegionBreakdown($metric, $period));
    }

    public function exportReport(Request $request): Response
    {
        $type   = $request->query('type', 'revenue');
        $period = $request->query('period', '30d');
        $format = $request->query('format', 'csv');

        $export = $this->analytics->generateExport($type, $period, $format);

        return Response::ok([
            'download_url' => $export['url'],
            'expires_at'   => $export['expires_at'],
            'rows'         => $export['rows'],
        ]);
    }
}
