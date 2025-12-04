<?php
/**
 * Context Chunk Model for RAG
 */

namespace StartupGame\Models;

use StartupGame\Database;

class ContextChunk extends Model
{
    protected static string $table = 'context_chunks';

    /**
     * Add a context chunk
     */
    public static function addChunk(
        int $projectId,
        string $type,
        string $content,
        array $metadata = []
    ): int {
        return self::create([
            'project_id' => $projectId,
            'chunk_type' => $type,
            'content' => $content,
            'metadata' => json_encode($metadata)
        ]);
    }

    /**
     * Get relevant context for a query (basic keyword search)
     * In a production system, this would use vector similarity
     */
    public static function getRelevant(int $projectId, string $query, int $limit = 10): array
    {
        // Extract keywords from query
        $keywords = array_filter(
            explode(' ', strtolower($query)),
            fn($word) => strlen($word) > 3
        );

        if (empty($keywords)) {
            // Return most recent if no keywords
            return self::where(['project_id' => $projectId], 'created_at DESC');
        }

        // Build search query
        $whereClauses = [];
        $params = [$projectId];

        foreach ($keywords as $keyword) {
            $whereClauses[] = "LOWER(content) LIKE ?";
            $params[] = "%{$keyword}%";
        }

        $sql = sprintf(
            "SELECT *, (%s) as match_count FROM context_chunks
             WHERE project_id = ? AND (%s)
             ORDER BY match_count DESC, relevance_score DESC, created_at DESC
             LIMIT %d",
            implode(' + ', array_map(fn($w) => "(LOWER(content) LIKE '%$w%')", $keywords)),
            implode(' OR ', $whereClauses),
            $limit
        );

        return Database::query($sql, $params);
    }

    /**
     * Get context by type
     */
    public static function getByType(int $projectId, string $type, int $limit = 20): array
    {
        $sql = "SELECT * FROM context_chunks
                WHERE project_id = ? AND chunk_type = ?
                ORDER BY created_at DESC
                LIMIT ?";
        return Database::query($sql, [$projectId, $type, $limit]);
    }

    /**
     * Update relevance score (for learning from usage)
     */
    public static function updateRelevance(int $id, float $score): void
    {
        self::update($id, ['relevance_score' => $score]);
    }

    /**
     * Build context string from chunks
     */
    public static function buildContextString(array $chunks): string
    {
        $parts = [];
        foreach ($chunks as $chunk) {
            $type = ucfirst(str_replace('_', ' ', $chunk['chunk_type']));
            $parts[] = "[$type]\n{$chunk['content']}";
        }
        return implode("\n\n---\n\n", $parts);
    }
}
