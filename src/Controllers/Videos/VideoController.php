<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Videos;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\VideoService;
use AvatarTok\Services\StorageService;
use AvatarTok\Services\ModerationService;
use AvatarTok\Services\AnalyticsService;

class VideoController
{
    public function __construct(
        private readonly VideoService     $videos     = new VideoService(),
        private readonly StorageService   $storage    = new StorageService(),
        private readonly ModerationService $moderation = new ModerationService(),
        private readonly AnalyticsService  $analytics  = new AnalyticsService()
    ) {}

    public function show(Request $request): Response
    {
        $videoId = $request->routeParam('videoId');
        $video   = $this->videos->findById($videoId, $request->user()?->id);

        if (!$video) {
            return Response::error('Video not found.', 404);
        }

        $this->analytics->recordVideoView($videoId, $request->user()?->id, [
            'source'    => $request->query('source', 'direct'),
            'feed_id'   => $request->query('feed_id'),
        ]);

        return Response::ok($video);
    }

    /**
     * Step 1 of upload: get presigned S3 URL to upload directly from client.
     * Avoids routing large video files through the API server.
     */
    public function presignedUploadUrl(Request $request): Response
    {
        $data = $request->validate([
            'filename'  => 'required|max:255',
            'file_size' => 'required|numeric',
            'mime_type' => 'required',
        ]);

        $maxBytes = 500 * 1024 * 1024; // 500 MB
        if ((int) $data['file_size'] > $maxBytes) {
            return Response::error('Video file exceeds the 500 MB limit.', 422);
        }

        $allowed = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'];
        if (!in_array($data['mime_type'], $allowed, true)) {
            return Response::error('Unsupported video format.', 422);
        }

        $upload = $this->storage->createVideoUpload($request->user()->id, $data);

        return Response::ok([
            'upload_id'      => $upload['upload_id'],
            'presigned_url'  => $upload['presigned_url'],
            'expires_at'     => $upload['expires_at'],
            'fields'         => $upload['fields'],
        ]);
    }

    /**
     * Step 2 of upload: after S3 upload, register the video and trigger processing.
     */
    public function completeUpload(Request $request): Response
    {
        $data = $request->validate([
            'upload_id'   => 'required',
            'title'       => 'max:150',
            'description' => 'max:2000',
            'sound_id'    => 'max:36',
            'effect_ids'  => '',
            'hashtags'    => '',
            'privacy'     => 'required',
            'allow_duet'  => '',
            'allow_stitch'=> '',
            'allow_comments'=> '',
        ]);

        $video = $this->videos->completeUpload($request->user()->id, $data);

        // Queue AI moderation and FFmpeg processing asynchronously
        $this->moderation->queueVideoReview($video->id);

        return Response::created([
            'video_id'          => $video->id,
            'processing_status' => 'queued',
            'estimated_ready'   => '30-120 seconds',
        ], 'Video upload complete. Processing started.');
    }

    public function uploadStatus(Request $request): Response
    {
        $uploadId = $request->routeParam('uploadId');
        $status   = $this->videos->getUploadStatus($uploadId, $request->user()->id);

        if (!$status) {
            return Response::error('Upload not found.', 404);
        }

        return Response::ok($status);
    }

    public function update(Request $request): Response
    {
        $videoId = $request->routeParam('videoId');
        $video   = $this->videos->findOwned($videoId, $request->user()->id);

        if (!$video) {
            return Response::error('Video not found or access denied.', 404);
        }

        $data = $request->only([
            'title', 'description', 'hashtags',
            'privacy', 'allow_duet', 'allow_stitch', 'allow_comments',
        ]);

        $updated = $this->videos->update($videoId, $data);
        return Response::ok($updated, 'Video updated.');
    }

    public function delete(Request $request): Response
    {
        $videoId = $request->routeParam('videoId');
        $video   = $this->videos->findOwned($videoId, $request->user()->id);

        if (!$video) {
            return Response::error('Video not found or access denied.', 404);
        }

        $this->videos->softDelete($videoId);
        return Response::noContent();
    }

    public function like(Request $request): Response
    {
        $videoId = $request->routeParam('videoId');
        $result  = $this->videos->like($videoId, $request->user()->id);

        if (!$result) {
            return Response::error('Video not found.', 404);
        }

        return Response::ok(['likes_count' => $result['likes_count']], 'Liked.');
    }

