<?php
/**
 * Project Model
 */

namespace StartupGame\Models;

class Project extends Model
{
    protected static string $table = 'projects';

    /**
     * Get active project (most recent active one)
     */
    public static function getActive(): ?array
    {
        return self::findWhere(['status' => 'active']) ?? self::findWhere(['status' => 'setup']);
    }

    /**
     * Get project with full details including teammates
     */
    public static function getWithDetails(int $id): ?array
    {
        $project = self::find($id);
        if (!$project) return null;

        $project['teammates'] = Teammate::where(['project_id' => $id]);
        $project['game_state'] = GameState::findWhere(['project_id' => $id]);
        $project['documents'] = ProjectDocument::where(['project_id' => $id], 'created_at DESC');

        return $project;
    }

    /**
     * Get phase information
     */
    public static function getPhases(): array
    {
        return [
            'planning' => ['name' => 'Planning', 'icon' => '📋', 'order' => 1],
            'design' => ['name' => 'Design', 'icon' => '🎨', 'order' => 2],
            'development' => ['name' => 'Development', 'icon' => '💻', 'order' => 3],
            'testing' => ['name' => 'Testing', 'icon' => '🧪', 'order' => 4],
            'deployment' => ['name' => 'Deployment', 'icon' => '🚀', 'order' => 5],
            'maintenance' => ['name' => 'Maintenance', 'icon' => '🔧', 'order' => 6],
        ];
    }
}
