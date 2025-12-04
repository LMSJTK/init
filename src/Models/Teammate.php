<?php
/**
 * Teammate (Bot) Model
 */

namespace StartupGame\Models;

use StartupGame\Database;

class Teammate extends Model
{
    protected static string $table = 'teammates';

    /**
     * Get project manager for a project
     */
    public static function getProjectManager(int $projectId): ?array
    {
        return self::findWhere([
            'project_id' => $projectId,
            'is_project_manager' => 1
        ]);
    }

    /**
     * Get player's coding assistant
     */
    public static function getPlayerAssistant(int $projectId): ?array
    {
        return self::findWhere([
            'project_id' => $projectId,
            'is_player_assistant' => 1
        ]);
    }

    /**
     * Get all regular teammates (not PM, not player assistant)
     */
    public static function getTeammates(int $projectId): array
    {
        $sql = "SELECT * FROM teammates
                WHERE project_id = ?
                AND is_project_manager = 0
                AND is_player_assistant = 0
                ORDER BY name";
        return Database::query($sql, [$projectId]);
    }

    /**
     * Get available teammates (not in meeting, not busy)
     */
    public static function getAvailable(int $projectId): array
    {
        return self::where([
            'project_id' => $projectId,
            'status' => 'available'
        ]);
    }

    /**
     * Update teammate status
     */
    public static function setStatus(int $id, string $status): void
    {
        self::update($id, ['status' => $status]);
    }

    /**
     * Assign task to teammate
     */
    public static function assignTask(int $teammateId, int $taskId): void
    {
        self::update($teammateId, [
            'current_task_id' => $taskId,
            'status' => 'busy'
        ]);
    }

    /**
     * Get teammate with their current task
     */
    public static function getWithCurrentTask(int $id): ?array
    {
        $teammate = self::find($id);
        if (!$teammate) return null;

        if ($teammate['current_task_id']) {
            $teammate['current_task'] = Task::find($teammate['current_task_id']);
        }

        return $teammate;
    }

    /**
     * Generate a random name for a teammate
     */
    public static function generateName(): string
    {
        $firstNames = [
            'Alex', 'Jordan', 'Taylor', 'Morgan', 'Casey', 'Riley', 'Quinn', 'Avery',
            'Sage', 'Phoenix', 'River', 'Skyler', 'Dakota', 'Emery', 'Finley', 'Harper',
            'Jamie', 'Kai', 'Logan', 'Parker', 'Reese', 'Rowan', 'Sydney', 'Charlie'
        ];

        $lastNames = [
            'Chen', 'Patel', 'Kim', 'Singh', 'Nakamura', 'Garcia', 'Williams', 'Brown',
            'Lee', 'Wilson', 'Moore', 'Anderson', 'Thomas', 'Jackson', 'White', 'Martin',
            'Thompson', 'Robinson', 'Clark', 'Rodriguez', 'Lewis', 'Walker', 'Hall', 'Young'
        ];

        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }
}
