<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Live;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\LiveStreamService;
use AvatarTok\Services\GiftService;

class LiveStreamController
{
    public function __construct(
        private readonly LiveStreamService $liveService  = new LiveStreamService(),
        private readonly GiftService       $giftService  = new GiftService()
    ) {}

    public function start(Request $request): Response
    {
        $user = $request->user();
        $data = $request->validate([
            'title'       => 'required|max:100',
            'description' => 'max:500',
            'category'    => 'required',
        ]);

        $extras = $request->only(['allow_gifts', 'minimum_gift_coins', 'co_host_mode']);

        // Check if user already has an active stream
        if ($this->liveService->hasActiveStream($user->id)) {
            return Response::error('You already have an active live stream.', 409);
        }

        $stream = $this->liveService->startStream($user->id, array_merge($data, $extras));

        return Response::created([
            'stream_id'     => $stream->id,
            'rtmp_url'      => $stream->rtmp_url,       // Client pushes RTMP
            'stream_key'    => $stream->stream_key,
            'ws_url'        => $stream->ws_url,          // For chat + gifts
            'ws_token'      => $stream->ws_token,
            'hls_url'       => $stream->hls_url,         // Viewers consume HLS
            'viewer_token'  => null,
        ], 'Live stream started.');
    }

    public function end(Request $request): Response
    {
        $streamId = $request->routeParam('streamId');
        $stream   = $this->liveService->findOwned($streamId, $request->user()->id);

        if (!$stream) {
            return Response::error('Stream not found or access denied.', 404);
        }

        $summary = $this->liveService->endStream($streamId);

        return Response::ok([
            'duration_seconds' => $summary['duration'],
            'peak_viewers'     => $summary['peak_viewers'],
            'total_viewers'    => $summary['total_viewers'],
            'gifts_received'   => $summary['gifts_received'],
            'coins_earned'     => $summary['coins_earned'],
            'replay_url'       => $summary['replay_url'],
        ], 'Stream ended.');
    }

    public function listActive(Request $request): Response
    {
        $category = $request->query('category');
        $limit    = min((int) $request->query('limit', 20), 50);
        $page     = max((int) $request->query('page', 1), 1);

        $result = $this->liveService->listActive($category, $page, $limit);
        return Response::paginated($result['streams'], $result['total'], $page, $limit);
    }

    public function show(Request $request): Response
    {
        $streamId = $request->routeParam('streamId');
        $stream   = $this->liveService->findById($streamId);

        if (!$stream) {
            return Response::error('Stream not found.', 404);
        }

        $joinToken = $this->liveService->issueViewerToken($streamId, $request->user()->id);

        return Response::ok([
            'stream'       => $stream,
            'viewer_token' => $joinToken,
            'hls_url'      => $stream->hls_url,
            'ws_url'       => $stream->ws_url,
        ]);
    }

    public function join(Request $request): Response
    {
        $streamId = $request->routeParam('streamId');
        $result   = $this->liveService->joinStream($streamId, $request->user()->id);

        if (!$result['success']) {
            return Response::error($result['message'], 403);
        }

        return Response::ok([
            'viewer_token'   => $result['token'],
            'ws_url'         => $result['ws_url'],
            'viewer_count'   => $result['viewer_count'],
        ]);
    }

    public function leave(Request $request): Response
    {
        $streamId = $request->routeParam('streamId');
        $this->liveService->leaveStream($streamId, $request->user()->id);

        return Response::noContent();
    }

    public function viewers(Request $request): Response
    {
        $streamId = $request->routeParam('streamId');
        $viewers  = $this->liveService->getViewers($streamId);

        return Response::ok(['viewers' => $viewers, 'count' => count($viewers)]);
    }

    public function sendGift(Request $request): Response
    {
        $streamId = $request->routeParam('streamId');
        $data     = $request->validate([
            'gift_id'  => 'required',
            'quantity' => 'required|numeric',
        ]);

        $result = $this->giftService->sendLiveGift(
            $request->user()->id,
            $streamId,
            $data['gift_id'],
            (int) $data['quantity']
        );

        if (!$result['success']) {
            return Response::error($result['message'], 402);
        }

        return Response::ok([
            'animation'      => $result['animation_key'],
            'coins_spent'    => $result['coins_spent'],
            'wallet_balance' => $result['new_balance'],
        ]);
    }

    public function inviteCoHost(Request $request): Response
    {
        $streamId = $request->routeParam('streamId');
        $data     = $request->validate(['user_id' => 'required']);

        $result = $this->liveService->inviteCoHost($streamId, $request->user()->id, $data['user_id']);

        return Response::ok($result, 'Co-host invitation sent.');
    }

    public function schedule(Request $request): Response
    {
        $data = $request->validate([
            'title'        => 'required|max:100',
            'description'  => 'max:500',
            'scheduled_at' => 'required',
        ]);

        $scheduled = $this->liveService->scheduleStream($request->user()->id, $data);
        return Response::created($scheduled, 'Stream scheduled.');
    }

    public function listScheduled(Request $request): Response
    {
        $streams = $this->liveService->getScheduled($request->user()->id);
        return Response::ok($streams);
    }

    public function replay(Request $request): Response
    {
        $streamId = $request->routeParam('streamId');
        $replay   = $this->liveService->getReplay($streamId, $request->user()?->id);

        if (!$replay) {
            return Response::error('Replay not available.', 404);
        }

        return Response::ok($replay);
    }
}
