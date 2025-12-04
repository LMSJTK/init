<?php
/**
 * Task Model
 */

namespace StartupGame\Models;

use StartupGame\Database;

class Task extends Model
{
    protected static string $table = 'tasks';

    /**
     * Get tasks for project by status
     */
    public static function getByStatus(int $projectId, string $status): array
    {
        return self::where([
            'project_id' => $projectId,
            'status' => $status
        ], 'priority DESC, created_at ASC');
    }

    /**
     * Get all tasks for kanban board
     */
    public static function getKanbanBoard(int $projectId): array
    {
        return [
            'backlog' => self::getByStatus($projectId, 'backlog'),
            'todo' => self::getByStatus($projectId, 'todo'),
            'in_progress' => self::getByStatus($projectId, 'in_progress'),
            'review' => self::getByStatus($projectId, 'review'),
            'done' => self::getByStatus($projectId, 'done'),
        ];
    }

    /**
     * Get tasks assigned to a teammate
     */
    public static function getForTeammate(int $teammateId): array
    {
        return self::where(['assigned_to' => $teammateId], 'priority DESC');
    }

    /**
     * Get today's tasks
     */
    public static function getTodaysTasks(int $projectId, int $dayNumber): array
    {
        $sql = "SELECT t.*, tm.name as assignee_name, tm.avatar_color
                FROM tasks t
                LEFT JOIN teammates tm ON t.assigned_to = tm.id
                WHERE t.project_id = ?
                AND (t.day_created = ? OR t.status IN ('in_progress', 'review'))
                ORDER BY t.priority DESC, t.created_at ASC";
        return Database::query($sql, [$projectId, $dayNumber]);
    }

    /**
     * Move task to next status
     */
    public static function moveToNextStatus(int $id): ?array
    {
        $task = self::find($id);
        if (!$task) return null;

        $statusFlow = [
            'backlog' => 'todo',
            'todo' => 'in_progress',
            'in_progress' => 'review',
            'review' => 'done'
        ];

        $nextStatus = $statusFlow[$task['status']] ?? null;
        if ($nextStatus) {
            self::update($id, ['status' => $nextStatus]);
        }

        return self::find($id);
    }

    /**
     * Get task with full details
     */
    public static function getWithDetails(int $id): ?array
    {
        $sql = "SELECT t.*,
                       assigned.name as assignee_name,
                       assigned.avatar_color as assignee_color,
                       recommended.name as recommended_name
                FROM tasks t
                LEFT JOIN teammates assigned ON t.assigned_to = assigned.id
                LEFT JOIN teammates recommended ON t.recommended_assignee = recommended.id
                WHERE t.id = ?";
        return Database::queryOne($sql, [$id]);
    }

    /**
     * Create task with branch name
     */
    public static function createWithBranch(array $data): int
    {
        // Generate branch name from title
        $branchName = 'feature/' . preg_replace(
            '/[^a-z0-9]+/',
            '-',
            strtolower($data['title'])
        );
        $branchName = trim($branchName, '-');
        $data['branch_name'] = $branchName;

        return self::create($data);
    }
}
