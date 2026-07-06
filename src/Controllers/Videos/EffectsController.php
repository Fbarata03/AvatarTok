<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Videos;

use AvatarTok\Core\Database;
use AvatarTok\Core\Request;
use AvatarTok\Core\Response;

class EffectsController
{
    public function list(Request $request): Response
    {
        $category = $request->query('category');
        $where    = $category ? "WHERE category = ? AND active = 1" : "WHERE active = 1";
        $params   = $category ? [$category] : [];

        $effects = Database::fetchAll(
            "SELECT * FROM effects {$where} ORDER BY usage_count DESC LIMIT 100",
            $params
        );

        return Response::ok($effects);
    }

    public function categories(Request $request): Response
    {
        $cats = Database::fetchAll(
            "SELECT category, COUNT(*) AS count FROM effects WHERE active = 1 GROUP BY category ORDER BY count DESC"
        );

        return Response::ok($cats);
    }

    public function trending(Request $request): Response
    {
        $effects = Database::fetchAll(
            "SELECT * FROM effects WHERE active = 1 ORDER BY usage_count DESC LIMIT 20"
        );

        return Response::ok($effects);
    }

    public function show(Request $request): Response
    {
        $effectId = $request->routeParam('effectId');
        $effect   = Database::fetchOne("SELECT * FROM effects WHERE id = ? AND active = 1", [$effectId]);

        if (!$effect) {
            return Response::error('Effect not found.', 404);
        }

        return Response::ok($effect);
    }

    public function apply(Request $request): Response
    {
        $effectId = $request->routeParam('effectId');
        $effect   = Database::fetchOne("SELECT * FROM effects WHERE id = ?", [$effectId]);

        if (!$effect) {
            return Response::error('Effect not found.', 404);
        }

        Database::query("UPDATE effects SET usage_count = usage_count + 1 WHERE id = ?", [$effectId]);

        return Response::ok([
            'effect_id'  => $effect->id,
            'shader_url' => $effect->shader_url,
            'asset_url'  => $effect->asset_url,
            'config'     => [
                'requires_face_track' => (bool) $effect->requires_face_track,
                'render_mode'         => $effect->requires_face_track ? 'avatar_overlay' : 'post_process',
            ],
        ]);
    }

    public function avatarFilters(Request $request): Response
    {
        $filters = Database::fetchAll(
            "SELECT * FROM effects WHERE category = 'avatar' AND active = 1 ORDER BY usage_count DESC"
        );

        return Response::ok($filters);
    }

    public function preview(Request $request): Response
    {
        $data = $request->validate([
            'effect_id' => 'required',
            'frame_url' => 'required',
        ]);

        // In production: calls the media processing microservice for a preview render
        return Response::ok([
            'preview_url' => "https://cdn.avatartok.com/effect-previews/{$data['effect_id']}/preview.jpg",
            'expires_at'  => date('c', strtotime('+5 minutes')),
        ]);
    }
}
