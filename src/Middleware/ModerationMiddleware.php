<?php

declare(strict_types=1);

namespace AvatarTok\Middleware;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\ModerationService;

class ModerationMiddleware
{
    public function process(Request $request): ?Response
    {
        $user = $request->user();

        // Users with trust score above threshold bypass async screening
        if ($user && $user->trust_score >= 90) {
            return null;
        }

        $text = $this->extractTextContent($request);

        if ($text) {
            $result = (new ModerationService())->quickScreen($text);

            if ($result['blocked']) {
                return Response::error(
                    'Content violates community guidelines: ' . $result['reason'],
                    422
                );
            }
        }

        return null;
    }

    private function extractTextContent(Request $request): string
    {
        $body = $request->body();
        $parts = [];

        foreach (['title', 'description', 'caption', 'text', 'message', 'content'] as $field) {
            if (!empty($body[$field])) {
                $parts[] = $body[$field];
            }
        }

        return implode(' ', $parts);
    }
}
