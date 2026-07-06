<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Sounds;

use AvatarTok\Core\Database;
use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\ModerationService;
use AvatarTok\Services\StorageService;
use Ramsey\Uuid\Uuid;

class SoundController
{
    public function list(Request $request): Response
    {
        $category = $request->query('category');
        $page     = max((int) $request->query('page', 1), 1);
        $limit    = min((int) $request->query('limit', 20), 50);

        $where  = "WHERE s.status = 'active'";
        $params = [];

        if ($category) {
            $where   .= " AND s.category = ?";
            $params[] = $category;
        }

        $total = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM sounds s {$where}", $params)->c;

        $sounds = Database::fetchAll(
            "SELECT s.*, u.username AS author_username FROM sounds s
             LEFT JOIN users u ON u.id = s.author_id
             {$where} ORDER BY s.video_count DESC LIMIT ? OFFSET ?",
            [...$params, $limit, ($page - 1) * $limit]
        );

        return Response::paginated($sounds, $total, $page, $limit);
    }

    public function trending(Request $request): Response
    {
        $sounds = Database::fetchAll(
            "SELECT s.*, u.username AS author_username FROM sounds s
             LEFT JOIN users u ON u.id = s.author_id
             WHERE s.status = 'active'
             ORDER BY s.video_count DESC LIMIT 30"
        );

        return Response::ok($sounds);
    }

    public function categories(Request $request): Response
    {
        $cats = Database::fetchAll(
            "SELECT category, COUNT(*) AS count FROM sounds WHERE status = 'active' GROUP BY category ORDER BY count DESC"
        );

        return Response::ok($cats);
    }

    public function search(Request $request): Response
    {
        $q     = $request->query('q', '');
        $page  = max((int) $request->query('page', 1), 1);
        $limit = min((int) $request->query('limit', 20), 50);

        $like   = "%{$q}%";
        $total  = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM sounds WHERE (title LIKE ? OR artist LIKE ?) AND status = 'active'",
            [$like, $like]
        )->c;

        $sounds = Database::fetchAll(
            "SELECT * FROM sounds WHERE (title LIKE ? OR artist LIKE ?) AND status = 'active'
             ORDER BY video_count DESC LIMIT ? OFFSET ?",
            [$like, $like, $limit, ($page - 1) * $limit]
        );

        return Response::paginated($sounds, $total, $page, $limit);
    }

    public function show(Request $request): Response
    {
        $soundId = $request->routeParam('soundId');
        $sound   = Database::fetchOne("SELECT * FROM sounds WHERE id = ? AND status = 'active'", [$soundId]);

        if (!$sound) {
            return Response::error('Sound not found.', 404);
        }

        return Response::ok($sound);
    }

    public function videosWithSound(Request $request): Response
    {
        $soundId = $request->routeParam('soundId');
        $page    = max((int) $request->query('page', 1), 1);
        $limit   = min((int) $request->query('limit', 20), 50);

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM videos WHERE sound_id = ? AND status = 'public'",
            [$soundId]
        )->c;

        $videos = Database::fetchAll(
            "SELECT v.*, u.username FROM videos v JOIN users u ON u.id = v.author_id
             WHERE v.sound_id = ? AND v.status = 'public'
             ORDER BY v.view_count DESC LIMIT ? OFFSET ?",
            [$soundId, $limit, ($page - 1) * $limit]
        );

        return Response::paginated($videos, $total, $page, $limit);
    }

    public function upload(Request $request): Response
    {
        $data = $request->validate([
            'title'    => 'required|max:200',
            'category' => 'required',
        ]);

        // In production: presigned S3 upload flow similar to videos
        $soundId = Uuid::uuid4()->toString();
        Database::insert('sounds', [
            'id'          => $soundId,
            'author_id'   => $request->user()->id,
            'title'       => $data['title'],
            'artist'      => $request->input('artist'),
            'category'    => $data['category'],
            'duration_sec'=> 0,   // Updated after processing
            's3_key'      => "sounds/{$request->user()->id}/{$soundId}.mp3",
            'status'      => 'active',
            'is_original' => 1,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        (new ModerationService())->queueVideoReview($soundId); // Reuse for audio review

        return Response::created(['sound_id' => $soundId], 'Sound uploaded.');
    }

    public function favorite(Request $request): Response
    {
        $soundId = $request->routeParam('soundId');
        $userId  = $request->user()->id;

        try {
            Database::insert('sound_favorites', ['user_id' => $userId, 'sound_id' => $soundId, 'created_at' => date('Y-m-d H:i:s')]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
        }

        return Response::ok(null, 'Sound added to favorites.');
    }

    public function unfavorite(Request $request): Response
    {
        Database::query(
            "DELETE FROM sound_favorites WHERE user_id = ? AND sound_id = ?",
            [$request->user()->id, $request->routeParam('soundId')]
        );

        return Response::noContent();
    }

    public function myFavorites(Request $request): Response
    {
        $sounds = Database::fetchAll(
            "SELECT s.* FROM sound_favorites sf JOIN sounds s ON s.id = sf.sound_id
             WHERE sf.user_id = ? ORDER BY sf.created_at DESC",
            [$request->user()->id]
        );

        return Response::ok($sounds);
    }
}
