<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Users;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\AvatarService;
use AvatarTok\Services\FacialTrackingService;

class AvatarController
{
    public function __construct(
        private readonly AvatarService        $avatarService   = new AvatarService(),
        private readonly FacialTrackingService $facialService  = new FacialTrackingService()
    ) {}

    public function getMyAvatar(Request $request): Response
    {
        $avatar = $this->avatarService->getByUserId($request->user()->id);
        return Response::ok($avatar);
    }

    public function createOrUpdate(Request $request): Response
    {
        $data = $request->validate([
            'base_model'       => 'required',
            'skin_tone'        => 'required',
            'hair_style'       => 'required',
            'hair_color'       => 'required',
            'eye_shape'        => 'required',
            'face_shape'       => 'required',
            'body_type'        => 'required',
        ]);

        $extras = $request->only([
            'accessories',
            'outfit',
            'expression_pack',
            'voice_filter',
        ]);

        $avatar = $this->avatarService->createOrUpdate(
            $request->user()->id,
            array_merge($data, $extras)
        );

        return Response::ok($avatar, 'Avatar saved.');
    }

    /**
     * Returns available preset avatar configurations from the catalog.
     */
    public function listPresets(Request $request): Response
    {
        $category = $request->query('category');
        $presets  = $this->avatarService->getPresets($category);
        return Response::ok($presets);
    }

    public function listAccessories(Request $request): Response
    {
        $filter = [
            'category' => $request->query('category'),
            'rarity'   => $request->query('rarity'),
            'unlocked' => $request->query('unlocked') === 'true',
            'user_id'  => $request->user()->id,
        ];

        $accessories = $this->avatarService->listAccessories($filter);
        return Response::ok($accessories);
    }

    /**
     * Initiates one-time facial geometry calibration for optimal avatar mapping.
     * Returns a session token for the face-tracking microservice.
     */
    public function faceCalibrate(Request $request): Response
    {
        $session = $this->facialService->startCalibrationSession($request->user()->id);

        return Response::ok([
            'session_token'   => $session['token'],
            'ws_endpoint'     => $session['ws_url'],
            'expires_at'      => $session['expires_at'],
            'landmarks_count' => 468, // MediaPipe FaceMesh landmark count
            'instructions'    => $session['calibration_steps'],
        ]);
    }

    /**
     * Issues a short-lived WebRTC/WebSocket token for real-time face tracking
     * during video recording or live streaming.
     */
    public function startFaceTrackingSession(Request $request): Response
    {
        $data = $request->validate([
            'mode'      => 'required', // 'record' | 'live'
            'stream_id' => 'max:36',
        ]);

        $session = $this->facialService->startTrackingSession(
            $request->user()->id,
            $data['mode'],
            $data['stream_id'] ?? null
        );

        return Response::ok([
            'session_token' => $session['token'],
            'ws_endpoint'   => $session['ws_url'],
            'expires_at'    => $session['expires_at'],
            'config'        => [
                'fps'               => 30,
                'landmark_model'    => 'mediapipe-facemesh-v2',
                'expression_map'    => $session['expression_map'],
                'avatar_rig_schema' => $session['rig_schema'],
            ],
        ]);
    }

    public function listExpressions(Request $request): Response
    {
        $expressions = $this->avatarService->getAvailableExpressions($request->user()->id);
        return Response::ok($expressions);
    }

    public function unlockAccessory(Request $request): Response
    {
        $data = $request->validate(['accessory_id' => 'required']);

        $result = $this->avatarService->unlockAccessory(
            $request->user()->id,
            $data['accessory_id']
        );

        if (!$result['success']) {
            return Response::error($result['message'], 402);
        }

        return Response::ok($result['accessory'], 'Accessory unlocked!');
    }
}
