<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;

/**
 * Bridges the PHP API with the face-tracking microservice (Python/FastAPI).
 *
 * Architecture:
 *   Client <—WebRTC—> Face Tracking Service (ML inference, 30fps)
 *                         |
 *                    landmark stream (468 points)
 *                         |
 *                    Avatar Rig Engine (WebGL / client-side)
 *
 * The PHP API only issues session tokens and receives summary events
 * (expressions detected, calibration complete). Real-time data flows
 * directly between client and the microservice over WebRTC/WebSocket.
 */
class FacialTrackingService
{
    private Client $http;
    private string $serviceUrl;
    private string $serviceKey;

    public function __construct()
    {
        $this->serviceUrl = rtrim($_ENV['FACE_TRACKING_URL'], '/');
        $this->serviceKey = $_ENV['FACE_TRACKING_KEY'];

        $this->http = new Client([
            'base_uri' => $this->serviceUrl,
            'timeout'  => 5.0,
            'headers'  => ['X-Service-Key' => $this->serviceKey],
        ]);
    }

    public function startCalibrationSession(string $userId): array
    {
        $sessionId = Uuid::uuid4()->toString();

        $response = $this->http->post('/sessions/calibrate', [
            'json' => [
                'session_id' => $sessionId,
                'user_id'    => $userId,
                'ttl'        => 300, // 5 min to complete calibration
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'token'              => $data['session_token'],
            'ws_url'             => $data['ws_endpoint'],
            'expires_at'         => date('c', time() + 300),
            'calibration_steps'  => [
                ['step' => 1, 'instruction' => 'Look straight at the camera. Keep your face centered.'],
                ['step' => 2, 'instruction' => 'Slowly turn your head left, then right.'],
                ['step' => 3, 'instruction' => 'Raise your eyebrows. Then smile.'],
                ['step' => 4, 'instruction' => 'Open your mouth wide.'],
                ['step' => 5, 'instruction' => 'Calibration complete! Your avatar is ready.'],
            ],
        ];
    }

    public function startTrackingSession(string $userId, string $mode, ?string $streamId): array
    {
        $sessionId = Uuid::uuid4()->toString();

        $response = $this->http->post('/sessions/track', [
            'json' => [
                'session_id' => $sessionId,
                'user_id'    => $userId,
                'mode'       => $mode,
                'stream_id'  => $streamId,
                'ttl'        => $mode === 'live' ? 14400 : 600, // 4h live, 10m record
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Load the user's saved avatar rig for expression mapping
        $rig = $this->getAvatarRigSchema($userId);

        return [
            'token'          => $data['session_token'],
            'ws_url'         => $data['ws_endpoint'],
            'expires_at'     => $data['expires_at'],
            'expression_map' => $this->buildExpressionMap(),
            'rig_schema'     => $rig,
        ];
    }

    /**
     * Maps MediaPipe expression probabilities to avatar blend shape targets.
     * Blend shape names follow the ARKit standard for cross-platform compatibility.
     */
    private function buildExpressionMap(): array
    {
        return [
            'jawOpen'         => ['mp_landmark_key' => 'mouth_open',      'scale' => 1.0],
            'mouthSmileLeft'  => ['mp_landmark_key' => 'smile_left',       'scale' => 0.9],
            'mouthSmileRight' => ['mp_landmark_key' => 'smile_right',      'scale' => 0.9],
            'eyeBlinkLeft'    => ['mp_landmark_key' => 'left_eye_blink',   'scale' => 1.0],
            'eyeBlinkRight'   => ['mp_landmark_key' => 'right_eye_blink',  'scale' => 1.0],
            'browRaiseLeft'   => ['mp_landmark_key' => 'left_brow_raise',  'scale' => 0.8],
            'browRaiseRight'  => ['mp_landmark_key' => 'right_brow_raise', 'scale' => 0.8],
            'browFurrow'      => ['mp_landmark_key' => 'brow_furrow',      'scale' => 0.7],
            'noseSneer'       => ['mp_landmark_key' => 'nose_sneer',       'scale' => 0.6],
            'cheekPuff'       => ['mp_landmark_key' => 'cheek_puff',       'scale' => 0.5],
            'headRotX'        => ['mp_landmark_key' => 'head_pitch',       'scale' => 1.0, 'type' => 'rotation'],
            'headRotY'        => ['mp_landmark_key' => 'head_yaw',         'scale' => 1.0, 'type' => 'rotation'],
            'headRotZ'        => ['mp_landmark_key' => 'head_roll',        'scale' => 1.0, 'type' => 'rotation'],
        ];
    }

    private function getAvatarRigSchema(string $userId): array
    {
        // Fetch avatar model type to return the correct bone rig schema
        $avatar = (new AvatarService())->getByUserId($userId);

        return [
            'model_type'   => $avatar?->base_model ?? 'default_v1',
            'bone_count'   => 78,
            'blend_shapes' => 52,
            'schema_url'   => "{$_ENV['AWS_CLOUDFRONT_URL']}/rigs/{$avatar?->base_model ?? 'default_v1'}/schema.json",
        ];
    }
}
