<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Moderation;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\ModerationService;
use AvatarTok\Services\NotificationService;

class ModerationController
{
    public function __construct(
        private readonly ModerationService   $moderation = new ModerationService(),
        private readonly NotificationService $notif      = new NotificationService()
    ) {}

    public function listReports(Request $request): Response
    {
        $page   = max((int) $request->query('page', 1), 1);
        $limit  = min((int) $request->query('limit', 20), 100);
        $status = $request->query('status', 'pending');
        $type   = $request->query('type');

        $result = $this->moderation->listReports($status, $type, $page, $limit);
        return Response::paginated($result['reports'], $result['total'], $page, $limit);
    }

    public function reportDetail(Request $request): Response
    {
        $reportId = $request->routeParam('reportId');
        $report   = $this->moderation->getReport($reportId);

        if (!$report) {
            return Response::error('Report not found.', 404);
        }

        return Response::ok($report);
    }

    public function reviewReport(Request $request): Response
    {
        $reportId = $request->routeParam('reportId');
        $data     = $request->validate([
            'action'  => 'required', // 'dismiss' | 'warn' | 'remove' | 'ban'
            'notes'   => 'max:1000',
        ]);

        $result = $this->moderation->reviewReport(
            $reportId,
            $request->user()->id,
            $data['action'],
            $data['notes'] ?? null
        );

        return Response::ok($result, 'Report reviewed.');
    }

    public function moderationQueue(Request $request): Response
    {
        $type  = $request->query('type', 'all'); // video|comment|avatar|chat
        $limit = min((int) $request->query('limit', 20), 50);

        $queue = $this->moderation->getQueue($type, $limit);
        return Response::ok($queue);
    }

    public function banContent(Request $request): Response
    {
        $contentId = $request->routeParam('contentId');
        $data      = $request->validate([
            'content_type' => 'required', // video|comment|sound|avatar
            'reason'       => 'required|max:500',
        ]);

        $this->moderation->banContent($contentId, $data['content_type'], $data['reason'], $request->user()->id);
        return Response::ok(null, 'Content removed from platform.');
    }

    public function restoreContent(Request $request): Response
    {
        $contentId = $request->routeParam('contentId');
        $data      = $request->validate(['content_type' => 'required']);

        $this->moderation->restoreContent($contentId, $data['content_type'], $request->user()->id);
        return Response::ok(null, 'Content restored.');
    }

    public function warnUser(Request $request): Response
    {
        $userId = $request->routeParam('userId');
        $data   = $request->validate([
            'reason'  => 'required|max:500',
            'message' => 'max:1000',
        ]);

        $this->moderation->warnUser($userId, $data['reason'], $data['message'] ?? null);
        $this->notif->sendModerationNotice($userId, 'warning', $data['reason']);

        return Response::ok(null, 'Warning issued.');
    }

    public function suspendUser(Request $request): Response
    {
        $userId = $request->routeParam('userId');
        $data   = $request->validate([
            'reason'       => 'required|max:500',
            'duration_days'=> 'required|numeric',
        ]);

        $until = new \DateTime("+{$data['duration_days']} days");
        $this->moderation->suspendUser($userId, $data['reason'], $until);
        $this->notif->sendModerationNotice($userId, 'suspended', $data['reason']);

        return Response::ok(['suspended_until' => $until->format('c')], 'User suspended.');
    }

    public function banUser(Request $request): Response
    {
        $userId = $request->routeParam('userId');
        $data   = $request->validate(['reason' => 'required|max:500']);

        $this->moderation->permanentBan($userId, $data['reason'], $request->user()->id);
        $this->notif->sendModerationNotice($userId, 'banned', $data['reason']);

        return Response::ok(null, 'User permanently banned.');
    }

    public function unbanUser(Request $request): Response
    {
        $userId = $request->routeParam('userId');
        $data   = $request->validate(['reason' => 'required|max:500']);

        $this->moderation->unban($userId, $data['reason'], $request->user()->id);
        return Response::ok(null, 'User unbanned.');
    }

    public function bannedWords(Request $request): Response
    {
        $lang  = $request->query('lang');
        $words = $this->moderation->getBannedWords($lang);
        return Response::ok($words);
    }

    public function addBannedWord(Request $request): Response
    {
        $data = $request->validate([
            'word'     => 'required|max:100',
            'language' => 'required|max:5',
            'severity' => 'required',
        ]);

        $word = $this->moderation->addBannedWord($data);
        return Response::created($word);
    }

    public function removeBannedWord(Request $request): Response
    {
        $wordId = $request->routeParam('wordId');
        $this->moderation->removeBannedWord($wordId);
        return Response::noContent();
    }

    public function aiFlaggedContent(Request $request): Response
    {
        $threshold = (float) $request->query('threshold', 0.7);
        $type      = $request->query('type');
        $page      = max((int) $request->query('page', 1), 1);
        $limit     = min((int) $request->query('limit', 20), 50);

        $result = $this->moderation->getAiFlagged($threshold, $type, $page, $limit);
        return Response::paginated($result['items'], $result['total'], $page, $limit);
    }
}
