<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use AvatarTok\Core\Database;
use Ramsey\Uuid\Uuid;

class VideoService
{
    public function findById(string $videoId, ?string $viewerId): ?object
    {
        $video = Database::fetchOne(
            "SELECT v.*, u.username, u.display_name, av.avatar_url
             FROM videos v
             JOIN users u  ON u.id  = v.author_id
             LEFT JOIN avatars av ON av.user_id = u.id
             WHERE v.id = ? AND v.status IN ('public','friends_only')",
            [$videoId]
        );

        if (!$video) {
            return null;
        }

        if ($viewerId) {
            $video->is_liked = (bool) Database::fetchOne(
                "SELECT 1 FROM video_likes WHERE user_id = ? AND video_id = ?",
                [$viewerId, $videoId]
            );
        }

        return $video;
    }

    public function findOwned(string $videoId, string $userId): ?object
    {
        return Database::fetchOne(
            "SELECT * FROM videos WHERE id = ? AND author_id = ?",
            [$videoId, $userId]
        );
    }

    public function completeUpload(string $userId, array $data): object
    {
        $upload = Database::fetchOne(
            "SELECT * FROM video_uploads WHERE id = ? AND user_id = ?",
            [$data['upload_id'], $userId]
        );

        if (!$upload) {
            throw new \RuntimeException('Upload not found.');
        }

        $videoId = Uuid::uuid4()->toString();

        Database::beginTransaction();
        try {
            Database::insert('videos', [
                'id'            => $videoId,
                'author_id'     => $userId,
                'title'         => $data['title'] ?? null,
                'description'   => $data['description'] ?? null,
                's3_key'        => $upload->s3_key,
                'sound_id'      => $data['sound_id'] ?? null,
                'effect_ids'    => isset($data['effect_ids']) ? json_encode($data['effect_ids']) : null,
                'hashtags'      => isset($data['hashtags']) ? json_encode($data['hashtags']) : null,
                'privacy'       => $data['privacy'],
                'status'        => 'processing',
                'allow_duet'    => ($data['allow_duet'] ?? true) ? 1 : 0,
                'allow_stitch'  => ($data['allow_stitch'] ?? true) ? 1 : 0,
                'allow_comments'=> ($data['allow_comments'] ?? true) ? 1 : 0,
                'avatar_recorded' => 1,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);

            Database::update('video_uploads', ['status' => 'complete'], ['id' => $data['upload_id']]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return Database::fetchOne("SELECT * FROM videos WHERE id = ?", [$videoId]);
    }

    public function update(string $videoId, array $data): object
    {
        if (!empty($data)) {
            Database::update('videos', $data, ['id' => $videoId]);
        }

        return Database::fetchOne("SELECT * FROM videos WHERE id = ?", [$videoId]);
    }

    public function softDelete(string $videoId): void
    {
        Database::update('videos', ['status' => 'removed', 'removed_at' => date('Y-m-d H:i:s')], ['id' => $videoId]);
    }

    public function like(string $videoId, string $userId): ?array
    {
        $video = Database::fetchOne("SELECT id FROM videos WHERE id = ?", [$videoId]);
        if (!$video) return null;

        try {
            Database::insert('video_likes', [
                'user_id'  => $userId,
                'video_id' => $videoId,
            ]);
            Database::query("UPDATE videos SET like_count = like_count + 1 WHERE id = ?", [$videoId]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') throw $e; // Ignore duplicate key
        }

        $updated = Database::fetchOne("SELECT like_count FROM videos WHERE id = ?", [$videoId]);
        return ['likes_count' => (int) $updated->like_count];
    }

    public function unlike(string $videoId, string $userId): array
    {
        $deleted = Database::query(
            "DELETE FROM video_likes WHERE user_id = ? AND video_id = ?",
            [$userId, $videoId]
        )->rowCount();

        if ($deleted) {
            Database::query("UPDATE videos SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?", [$videoId]);
        }

        $updated = Database::fetchOne("SELECT like_count FROM videos WHERE id = ?", [$videoId]);
        return ['likes_count' => (int) ($updated->like_count ?? 0)];
    }

    public function getComments(string $videoId, int $page, int $limit): array
    {
        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM comments WHERE video_id = ? AND status = 'visible' AND parent_id IS NULL",
            [$videoId]
        )->c;

        $comments = Database::fetchAll(
            "SELECT c.*, u.username, av.avatar_url
             FROM comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN avatars av ON av.user_id = u.id
             WHERE c.video_id = ? AND c.status = 'visible' AND c.parent_id IS NULL
             ORDER BY c.created_at DESC LIMIT ? OFFSET ?",
            [$videoId, $limit, ($page - 1) * $limit]
        );

        return ['comments' => $comments, 'total' => $total];
    }

    public function addComment(string $videoId, string $userId, string $text, ?string $parentId): object
    {
        $id = Uuid::uuid4()->toString();

        Database::insert('comments', [
            'id'        => $id,
            'video_id'  => $videoId,
            'user_id'   => $userId,
            'parent_id' => $parentId,
            'text'      => $text,
            'created_at'=> date('Y-m-d H:i:s'),
        ]);

        Database::query("UPDATE videos SET comment_count = comment_count + 1 WHERE id = ?", [$videoId]);

        return Database::fetchOne(
            "SELECT c.*, u.username, av.avatar_url FROM comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN avatars av ON av.user_id = u.id
             WHERE c.id = ?",
            [$id]
        );
    }

    public function deleteComment(string $commentId, string $userId): bool
    {
        $deleted = Database::query(
            "UPDATE comments SET status = 'removed' WHERE id = ? AND user_id = ? AND status = 'visible'",
            [$commentId, $userId]
        )->rowCount();

        return $deleted > 0;
    }

    public function generateShareLink(string $videoId, string $platform): array
    {
        $url = "{$_ENV['APP_URL']}/v/{$videoId}";

        return [
            'url'      => $url,
            'platform' => $platform,
            'deeplink' => "avatartok://video/{$videoId}",
        ];
    }

    public function report(string $videoId, string $userId, array $data): void
    {
        Database::insert('reports', [
            'id'           => Uuid::uuid4()->toString(),
            'reporter_id'  => $userId,
            'content_id'   => $videoId,
            'content_type' => 'video',
            'reason'       => $data['reason'],
            'details'      => $data['details'] ?? null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public function getByUsername(string $username, int $page, int $limit, ?string $viewerId): array
    {
        $user = Database::fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
        if (!$user) return ['videos' => [], 'total' => 0];

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM videos WHERE author_id = ? AND status = 'public'",
            [$user->id]
        )->c;

        $videos = Database::fetchAll(
            "SELECT * FROM videos WHERE author_id = ? AND status = 'public'
             ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$user->id, $limit, ($page - 1) * $limit]
        );

        return ['videos' => $videos, 'total' => $total];
    }

    public function getLikedBy(string $username, int $page, int $limit, ?string $viewerId): array
    {
        $user = Database::fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
        if (!$user) return ['videos' => [], 'total' => 0];

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM video_likes WHERE user_id = ?",
            [$user->id]
        )->c;

        $videos = Database::fetchAll(
            "SELECT v.* FROM videos v
             JOIN video_likes vl ON vl.video_id = v.id
             WHERE vl.user_id = ? AND v.status = 'public'
             ORDER BY vl.created_at DESC LIMIT ? OFFSET ?",
            [$user->id, $limit, ($page - 1) * $limit]
        );

        return ['videos' => $videos, 'total' => $total];
    }

    public function search(string $q, int $page, int $limit, ?string $viewerId): array
    {
        $like  = "%{$q}%";
        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM videos WHERE status = 'public' AND (title LIKE ? OR description LIKE ?)",
            [$like, $like]
        )->c;

        $videos = Database::fetchAll(
            "SELECT v.*, u.username FROM videos v JOIN users u ON u.id = v.author_id
             WHERE v.status = 'public' AND (v.title LIKE ? OR v.description LIKE ?)
             ORDER BY v.view_count DESC LIMIT ? OFFSET ?",
            [$like, $like, $limit, ($page - 1) * $limit]
        );

        return ['videos' => $videos, 'total' => $total];
    }

    public function getUploadStatus(string $uploadId, string $userId): ?object
    {
        return Database::fetchOne(
            "SELECT id, status, metadata FROM video_uploads WHERE id = ? AND user_id = ?",
            [$uploadId, $userId]
        );
    }

    public function createDuet(string $originalId, string $userId, string $uploadId): object
    {
        $original = $this->findById($originalId, null);

        if (!$original || !$original->allow_duet) {
            throw new \RuntimeException('Duet not allowed on this video.');
        }

        $upload = Database::fetchOne("SELECT * FROM video_uploads WHERE id = ? AND user_id = ?", [$uploadId, $userId]);

        $videoId = Uuid::uuid4()->toString();
        Database::insert('videos', [
            'id'          => $videoId,
            'author_id'   => $userId,
            's3_key'      => $upload->s3_key,
            'duet_of'     => $originalId,
            'privacy'     => 'public',
            'status'      => 'processing',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return Database::fetchOne("SELECT * FROM videos WHERE id = ?", [$videoId]);
    }

    public function createStitch(string $originalId, string $userId, array $data): object
    {
        $original = $this->findById($originalId, null);

        if (!$original || !$original->allow_stitch) {
            throw new \RuntimeException('Stitch not allowed on this video.');
        }

        $upload  = Database::fetchOne("SELECT * FROM video_uploads WHERE id = ? AND user_id = ?", [$data['upload_id'], $userId]);
        $videoId = Uuid::uuid4()->toString();

        Database::insert('videos', [
            'id'               => $videoId,
            'author_id'        => $userId,
            's3_key'           => $upload->s3_key,
            'stitch_of'        => $originalId,
            'stitch_clip_start'=> $data['clip_start_sec'],
            'stitch_clip_end'  => $data['clip_end_sec'],
            'privacy'          => 'public',
            'status'           => 'processing',
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        return Database::fetchOne("SELECT * FROM videos WHERE id = ?", [$videoId]);
    }
}
