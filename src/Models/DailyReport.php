<?php
/**
 * Daily Report Model
 */

namespace StartupGame\Models;

class DailyReport extends Model
{
    protected static string $table = 'daily_reports';

    /**
     * Get report for a specific day
     */
    public static function getForDay(int $projectId, int $dayNumber): ?array
    {
        $report = self::findWhere([
            'project_id' => $projectId,
            'day_number' => $dayNumber
        ]);

        if ($report) {
            $report['tasks_completed'] = json_decode($report['tasks_completed'], true);
            $report['tasks_in_progress'] = json_decode($report['tasks_in_progress'], true);
            $report['blockers'] = json_decode($report['blockers'], true);
            $report['next_day_priorities'] = json_decode($report['next_day_priorities'], true);
            $report['team_notes'] = json_decode($report['team_notes'], true);
        }

        return $report;
    }

    /**
     * Create or update daily report
     */
    public static function saveReport(int $projectId, int $dayNumber, array $data): int
    {
        $existing = self::findWhere([
            'project_id' => $projectId,
            'day_number' => $dayNumber
        ]);

        $reportData = [
            'project_id' => $projectId,
            'day_number' => $dayNumber,
            'summary' => $data['summary'] ?? '',
            'tasks_completed' => json_encode($data['tasks_completed'] ?? []),
            'tasks_in_progress' => json_encode($data['tasks_in_progress'] ?? []),
            'blockers' => json_encode($data['blockers'] ?? []),
            'next_day_priorities' => json_encode($data['next_day_priorities'] ?? []),
            'team_notes' => json_encode($data['team_notes'] ?? [])
        ];

        if ($existing) {
            self::update($existing['id'], $reportData);
            return $existing['id'];
        }

        return self::create($reportData);
    }

    /**
     * Get recent reports
     */
    public static function getRecent(int $projectId, int $limit = 5): array
    {
        $reports = self::where(['project_id' => $projectId], 'day_number DESC');
        return array_slice($reports, 0, $limit);
    }
}
