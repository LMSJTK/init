<?php
/**
 * Coding Session Model
 */

namespace StartupGame\Models;

use StartupGame\Database;

class CodingSession extends Model
{
    protected static string $table = 'coding_sessions';

    /**
     * Start a new coding session
     */
    public static function start(
        int $projectId,
        int $teammateId,
        int $conversationId,
        ?int $taskId = null,
        ?string $branchName = null
    ): int {
        return self::create([
            'project_id' => $projectId,
            'teammate_id' => $teammateId,
            'task_id' => $taskId,
            'conversation_id' => $conversationId,
            'branch_name' => $branchName,
            'status' => 'active'
        ]);
    }

    /**
     * End a coding session
     */
    public static function end(int $id): void
    {
        self::update($id, [
            'status' => 'completed',
            'ended_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get active session for teammate
     */
    public static function getActive(int $teammateId): ?array
    {
        return self::findWhere([
            'teammate_id' => $teammateId,
            'status' => 'active'
        ]);
    }

    /**
     * Update files modified
     */
    public static function addFile(int $id, string $filepath): void
    {
        $session = self::find($id);
        if (!$session) return;

        $files = json_decode($session['files_modified'] ?? '[]', true);
        if (!in_array($filepath, $files)) {
            $files[] = $filepath;
        }
        self::update($id, ['files_modified' => json_encode($files)]);
    }

    /**
     * Add commit to session
     */
    public static function addCommit(int $id, array $commitData): void
    {
        $session = self::find($id);
        if (!$session) return;

        $commits = json_decode($session['commits'] ?? '[]', true);
        $commits[] = $commitData;
        self::update($id, ['commits' => json_encode($commits)]);
    }

    /**
     * Get session with full details
     */
    public static function getWithDetails(int $id): ?array
    {
        $sql = "SELECT cs.*, t.name as teammate_name, tk.title as task_title
                FROM coding_sessions cs
                LEFT JOIN teammates t ON cs.teammate_id = t.id
                LEFT JOIN tasks tk ON cs.task_id = tk.id
                WHERE cs.id = ?";
        return Database::queryOne($sql, [$id]);
    }
}