    public function unlike(Request $request): Response
    {
        $videoId = $request->routeParam('videoId');
        $result  = $this->videos->unlike($videoId, $request->user()->id);

        return Response::ok(['likes_count' => $result['likes_count']], 'Like removed.');
    }

    public function listComments(Request $request): Response
    {
        $videoId = $request->routeParam('videoId');
        $page    = max((int) $request->query('page', 1), 1);
        $limit   = min((int) $request->query('limit', 20), 50);

        $result = $this->videos->getComments($videoId, $page, $limit);
        return Response::paginated($result['comments'], $result['total'], $page, $limit);
    }

    public function addComment(Request $request): Response
    {
        $videoId  = $request->routeParam('videoId');
        $data     = $request->validate(['text' => 'required|max:500']);
        $parentId = $request->input('parent_comment_id');

        $comment = $this->videos->addComment(
            $videoId,
            $request->user()->id,
            $data['text'],
            $parentId
        );

        return Response::created($comment);
    }

    public function deleteComment(Request $request): Response
    {
        $commentId = $request->routeParam('commentId');
        $success   = $this->videos->deleteComment($commentId, $request->user()->id);

        if (!$success) {
            return Response::error('Comment not found or access denied.', 404);
        }

        return Response::noContent();
    }

    public function share(Request $request): Response
    {
        $videoId  = $request->routeParam('videoId');
        $platform = $request->input('platform', 'copy_link');

        $shareData = $this->videos->generateShareLink($videoId, $platform);
        $this->analytics->recordShare($videoId, $request->user()->id, $platform);

        return Response::ok($shareData);
    }

    public function report(Request $request): Response
    {
        $videoId = $request->routeParam('videoId');
        $data    = $request->validate([
            'reason'  => 'required',
            'details' => 'max:500',
        ]);

        $this->videos->report($videoId, $request->user()->id, $data);
        return Response::ok(null, 'Report submitted. Thank you.');
    }

    public function byUser(Request $request): Response
    {
        $username = $request->routeParam('username');
        $page     = max((int) $request->query('page', 1), 1);
        $limit    = min((int) $request->query('limit', 20), 50);

        $result = $this->videos->getByUsername($username, $page, $limit, $request->user()?->id);
        return Response::paginated($result['videos'], $result['total'], $page, $limit);
    }

    public function likedByUser(Request $request): Response
    {
        $username = $request->routeParam('username');
        $page     = max((int) $request->query('page', 1), 1);
        $limit    = min((int) $request->query('limit', 20), 50);

        $result = $this->videos->getLikedBy($username, $page, $limit, $request->user()?->id);
        return Response::paginated($result['videos'], $result['total'], $page, $limit);
    }

    public function search(Request $request): Response
    {
        $q     = $request->query('q', '');
        $page  = max((int) $request->query('page', 1), 1);
        $limit = min((int) $request->query('limit', 20), 50);

        if (strlen($q) < 2) {
            return Response::error('Search query too short.', 422);
        }

        $result = $this->videos->search($q, $page, $limit, $request->user()?->id);
        return Response::paginated($result['videos'], $result['total'], $page, $limit);
    }

    public function duet(Request $request): Response
    {
        $videoId = $request->routeParam('videoId');
        $data    = $request->validate(['upload_id' => 'required']);

        $duet = $this->videos->createDuet($videoId, $request->user()->id, $data['upload_id']);
        $this->moderation->queueVideoReview($duet->id);

        return Response::created(['video_id' => $duet->id], 'Duet created.');
    }

    public function stitch(Request $request): Response
    {
        $videoId = $request->routeParam('videoId');
        $data    = $request->validate([
            'upload_id'      => 'required',
            'clip_start_sec' => 'required|numeric',
            'clip_end_sec'   => 'required|numeric',
        ]);

        if (($data['clip_end_sec'] - $data['clip_start_sec']) > 5) {
            return Response::error('Stitch clip cannot exceed 5 seconds.', 422);
        }

        $stitch = $this->videos->createStitch($videoId, $request->user()->id, $data);
        $this->moderation->queueVideoReview($stitch->id);

        return Response::created(['video_id' => $stitch->id], 'Stitch created.');
    }
}
