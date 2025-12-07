<?php
/**
 * API Controller - Handles all API endpoints
 */

namespace StartupGame\Controllers;

use StartupGame\Services\{GameService, AIService, GitService};
use StartupGame\Models\{
    Project, Teammate, Task, GameState, Conversation,
    Message, Meeting, Setting, DailyReport, ProjectDocument,
    CodingSession, GitCommit
};

class ApiController
{
    private GameService $game;
    private AIService $ai;
    private GitService $git;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->ai = new AIService($config);
        $this->git = new GitService($config);
        $this->game = new GameService($config, $this->ai, $this->git);
    }

    /**
     * Route API request
     */
    public function handle(string $method, string $path, array $data): array
    {
        try {
            return match(true) {
                // Project endpoints
                $path === '/api/project' && $method === 'GET' => $this->getProject(),
                $path === '/api/project' && $method === 'POST' => $this->createProject($data),
                $path === '/api/project/activate' && $method === 'POST' => $this->activateProject($data),

                // Team endpoints
                $path === '/api/team' && $method === 'GET' => $this->getTeam($data),
                $path === '/api/team/generate' && $method === 'POST' => $this->generateTeam($data),

                // Game state endpoints
                $path === '/api/game/state' && $method === 'GET' => $this->getGameState($data),
                $path === '/api/game/day/start' && $method === 'POST' => $this->startDay($data),
                $path === '/api/game/day/end' && $method === 'POST' => $this->endDay($data),

                // Standup endpoints
                $path === '/api/standup/start' && $method === 'POST' => $this->startStandup($data),
                $path === '/api/standup/update' && $method === 'POST' => $this->submitStandupUpdate($data),

                // Conversation endpoints
                $path === '/api/conversation/one-on-one/start' && $method === 'POST' => $this->startOneOnOne($data),
                $path === '/api/conversation/one-on-one/message' && $method === 'POST' => $this->sendOneOnOneMessage($data),
                $path === '/api/conversation/one-on-one/end' && $method === 'POST' => $this->endOneOnOne($data),

                // Coding session endpoints
                $path === '/api/coding/start' && $method === 'POST' => $this->startCoding($data),
                $path === '/api/coding/message' && $method === 'POST' => $this->sendCodingMessage($data),
                $path === '/api/coding/commit' && $method === 'POST' => $this->commitCode($data),
                $path === '/api/coding/end' && $method === 'POST' => $this->endCoding($data),

                // Meeting endpoints
                $path === '/api/meeting/start' && $method === 'POST' => $this->startMeeting($data),
                $path === '/api/meeting/message' && $method === 'POST' => $this->sendMeetingMessage($data),
                $path === '/api/meeting/end' && $method === 'POST' => $this->endMeeting($data),
                $path === '/api/meetings' && $method === 'GET' => $this->getMeetings($data),

                // Whiteboard endpoints
                $path === '/api/whiteboard/start' && $method === 'POST' => $this->startWhiteboard($data),
                $path === '/api/whiteboard/message' && $method === 'POST' => $this->sendWhiteboardMessage($data),
                $path === '/api/whiteboard/end' && $method === 'POST' => $this->endWhiteboard($data),

                // Task endpoints
                $path === '/api/tasks' && $method === 'GET' => $this->getTasks($data),
                $path === '/api/tasks/kanban' && $method === 'GET' => $this->getKanban($data),
                $path === '/api/task/assign' && $method === 'POST' => $this->assignTask($data),
                $path === '/api/task/move' && $method === 'POST' => $this->moveTask($data),

                // Team work activation endpoints
                $path === '/api/team/activate' && $method === 'POST' => $this->activateTeamWork($data),

                // Git endpoints
                $path === '/api/git/status' && $method === 'GET' => $this->getGitStatus($data),
                $path === '/api/git/branches' && $method === 'GET' => $this->getGitBranches($data),
                $path === '/api/git/files' && $method === 'GET' => $this->getGitFiles($data),
                $path === '/api/git/file' && $method === 'GET' => $this->getGitFile($data),
                $path === '/api/git/merge' && $method === 'POST' => $this->mergeGit($data),
                $path === '/api/git/link' && $method === 'POST' => $this->linkGit($data),
                $path === '/api/git/push' && $method === 'POST' => $this->pushGit($data),
                $path === '/api/git/pending-merges' && $method === 'GET' => $this->getPendingMerges($data),

                // Settings endpoints
                $path === '/api/settings' && $method === 'GET' => $this->getSettings(),
                $path === '/api/settings' && $method === 'POST' => $this->saveSettings($data),
                $path === '/api/settings/api-keys' && $method === 'POST' => $this->saveApiKeys($data),

                // Documents endpoints
                $path === '/api/documents' && $method === 'GET' => $this->getDocuments($data),
                $path === '/api/document' && $method === 'GET' => $this->getDocument($data),

                // Reports endpoints
                $path === '/api/reports' && $method === 'GET' => $this->getReports($data),
                $path === '/api/report' && $method === 'GET' => $this->getReport($data),

                // Conversation history
                $path === '/api/conversation' && $method === 'GET' => $this->getConversation($data),

                default => ['error' => 'Not found', 'status' => 404]
            };
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'status' => 500];
        }
    }

    // ========== Project Methods ==========

    private function getProject(): array
    {
        $project = Project::getActive();
        if (!$project) {
            return ['project' => null, 'needs_setup' => true];
        }
        return ['project' => Project::getWithDetails($project['id'])];
    }

    private function createProject(array $data): array
    {
        $result = $this->game->createProject(
            $data['name'] ?? 'New Project',
            $data['description'] ?? '',
            $data['vision'] ?? '',
            $data['pm_model'] ?? 'claude-sonnet-4.5'
        );
        return $result;
    }

    private function activateProject(array $data): array
    {
        $projectId = $data['project_id'] ?? 0;
        Project::update($projectId, ['status' => 'active']);
        return ['success' => true];
    }

    // ========== Team Methods ==========

    private function getTeam(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return [
            'pm' => Teammate::getProjectManager($projectId),
            'assistant' => Teammate::getPlayerAssistant($projectId),
            'teammates' => Teammate::getTeammates($projectId)
        ];
    }

    private function generateTeam(array $data): array
    {
        $projectId = $data['project_id'];
        $teammates = $this->game->generateTeammates($projectId, $data['team_config']);
        return ['teammates' => $teammates];
    }

    // ========== Game State Methods ==========

    private function getGameState(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $state = GameState::getForProject($projectId);
        return [
            'state' => $state,
            'time_formatted' => GameState::getTimeFormatted($state['time_remaining'] ?? 0)
        ];
    }

    private function startDay(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return $this->game->startDay($projectId);
    }

    private function endDay(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return $this->game->endDay($projectId);
    }

    // ========== Standup Methods ==========

    private function startStandup(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $conversationId = $this->game->runStandup($projectId);
        return ['conversation_id' => $conversationId];
    }

    private function submitStandupUpdate(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return $this->game->processStandupUpdate(
            $projectId,
            $data['conversation_id'],
            $data['yesterday'] ?? '',
            $data['today'] ?? '',
            $data['blockers'] ?? ''
        );
    }

    // ========== One-on-One Methods ==========

    private function startOneOnOne(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $conversationId = $this->game->startOneOnOne($projectId, $data['teammate_id']);
        return [
            'conversation_id' => $conversationId,
            'teammate' => Teammate::find($data['teammate_id'])
        ];
    }

    private function sendOneOnOneMessage(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return $this->game->sendOneOnOneMessage(
            $projectId,
            $data['conversation_id'],
            $data['teammate_id'],
            $data['message']
        );
    }

    private function endOneOnOne(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $this->game->endOneOnOne($projectId, $data['conversation_id'], $data['teammate_id']);
        return ['success' => true];
    }

    // ========== Coding Session Methods ==========

    private function startCoding(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return $this->game->startCodingSession($projectId, $data['task_id'] ?? null);
    }

    private function sendCodingMessage(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return $this->game->sendCodingMessage(
            $projectId,
            $data['session_id'],
            $data['conversation_id'],
            $data['message']
        );
    }

    private function commitCode(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return $this->game->commitCodingSession(
            $projectId,
            $data['session_id'],
            $data['message']
        );
    }

    private function endCoding(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $this->game->endCodingSession($projectId, $data['session_id']);
        return ['success' => true];
    }

    // ========== Meeting Methods ==========

    private function startMeeting(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return $this->game->startMeeting($projectId, $data['meeting_id']);
    }

    private function sendMeetingMessage(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return $this->game->sendMeetingMessage(
            $projectId,
            $data['meeting_id'],
            $data['conversation_id'],
            $data['message']
        );
    }

    private function endMeeting(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $this->game->endMeeting($projectId, $data['meeting_id']);
        return ['success' => true];
    }

    private function getMeetings(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $state = GameState::getForProject($projectId);
        return ['meetings' => Meeting::getForDay($projectId, $state['current_day'])];
    }

    // ========== Whiteboard Methods ==========

    private function startWhiteboard(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $conversationId = $this->game->startWhiteboard(
            $projectId,
            $data['teammate_id'],
            $data['topic']
        );
        return [
            'conversation_id' => $conversationId,
            'teammate' => Teammate::find($data['teammate_id'])
        ];
    }

    private function sendWhiteboardMessage(array $data): array
    {
        // Whiteboard uses same flow as one-on-one for messages
        return $this->sendOneOnOneMessage($data);
    }

    private function endWhiteboard(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $docId = $this->game->endWhiteboard(
            $projectId,
            $data['conversation_id'],
            $data['teammate_id']
        );
        return ['success' => true, 'document_id' => $docId];
    }

    // ========== Task Methods ==========

    private function getTasks(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $state = GameState::getForProject($projectId);
        return ['tasks' => Task::getTodaysTasks($projectId, $state['current_day'])];
    }

    private function getKanban(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return ['kanban' => Task::getKanbanBoard($projectId)];
    }

    private function assignTask(array $data): array
    {
        Task::update($data['task_id'], ['assigned_to' => $data['teammate_id']]);
        Teammate::assignTask($data['teammate_id'], $data['task_id']);
        return ['success' => true];
    }

    private function moveTask(array $data): array
    {
        $task = Task::moveToNextStatus($data['task_id']);
        return ['task' => $task];
    }

    // ========== Team Work Activation Methods ==========

    /**
     * Activate teammates to work on their assigned tasks
     * This triggers autonomous coding sessions for teammates with pending tasks
     */
    private function activateTeamWork(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $activatedSessions = $this->game->checkAndActivateIdleTeammates($projectId);
        return [
            'success' => true,
            'activated_count' => count($activatedSessions),
            'sessions' => array_map(function($session) {
                return [
                    'teammate' => $session['teammate']['name'] ?? 'Unknown',
                    'task' => $session['task']['title'] ?? 'Unknown',
                    'branch' => $session['branch'] ?? null,
                    'files_written' => $session['work_result']['files_written'] ?? []
                ];
            }, $activatedSessions)
        ];
    }

    // ========== Git Methods ==========

    private function getGitStatus(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return ['status' => $this->git->getStatus($projectId)];
    }

    private function getGitBranches(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return ['branches' => $this->git->getBranches($projectId)];
    }

    private function getGitFiles(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return ['files' => $this->git->listFiles($projectId, $data['path'] ?? '')];
    }

    private function getGitFile(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $content = $this->git->readFile($projectId, $data['path']);
        return ['content' => $content];
    }

    private function mergeGit(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;

        // First approve the merge
        if (isset($data['commit_id'])) {
            GitCommit::approveMerge($data['commit_id']);
        }

        // Then merge
        $result = $this->git->mergeBranch($projectId, $data['branch']);

        if ($result['success'] && isset($data['commit_id'])) {
            GitCommit::markMerged($data['commit_id']);
        }

        return $result;
    }

    private function linkGit(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        $result = $this->git->linkRemote($projectId, $data['remote_url']);

        if ($result['success']) {
            Project::update($projectId, [
                'git_remote_url' => $data['remote_url'],
                'github_linked' => 1
            ]);
        }

        return $result;
    }

    private function pushGit(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return $this->git->push($projectId, $data['branch'] ?? null);
    }

    private function getPendingMerges(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return ['merges' => GitCommit::getPendingMerges($projectId)];
    }

    // ========== Settings Methods ==========

    private function getSettings(): array
    {
        return [
            'gemini_configured' => Setting::hasApiKey('gemini-3'),
            'openai_configured' => Setting::hasApiKey('chatgpt-5.1'),
            'anthropic_configured' => Setting::hasApiKey('claude-sonnet-4.5'),
            'github_token_configured' => !empty(Setting::get('github_token'))
        ];
    }

    private function saveSettings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (!str_contains($key, 'api_key') && !str_contains($key, 'token')) {
                Setting::set($key, $value);
            }
        }
        return ['success' => true];
    }

    private function saveApiKeys(array $data): array
    {
        if (!empty($data['gemini_api_key'])) {
            Setting::set('gemini_api_key', $data['gemini_api_key'], true);
        }
        if (!empty($data['openai_api_key'])) {
            Setting::set('openai_api_key', $data['openai_api_key'], true);
        }
        if (!empty($data['anthropic_api_key'])) {
            Setting::set('anthropic_api_key', $data['anthropic_api_key'], true);
        }
        if (!empty($data['github_token'])) {
            Setting::set('github_token', $data['github_token'], true);
        }
        return ['success' => true];
    }

    // ========== Document Methods ==========

    private function getDocuments(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return ['documents' => ProjectDocument::where(['project_id' => $projectId], 'created_at DESC')];
    }

    private function getDocument(array $data): array
    {
        return ['document' => ProjectDocument::find($data['id'])];
    }

    // ========== Report Methods ==========

    private function getReports(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return ['reports' => DailyReport::getRecent($projectId, 10)];
    }

    private function getReport(array $data): array
    {
        $projectId = $data['project_id'] ?? Project::getActive()['id'] ?? 0;
        return ['report' => DailyReport::getForDay($projectId, $data['day'])];
    }

    // ========== Conversation Methods ==========

    private function getConversation(array $data): array
    {
        return ['conversation' => Conversation::getWithMessages($data['id'])];
    }
}
