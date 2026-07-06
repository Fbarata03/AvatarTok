<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Videos;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\AlgorithmService;
use AvatarTok\Services\VideoService;

class FeedController
{
    public function __construct(
        private readonly AlgorithmService $algorithm = new AlgorithmService(),
        private readonly VideoService     $videos    = new VideoService()
    ) {}

    /**
     * Personalized "For You" page — signals used:
     * - watch history (completion rate, replays)
     * - interaction graph (likes, comments, shares, follows)
     * - content embeddings (sound, effect, hashtag co-occurrence)
     * - geographic + temporal trends
     * - avatar expression engagement (smile rate on happy videos, etc.)
     * - diversity injection (prevents filter bubbles)
     */
    public function algorithmicFeed(Request $request): Response
    {
        $user    = $request->user();
        $cursor  = $request->query('cursor');
        $limit   = min((int) $request->query('limit', 10), 20);
        $context = [
            'device_type'   => $request->getHeader('X-Device-Type', 'mobile'),
            'network_type'  => $request->getHeader('X-Network-Type', 'wifi'),
            'timezone'      => $request->query('tz', 'UTC'),
        ];

        $result = $this->algorithm->buildForYouFeed($user->id, $cursor, $limit, $context);

        return Response::ok([
            'videos'      => $result['videos'],
            'next_cursor' => $result['next_cursor'],
            'feed_id'     => $result['feed_id'],
        ]);
    }

    public function trending(Request $request): Response
    {
        $country  = $request->query('country', 'global');
        $category = $request->query('category');
        $limit    = min((int) $request->query('limit', 20), 50);
        $page     = max((int) $request->query('page', 1), 1);

        $result = $this->algorithm->getTrending($country, $category, $page, $limit);

        return Response::paginated(
            $result['videos'],
            $result['total'],
            $page,
            $limit
        );
    }

    public function followingFeed(Request $request): Response
    {
        $user   = $request->user();
        $cursor = $request->query('cursor');
        $limit  = min((int) $request->query('limit', 10), 20);

        $result = $this->algorithm->buildFollowingFeed($user->id, $cursor, $limit);

        return Response::ok([
            'videos'      => $result['videos'],
            'next_cursor' => $result['next_cursor'],
        ]);
    }
}
