<?php
/**
 * Project Document Model
 */

namespace StartupGame\Models;

class ProjectDocument extends Model
{
    protected static string $table = 'project_documents';

    /**
     * Get documents by type
     */
    public static function getByType(int $projectId, string $type): array
    {
        return self::where([
            'project_id' => $projectId,
            'doc_type' => $type
        ], 'version DESC');
    }

    /**
     * Get latest version of a document type
     */
    public static function getLatest(int $projectId, string $type): ?array
    {
        $docs = self::getByType($projectId, $type);
        return $docs[0] ?? null;
    }

    /**
     * Create new version of document
     */
    public static function createVersion(
        int $projectId,
        string $type,
        string $title,
        string $content,
        int $createdBy,
        int $dayNumber
    ): int {
        $latest = self::getLatest($projectId, $type);
        $version = $latest ? $latest['version'] + 1 : 1;

        return self::create([
            'project_id' => $projectId,
            'doc_type' => $type,
            'title' => $title,
            'content' => $content,
            'version' => $version,
            'created_by' => $createdBy,
            'day_created' => $dayNumber
        ]);
    }
}
