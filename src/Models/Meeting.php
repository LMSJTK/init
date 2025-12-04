<?php
/**
 * Meeting Model
 */

namespace StartupGame\Models;

use StartupGame\Database;

class Meeting extends Model
{
    protected static string $table = 'meetings';

    /**
     * Schedule a meeting
     */
    public static function schedule(
        int $projectId,
        string $title,
        string $topic,
        int $dayScheduled,
        array $attendeeIds,
        int $duration = 30
    ): int {
        $meetingId = self::create([
            'project_id' => $projectId,
            'title' => $title,
            'topic' => $topic,
            'day_scheduled' => $dayScheduled,
            'duration' => $duration
        ]);

        // Add attendees
        foreach ($attendeeIds as $teammateId) {
            MeetingAttendee::create([
                'meeting_id' => $meetingId,
                'teammate_id' => $teammateId
            ]);
        }

        return $meetingId;
    }

    /**
     * Get meetings for a specific day
     */
    public static function getForDay(int $projectId, int $dayNumber): array
    {
        $sql = "SELECT m.*,
                       GROUP_CONCAT(t.name SEPARATOR ', ') as attendee_names
                FROM meetings m
                LEFT JOIN meeting_attendees ma ON m.id = ma.meeting_id
                LEFT JOIN teammates t ON ma.teammate_id = t.id
                WHERE m.project_id = ? AND m.day_scheduled = ?
                GROUP BY m.id
                ORDER BY m.created_at ASC";
        return Database::query($sql, [$projectId, $dayNumber]);
    }

    /**
     * Get meeting with attendees
     */
    public static function getWithAttendees(int $id): ?array
    {
        $meeting = self::find($id);
        if (!$meeting) return null;

        $sql = "SELECT t.* FROM teammates t
                JOIN meeting_attendees ma ON t.id = ma.teammate_id
                WHERE ma.meeting_id = ?";
        $meeting['attendees'] = Database::query($sql, [$id]);

        return $meeting;
    }

    /**
     * Start a meeting
     */
    public static function start(int $id, int $conversationId): void
    {
        self::update($id, [
            'status' => 'in_progress',
            'conversation_id' => $conversationId
        ]);
    }

    /**
     * Complete a meeting
     */
    public static function complete(int $id): void
    {
        self::update($id, ['status' => 'completed']);

        // Mark all attendees as attended
        $sql = "UPDATE meeting_attendees SET attended = 1 WHERE meeting_id = ?";
        Database::execute($sql, [$id]);
    }

    /**
     * Get next scheduled meeting
     */
    public static function getNextScheduled(int $projectId, int $currentDay): ?array
    {
        $sql = "SELECT * FROM meetings
                WHERE project_id = ?
                AND day_scheduled >= ?
                AND status = 'scheduled'
                ORDER BY day_scheduled ASC, created_at ASC
                LIMIT 1";
        return Database::queryOne($sql, [$projectId, $currentDay]);
    }
}
