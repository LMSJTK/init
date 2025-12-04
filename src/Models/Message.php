<?php
/**
 * Message Model
 */

namespace StartupGame\Models;

use StartupGame\Database;

class Message extends Model
{
    protected static string $table = 'messages';

    /**
     * Add a message to a conversation
     */
    public static function add(
        int $conversationId,
        string $senderType,
        string $content,
        ?int $senderId = null,
        string $messageType = 'text',
        ?array $metadata = null
    ): int {
        return self::create([
            'conversation_id' => $conversationId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'content' => $content,
            'message_type' => $messageType,
            'metadata' => $metadata ? json_encode($metadata) : null
        ]);
    }

    /**
     * Get messages for a conversation
     */
    public static function getForConversation(int $conversationId): array
    {
        $sql = "SELECT m.*, t.name as sender_name, t.avatar_color
                FROM messages m
                LEFT JOIN teammates t ON m.sender_id = t.id
                WHERE m.conversation_id = ?
                ORDER BY m.created_at ASC";
        return Database::query($sql, [$conversationId]);
    }

    /**
     * Get recent messages for RAG context
     */
    public static function getRecentForContext(int $projectId, int $limit = 50): array
    {
        $sql = "SELECT m.*, t.name as sender_name, c.conversation_type
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                LEFT JOIN teammates t ON m.sender_id = t.id
                WHERE c.project_id = ?
                ORDER BY m.created_at DESC
                LIMIT ?";
        return Database::query($sql, [$projectId, $limit]);
    }

    /**
     * Format messages for AI context
     */
    public static function formatForContext(array $messages): string
    {
        $formatted = [];
        foreach ($messages as $msg) {
            $sender = $msg['sender_type'] === 'player' ? 'Player' : ($msg['sender_name'] ?? 'System');
            $formatted[] = "[$sender]: {$msg['content']}";
        }
        return implode("\n", $formatted);
    }
}
