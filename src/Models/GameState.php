<?php
/**
 * Game State Model
 */

namespace StartupGame\Models;

class GameState extends Model
{
    protected static string $table = 'game_state';

    /**
     * Get current game state for project
     */
    public static function getForProject(int $projectId): ?array
    {
        return self::findWhere(['project_id' => $projectId]);
    }

    /**
     * Advance to next day phase
     */
    public static function advancePhase(int $projectId): ?array
    {
        $state = self::getForProject($projectId);
        if (!$state) return null;

        $phases = ['morning', 'standup', 'work', 'evening', 'end_of_day'];
        $currentIndex = array_search($state['day_phase'], $phases);

        if ($currentIndex === false || $currentIndex >= count($phases) - 1) {
            // Move to next day
            return self::advanceDay($projectId);
        }

        self::update($state['id'], [
            'day_phase' => $phases[$currentIndex + 1]
        ]);

        return self::find($state['id']);
    }

    /**
     * Advance to next day
     */
    public static function advanceDay(int $projectId): ?array
    {
        $state = self::getForProject($projectId);
        if (!$state) return null;

        self::update($state['id'], [
            'current_day' => $state['current_day'] + 1,
            'day_phase' => 'morning',
            'time_remaining' => 480,
            'standup_completed' => 0
        ]);

        return self::find($state['id']);
    }

    /**
     * Consume time from the day
     */
    public static function consumeTime(int $projectId, int $minutes): bool
    {
        $state = self::getForProject($projectId);
        if (!$state) return false;

        $newTime = max(0, $state['time_remaining'] - $minutes);
        self::update($state['id'], ['time_remaining' => $newTime]);

        return true;
    }

    /**
     * Mark standup as completed
     */
    public static function completeStandup(int $projectId): void
    {
        $state = self::getForProject($projectId);
        if ($state) {
            self::update($state['id'], [
                'standup_completed' => 1,
                'day_phase' => 'work'
            ]);
        }
    }

    /**
     * Check if it's end of day (no time left)
     */
    public static function isEndOfDay(int $projectId): bool
    {
        $state = self::getForProject($projectId);
        return $state && $state['time_remaining'] <= 0;
    }

    /**
     * Get formatted time remaining
     */
    public static function getTimeFormatted(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%d:%02d', $hours, $mins);
    }
}
