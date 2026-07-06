<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Monetization;

use AvatarTok\Core\Database;
use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use Ramsey\Uuid\Uuid;

class AdController
{
    public function nextAd(Request $request): Response
    {
        $user    = $request->user();
        $context = [
            'country'  => $user->country ?? null,
            'category' => $request->query('category'),
        ];

        $ad = $this->selectAd($user->id, $context);

        if (!$ad) {
            return Response::ok(null); // No ad to show
        }

        return Response::ok([
            'ad_id'        => $ad->id,
            'campaign_id'  => $ad->id,
            'creative_url' => $ad->creative_url,
            'click_url'    => $ad->click_url,
            'can_skip_at'  => 5, // seconds
            'duration'     => 15,
        ]);
    }

    public function recordImpression(Request $request): Response
    {
        $campaignId = $request->routeParam('adId');
        $cost       = $this->calculateCost($campaignId);

        Database::insert('ad_impressions', [
            'id'          => Uuid::uuid4()->toString(),
            'campaign_id' => $campaignId,
            'user_id'     => $request->user()->id,
            'cost_cents'  => $cost,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        Database::query(
            "UPDATE ad_campaigns SET spent_cents = spent_cents + ? WHERE id = ?",
            [$cost, $campaignId]
        );

        return Response::noContent();
    }

    public function recordClick(Request $request): Response
    {
        Database::query(
            "UPDATE ad_impressions SET clicked = 1 WHERE campaign_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1",
            [$request->routeParam('adId'), $request->user()->id]
        );

        return Response::noContent();
    }

    public function recordSkip(Request $request): Response
    {
        Database::query(
            "UPDATE ad_impressions SET skipped = 1 WHERE campaign_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1",
            [$request->routeParam('adId'), $request->user()->id]
        );

        return Response::noContent();
    }

    public function adPreferences(Request $request): Response
    {
        $prefs = Database::fetchOne(
            "SELECT * FROM ad_preferences WHERE user_id = ?", [$request->user()->id]
        );

        return Response::ok($prefs ?: ['personalized_ads' => true, 'interests' => []]);
    }

    public function updateAdPreferences(Request $request): Response
    {
        $data   = $request->only(['personalized_ads', 'interests']);
        $userId = $request->user()->id;

        Database::query(
            "INSERT INTO ad_preferences (user_id, personalized_ads, interests) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE personalized_ads = VALUES(personalized_ads), interests = VALUES(interests)",
            [$userId, $data['personalized_ads'] ? 1 : 0, json_encode($data['interests'] ?? [])]
        );

        return Response::ok(null, 'Ad preferences updated.');
    }

    // ── Creator ad manager ────────────────────────────────────────────────────

    public function creatorList(Request $request): Response
    {
        $campaigns = Database::fetchAll(
            "SELECT * FROM ad_campaigns WHERE advertiser_id = ? ORDER BY created_at DESC",
            [$request->user()->id]
        );

        return Response::ok($campaigns);
    }

    public function createCampaign(Request $request): Response
    {
        $data = $request->validate([
            'name'         => 'required|max:200',
            'budget_cents' => 'required|numeric',
            'bid_cpm_cents'=> 'required|numeric',
            'creative_url' => 'required',
        ]);

        $extras = $request->only([
            'click_url', 'target_country', 'target_category',
            'target_age_min', 'target_age_max', 'starts_at', 'ends_at',
        ]);

        $id = Uuid::uuid4()->toString();
        Database::insert('ad_campaigns', array_merge([
            'id'            => $id,
            'advertiser_id' => $request->user()->id,
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
        ], $data, $extras));

        return Response::created(
            Database::fetchOne("SELECT * FROM ad_campaigns WHERE id = ?", [$id]),
            'Campaign created.'
        );
    }

    public function campaignDetails(Request $request): Response
    {
        $campaignId = $request->routeParam('campaignId');
        $campaign   = Database::fetchOne(
            "SELECT * FROM ad_campaigns WHERE id = ? AND advertiser_id = ?",
            [$campaignId, $request->user()->id]
        );

        if (!$campaign) {
            return Response::error('Campaign not found.', 404);
        }

        return Response::ok($campaign);
    }

    public function updateCampaign(Request $request): Response
    {
        $campaignId = $request->routeParam('campaignId');
        $data       = $request->only(['name', 'status', 'budget_cents', 'bid_cpm_cents', 'ends_at']);

        $affected = Database::update(
            'ad_campaigns',
            $data,
            ['id' => $campaignId, 'advertiser_id' => $request->user()->id]
        );

        if (!$affected) {
            return Response::error('Campaign not found.', 404);
        }

        return Response::ok(null, 'Campaign updated.');
    }

    public function deleteCampaign(Request $request): Response
    {
        Database::update(
            'ad_campaigns',
            ['status' => 'completed'],
            ['id' => $request->routeParam('campaignId'), 'advertiser_id' => $request->user()->id]
        );

        return Response::noContent();
    }

    public function campaignStats(Request $request): Response
    {
        $campaignId = $request->routeParam('campaignId');

        $stats = Database::fetchOne(
            "SELECT
                COUNT(*) AS impressions,
                SUM(clicked) AS clicks,
                SUM(skipped) AS skips,
                SUM(cost_cents) AS total_cost_cents,
                ROUND(100 * SUM(clicked) / NULLIF(COUNT(*), 0), 2) AS ctr
             FROM ad_impressions WHERE campaign_id = ?",
            [$campaignId]
        );

        return Response::ok($stats);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function selectAd(string $userId, array $context): ?object
    {
        $where  = "WHERE ac.status = 'active' AND ac.spent_cents < ac.budget_cents";
        $params = [];

        if ($context['country']) {
            $where   .= " AND (ac.target_country IS NULL OR ac.target_country = ?)";
            $params[] = $context['country'];
        }

        return Database::fetchOne(
            "SELECT ac.* FROM ad_campaigns ac
             {$where}
             AND ac.id NOT IN (
                 SELECT campaign_id FROM ad_impressions WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             )
             ORDER BY ac.bid_cpm_cents DESC LIMIT 1",
            [...$params, $userId]
        );
    }

    private function calculateCost(string $campaignId): int
    {
        $campaign = Database::fetchOne("SELECT bid_cpm_cents FROM ad_campaigns WHERE id = ?", [$campaignId]);
        return (int) round(($campaign->bid_cpm_cents ?? 0) / 1000);
    }
}
