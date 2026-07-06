<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Chat;

use AvatarTok\Core\Database;
use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\CacheService;
use Ramsey\Uuid\Uuid;

class ChatController
{
    public function listConversations(Request $request): Response
    {
        $userId = $request->user()->id;

        $convs = Database::fetchAll(
            "SELECT c.id, c.type, c.name,
                    m.text AS last_message, m.created_at AS last_message_at,
                    (SELECT COUNT(*) FROM messages msg
                     WHERE msg.conversation_id = c.id
                     AND msg.created_at > COALESCE(cm.last_read_at, '1970-01-01')
                     AND msg.sender_id != ?) AS unread_count
             FROM conversations c
             JOIN conversation_members cm ON cm.conversation_id = c.id AND cm.user_id = ?
             LEFT JOIN messages m ON m.id = (
                 SELECT id FROM messages WHERE conversation_id = c.id AND deleted_at IS NULL
                 ORDER BY created_at DESC LIMIT 1
             )
             ORDER BY COALESCE(m.created_at, c.created_at) DESC",
            [$userId, $userId]
        );

        return Response::ok($convs);
    }

    public function getConversation(Request $request): Response
    {
        $convId = $request->routeParam('convId');
        $userId = $request->user()->id;

        $member = Database::fetchOne(
            "SELECT * FROM conversation_members WHERE conversation_id = ? AND user_id = ?",
            [$convId, $userId]
        );

        if (!$member) {
            return Response::error('Conversation not found.', 404);
        }

        $conv = Database::fetchOne("SELECT * FROM conversations WHERE id = ?", [$convId]);
        $members = Database::fetchAll(
            "SELECT u.id, u.username, u.display_name, av.avatar_url
             FROM conversation_members cm JOIN users u ON u.id = cm.user_id
             LEFT JOIN avatars av ON av.user_id = u.id
             WHERE cm.conversation_id = ?",
            [$convId]
        );

        return Response::ok(['conversation' => $conv, 'members' => $members]);
    }

    public function createConversation(Request $request): Response
    {
        $data      = $request->validate(['participant_id' => 'required']);
        $myId      = $request->user()->id;
        $otherId   = $data['participant_id'];

        // Check for existing DM
        $existing = Database::fetchOne(
            "SELECT c.id FROM conversations c
             JOIN conversation_members cm1 ON cm1.conversation_id = c.id AND cm1.user_id = ?
             JOIN conversation_members cm2 ON cm2.conversation_id = c.id AND cm2.user_id = ?
             WHERE c.type = 'direct' LIMIT 1",
            [$myId, $otherId]
        );

        if ($existing) {
            return Response::ok(['conversation_id' => $existing->id], 'Conversation already exists.');
        }

        $convId = Uuid::uuid4()->toString();

        Database::beginTransaction();
        try {
            Database::insert('conversations', [
                'id'         => $convId,
                'type'       => 'direct',
                'created_by' => $myId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            foreach ([$myId, $otherId] as $uid) {
                Database::insert('conversation_members', [
                    'conversation_id' => $convId,
                    'user_id'         => $uid,
                    'joined_at'       => date('Y-m-d H:i:s'),
                ]);
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return Response::created(['conversation_id' => $convId]);
    }

    public function listMessages(Request $request): Response
    {
        $convId = $request->routeParam('convId');
        $userId = $request->user()->id;

        if (!Database::fetchOne("SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?", [$convId, $userId])) {
            return Response::error('Conversation not found.', 404);
        }

        $cursor = $request->query('cursor');
        $limit  = min((int) $request->query('limit', 30), 100);

        $where  = "WHERE m.conversation_id = ? AND m.deleted_at IS NULL";
        $params = [$convId];

        if ($cursor) {
            $where   .= " AND m.created_at < ?";
            $params[] = base64_decode($cursor);
        }

        $messages = Database::fetchAll(
            "SELECT m.*, u.username, av.avatar_url
             FROM messages m JOIN users u ON u.id = m.sender_id
             LEFT JOIN avatars av ON av.user_id = u.id
             {$where} ORDER BY m.created_at DESC LIMIT {$limit}",
            $params
        );

        $messages    = array_reverse($messages);
        $nextCursor  = count($messages) === $limit
            ? base64_encode($messages[0]->created_at ?? '')
            : null;

        return Response::ok(['messages' => $messages, 'next_cursor' => $nextCursor]);
    }

    public function sendMessage(Request $request): Response
    {
        $convId = $request->routeParam('convId');
        $userId = $request->user()->id;

        if (!Database::fetchOne("SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?", [$convId, $userId])) {
            return Response::error('Conversation not found.', 404);
        }

        $data = $request->validate(['text' => 'required|max:2000']);

        $msgId = Uuid::uuid4()->toString();
        Database::insert('messages', [
            'id'              => $msgId,
            'conversation_id' => $convId,
            'sender_id'       => $userId,
            'type'            => 'text',
            'text'            => $data['text'],
            'reply_to_id'     => $request->input('reply_to_id'),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        // Publish to WebSocket channel for real-time delivery
        (new CacheService())->publish("chat:{$convId}", [
            'type'       => 'new_message',
            'message_id' => $msgId,
            'sender_id'  => $userId,
            'text'       => $data['text'],
        ]);

        return Response::created(
            Database::fetchOne("SELECT * FROM messages WHERE id = ?", [$msgId])
        );
    }

    public function deleteMessage(Request $request): Response
    {
        $messageId = $request->routeParam('messageId');

        $deleted = Database::query(
            "UPDATE messages SET deleted_at = NOW() WHERE id = ? AND sender_id = ?",
            [$messageId, $request->user()->id]
        )->rowCount();

        if (!$deleted) {
            return Response::error('Message not found.', 404);
        }

        return Response::noContent();
    }

    public function reactToMessage(Request $request): Response
    {
        $messageId = $request->routeParam('messageId');
        $data      = $request->validate(['emoji' => 'required|max:10']);

        $message   = Database::fetchOne("SELECT reactions FROM messages WHERE id = ?", [$messageId]);
        $reactions = json_decode($message->reactions ?? '{}', true);

        $emoji  = $data['emoji'];
        $userId = $request->user()->id;

        if (!isset($reactions[$emoji])) {
            $reactions[$emoji] = [];
        }

        if (!in_array($userId, $reactions[$emoji], true)) {
            $reactions[$emoji][] = $userId;
        }

        Database::update('messages', ['reactions' => json_encode($reactions)], ['id' => $messageId]);

        return Response::ok($reactions);
    }

    public function markAsRead(Request $request): Response
    {
        $convId = $request->routeParam('convId');
        $userId = $request->user()->id;

        Database::update('conversation_members', ['last_read_at' => date('Y-m-d H:i:s')],
            ['conversation_id' => $convId, 'user_id' => $userId]);

        return Response::noContent();
    }

    public function blockConversation(Request $request): Response
    {
        // Soft block: remove from conversation members
        Database::query(
            "DELETE FROM conversation_members WHERE conversation_id = ? AND user_id = ?",
            [$request->routeParam('convId'), $request->user()->id]
        );

        return Response::noContent();
    }

    public function wsAuthToken(Request $request): Response
    {
        $userId  = $request->user()->id;
        $payload = json_encode(['user_id' => $userId, 'exp' => time() + 3600]);
        $token   = base64_encode(hash_hmac('sha256', $payload, $_ENV['WS_SECRET']) . '.' . $payload);

        return Response::ok([
            'ws_token'   => $token,
            'ws_endpoint'=> $_ENV['WS_HOST'] . '/chat',
        ]);
    }
}
