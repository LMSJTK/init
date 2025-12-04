<?php
/**
 * Git Commit Model
 */

namespace StartupGame\Models;

use StartupGame\Database;

class GitCommit extends Model
{
    protected static string $table = 'git_commits';

    /**
     * Record a commit
     */
    public static function record(
        int $projectId,
        int $codingSessionId,
        string $commitHash,
        string $branchName,
        string $message,
        int $authorId,
        array $filesChanged,
        int $dayNumber
    ): int {
        return self::create([
            'project_id' => $projectId,
            'coding_session_id' => $codingSessionId,
            'commit_hash' => $commitHash,
            'branch_name' => $branchName,
            'commit_message' => $message,
            'author_id' => $authorId,
            'files_changed' => json_encode($filesChanged),
            'day_number' => $dayNumber
        ]);
    }

    /**
     * Get pending merges (commits not yet merged)
     */
    public static function getPendingMerges(int $projectId): array
    {
        return self::where([
            'project_id' => $projectId,
            'merged' => 0
        ], 'created_at DESC');
    }

    /**
     * Approve merge
     */
    public static function approveMerge(int $id): void
    {
        self::update($id, ['merge_approved' => 1]);
    }

    /**
     * Mark as merged
     */
    public static function markMerged(int $id): void
    {
        self::update($id, ['merged' => 1]);
    }

    /**
     * Get commits by branch
     */
    public static function getByBranch(int $projectId, string $branchName): array
    {
        return self::where([
            'project_id' => $projectId,
            'branch_name' => $branchName
        ], 'created_at ASC');
    }

    /**
     * Get commits for a day
     */
    public static function getForDay(int $projectId, int $dayNumber): array
    {
        $sql = "SELECT gc.*, t.name as author_name
                FROM git_commits gc
                LEFT JOIN teammates t ON gc.author_id = t.id
                WHERE gc.project_id = ? AND gc.day_number = ?
                ORDER BY gc.created_at ASC";
        return Database::query($sql, [$projectId, $dayNumber]);
    }
}
