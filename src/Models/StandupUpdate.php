<?php
/**
 * Standup Update Model
 */

namespace StartupGame\Models;

use StartupGame\Database;

class StandupUpdate extends Model
{
    protected static string $table = 'standup_updates';

    /**
     * Get all updates for a day
     */
    public static function getForDay(int $projectId, int $dayNumber): array
    {
        $sql = "SELECT su.*, t.name as teammate_name, t.avatar_color
                FROM standup_updates su
                LEFT JOIN teammates t ON su.teammate_id = t.id
                WHERE su.project_id = ? AND su.day_number = ?
                ORDER BY su.created_at ASC";
        return Database::query($sql, [$projectId, $dayNumber]);
    }

    /**
     * Record player's standup update
     */
    public static function recordPlayer(
        int $projectId,
        int $dayNumber,
        string $yesterday,
        string $today,
        string $blockers
    ): int {
        return self::create([
            'project_id' => $projectId,
            'day_number' => $dayNumber,
            'is_player' => 1,
            'yesterday_work' => $yesterday,
            'today_plan' => $today,
            'blockers' => $blockers
        ]);
    }

    /**
     * Record teammate's standup update
     */
    public static function recordTeammate(
        int $projectId,
        int $dayNumber,
        int $teammateId,
        string $yesterday,
        string $today,
        string $blockers
    ): int {
        return self::create([
            'project_id' => $projectId,
            'day_number' => $dayNumber,
            'teammate_id' => $teammateId,
            'yesterday_work' => $yesterday,
            'today_plan' => $today,
            'blockers' => $blockers
        ]);
    }
}
