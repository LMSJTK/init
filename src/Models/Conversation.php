<?php
/**
 * Conversation Model
 */

namespace StartupGame\Models;

use StartupGame\Database;

class Conversation extends Model
{
    protected static string $table = 'conversations';

    /**
     * Start a new conversation
     */
    public static function start(int $projectId, string $type, string $title, int $dayNumber): int
    {
        return self::create([
            'project_id' => $projectId,
            'conversation_type' => $type,
            'title' => $title,
            'day_number' => $dayNumber
        ]);
    }

    /**
     * End a conversation with summary
     */
    public static function end(int $id, string $summary = null): void
    {
        self::update($id, [
            'ended_at' => date('Y-m-d H:i:s'),
            'summary' => $summary
        ]);
    }

    /**
     * Get conversation with messages
     */
    public static function getWithMessages(int $id): ?array
    {
        $conversation = self::find($id);
        if (!$conversation) return null;

        $conversation['messages'] = Message::getForConversation($id);
        return $conversation;
    }

    /**
     * Get recent conversations for context
     */
    public static function getRecentForContext(int $projectId, int $limit = 5): array
    {
        $sql = "SELECT c.*, COUNT(m.id) as message_count
                FROM conversations c
                LEFT JOIN messages m ON c.id = m.conversation_id
                WHERE c.project_id = ?
                GROUP BY c.id
                ORDER BY c.started_at DESC
                LIMIT ?";
        return Database::query($sql, [$projectId, $limit]);
    }

    /**
     * Get active conversation (not ended)
     */
    public static function getActive(int $projectId): ?array
    {
        $sql = "SELECT * FROM conversations
                WHERE project_id = ? AND ended_at IS NULL
                ORDER BY started_at DESC LIMIT 1";
        return Database::queryOne($sql, [$projectId]);
    }
}
