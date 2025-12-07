<?php
/**
 * Game Service - Core game mechanics and flow management
 */

namespace StartupGame\Services;

use StartupGame\Models\{
    Project, Teammate, Task, GameState, Conversation,
    Message, Meeting, DailyReport, StandupUpdate,
    ProjectDocument, CodingSession, ContextChunk
};

class GameService
{
    private AIService $ai;
    private GitService $git;
    private array $config;

    public function __construct(array $config, AIService $ai, GitService $git)
    {
        $this->config = $config;
        $this->ai = $ai;
        $this->git = $git;
    }

    /**
     * Create a new project with initial setup
     */
    public function createProject(
        string $name,
        string $description,
        string $vision,
        string $pmModel
    ): array {
        // Create project
        $projectId = Project::create([
            'name' => $name,
            'description' => $description,
            'vision' => $vision,
            'status' => 'setup'
        ]);

        // Initialize game state
        GameState::create([
            'project_id' => $projectId,
            'current_day' => 1,
            'day_phase' => 'morning',
            'time_remaining' => $this->config['game']['workday_minutes']
        ]);

        // Create Project Manager
        Teammate::create([
            'project_id' => $projectId,
            'name' => 'Jordan Chen',
            'role' => 'Project Manager',
            'specialty' => 'Project planning, team coordination, stakeholder management',
            'ai_model' => $pmModel,
            'personality' => 'Organized, supportive, and focused on team success. Communicates clearly and helps remove blockers.',
            'avatar_color' => '#8e44ad',
            'is_project_manager' => 1,
            'desk_position_x' => 400,
            'desk_position_y' => 100
        ]);

        // Initialize git repository
        $this->git->initRepository($projectId, $name);

        // Store initial context
        ContextChunk::addChunk($projectId, 'decision', "Project created: $name\n\nDescription: $description\n\nVision: $vision");

        return [
            'project_id' => $projectId,
            'success' => true
        ];
    }

    /**
     * Generate teammates based on project needs
     */
    public function generateTeammates(int $projectId, array $teamConfig): array
    {
        $teammates = [];
        $positions = [
            ['x' => 100, 'y' => 200],
            ['x' => 100, 'y' => 350],
            ['x' => 700, 'y' => 200],
            ['x' => 700, 'y' => 350],
            ['x' => 400, 'y' => 450]
        ];

        $posIndex = 0;
        foreach ($teamConfig as $role => $model) {
            if ($role === 'assistant') {
                // Player's coding assistant
                $id = Teammate::create([
                    'project_id' => $projectId,
                    'name' => 'Dev Assistant',
                    'role' => 'Coding Assistant',
                    'specialty' => 'Pair programming, code review, implementation help',
                    'ai_model' => $model,
                    'personality' => 'Helpful, detail-oriented, and patient. Explains code clearly.',
                    'avatar_color' => '#27ae60',
                    'is_player_assistant' => 1,
                    'desk_position_x' => 400,
                    'desk_position_y' => 300
                ]);
            } else {
                $roleConfig = $this->config['roles'][$role] ?? null;
                if (!$roleConfig) continue;

                $pos = $positions[$posIndex % count($positions)];
                $posIndex++;

                $id = Teammate::create([
                    'project_id' => $projectId,
                    'name' => Teammate::generateName(),
                    'role' => $roleConfig['name'],
                    'specialty' => $roleConfig['specialty'],
                    'ai_model' => $model,
                    'personality' => $this->generatePersonality(),
                    'avatar_color' => $roleConfig['color'],
                    'desk_position_x' => $pos['x'],
                    'desk_position_y' => $pos['y']
                ]);
            }

            $teammates[] = Teammate::find($id);
        }

        return $teammates;
    }

    /**
     * Generate a random personality for a teammate
     */
    private function generatePersonality(): string
    {
        $traits = [
            ['Analytical', 'Creative', 'Pragmatic', 'Innovative'],
            ['Detail-oriented', 'Big-picture thinker', 'Process-driven', 'Results-focused'],
            ['Collaborative', 'Independent', 'Mentoring', 'Learning-oriented'],
            ['Calm under pressure', 'Energetic', 'Methodical', 'Adaptable']
        ];

        $personality = [];
        foreach ($traits as $traitGroup) {
            $personality[] = $traitGroup[array_rand($traitGroup)];
        }

        return implode(', ', $personality) . '. ' .
               'Communicates ' . ['clearly', 'concisely', 'thoroughly', 'enthusiastically'][array_rand([0,1,2,3])] . '.';
    }

    /**
     * Start a new day
     */
    public function startDay(int $projectId): array
    {
        $state = GameState::getForProject($projectId);
        $project = Project::getWithDetails($projectId);
        $pm = Teammate::getProjectManager($projectId);

        // Get yesterday's report if not day 1
        $yesterdayReport = null;
        if ($state['current_day'] > 1) {
            $yesterdayReport = DailyReport::getForDay($projectId, $state['current_day'] - 1);
        }

        // Get today's meetings
        $meetings = Meeting::getForDay($projectId, $state['current_day']);

        // Get current tasks
        $tasks = Task::getKanbanBoard($projectId);

        return [
            'day' => $state['current_day'],
            'phase' => $state['day_phase'],
            'time_remaining' => $state['time_remaining'],
            'project' => $project,
            'pm' => $pm,
            'yesterday_report' => $yesterdayReport,
            'meetings' => $meetings,
            'tasks' => $tasks
        ];
    }

    /**
     * Run standup meeting
     */
    public function runStandup(int $projectId): int
    {
        $state = GameState::getForProject($projectId);
        $project = Project::find($projectId);

        // Create standup conversation
        $conversationId = Conversation::start(
            $projectId,
            'standup',
            'Daily Standup - Day ' . $state['current_day'],
            $state['current_day']
        );

        return $conversationId;
    }

    /**
     * Process standup update - records player update and returns first teammate's response
     * Standup is now interactive: each teammate responds individually with opportunity for discussion
     */
    public function processStandupUpdate(
        int $projectId,
        int $conversationId,
        string $playerYesterday,
        string $playerToday,
        string $playerBlockers
    ): array {
        $state = GameState::getForProject($projectId);

        // Record player's update
        StandupUpdate::recordPlayer(
            $projectId,
            $state['current_day'],
            $playerYesterday,
            $playerToday,
            $playerBlockers
        );

        Message::add($conversationId, 'player', "Yesterday: $playerYesterday\nToday: $playerToday\nBlockers: $playerBlockers");

        // Get all teammates (excluding PM and assistant)
        $teammates = Teammate::getTeammates($projectId);

        if (empty($teammates)) {
            // No teammates, go straight to PM summary
            return $this->completeStandup($projectId, $conversationId);
        }

        // Get first teammate's response
        $firstTeammate = $teammates[0];
        $response = $this->generateStandupResponse($projectId, $firstTeammate, $state['current_day']);

        StandupUpdate::recordTeammate(
            $projectId,
            $state['current_day'],
            $firstTeammate['id'],
            $response['yesterday'],
            $response['today'],
            $response['blockers']
        );

        Message::add(
            $conversationId,
            'bot',
            "Yesterday: {$response['yesterday']}\nToday: {$response['today']}\nBlockers: {$response['blockers']}",
            $firstTeammate['id']
        );

        // Return state for interactive flow
        $remainingTeammates = array_slice($teammates, 1);
        return [
            'current_teammate' => $firstTeammate,
            'current_response' => $response,
            'teammates_remaining' => array_column($remainingTeammates, 'id'),
            'teammates_completed' => [$firstTeammate['id']],
            'standup_complete' => false
        ];
    }

    /**
     * Get next teammate's standup response
     */
    public function getNextStandupResponse(
        int $projectId,
        int $conversationId,
        array $teammatesCompleted
    ): array {
        $state = GameState::getForProject($projectId);
        $teammates = Teammate::getTeammates($projectId);

        // Find next teammate who hasn't given update
        $nextTeammate = null;
        foreach ($teammates as $teammate) {
            if (!in_array($teammate['id'], $teammatesCompleted)) {
                $nextTeammate = $teammate;
                break;
            }
        }

        // If no more teammates, complete the standup
        if (!$nextTeammate) {
            return $this->completeStandup($projectId, $conversationId);
        }

        // Generate this teammate's response
        $response = $this->generateStandupResponse($projectId, $nextTeammate, $state['current_day']);

        StandupUpdate::recordTeammate(
            $projectId,
            $state['current_day'],
            $nextTeammate['id'],
            $response['yesterday'],
            $response['today'],
            $response['blockers']
        );

        Message::add(
            $conversationId,
            'bot',
            "Yesterday: {$response['yesterday']}\nToday: {$response['today']}\nBlockers: {$response['blockers']}",
            $nextTeammate['id']
        );

        // Update completed list
        $teammatesCompleted[] = $nextTeammate['id'];

        // Check remaining
        $remaining = [];
        foreach ($teammates as $teammate) {
            if (!in_array($teammate['id'], $teammatesCompleted)) {
                $remaining[] = $teammate['id'];
            }
        }

        return [
            'current_teammate' => $nextTeammate,
            'current_response' => $response,
            'teammates_remaining' => $remaining,
            'teammates_completed' => $teammatesCompleted,
            'standup_complete' => false
        ];
    }

    /**
     * Send a message during standup - allows player to ask questions
     * Relevant teammates can respond if they have information
     */
    public function sendStandupMessage(
        int $projectId,
        int $conversationId,
        string $message,
        array $teammatesCompleted
    ): array {
        $state = GameState::getForProject($projectId);
        $teammates = Teammate::getTeammates($projectId);
        $pm = Teammate::getProjectManager($projectId);

        // Add player message
        Message::add($conversationId, 'player', $message);

        // Get conversation history for context
        $history = Message::getForConversation($conversationId);

        // Determine who should respond based on message content and relevance
        $responder = $this->determineStandupResponder($message, $teammates, $pm, $history);

        // Generate response from the appropriate teammate
        $prompt = $responder['is_project_manager']
            ? $this->ai->buildPMPrompt($projectId, Project::find($projectId))
            : $this->ai->buildBotPrompt($projectId, $responder, 'standup');

        // Build context from recent messages
        $aiMessages = [];
        foreach (array_slice($history, -8) as $msg) {
            $aiMessages[] = [
                'role' => $msg['sender_type'] === 'player' ? 'user' : 'assistant',
                'content' => "[{$msg['sender_name']}]: {$msg['content']}"
            ];
        }
        $aiMessages[] = ['role' => 'user', 'content' => $message];

        $response = $this->ai->chat($responder['ai_model'], $aiMessages, $prompt);
        $responseContent = $response['content'] ?? "I don't have specific information about that.";

        Message::add($conversationId, 'bot', $responseContent, $responder['id']);

        // Check remaining teammates
        $remaining = [];
        foreach ($teammates as $teammate) {
            if (!in_array($teammate['id'], $teammatesCompleted)) {
                $remaining[] = $teammate['id'];
            }
        }

        return [
            'responder' => $responder,
            'response' => $responseContent,
            'teammates_remaining' => $remaining,
            'teammates_completed' => $teammatesCompleted,
            'standup_complete' => false
        ];
    }

    /**
     * Determine which teammate should respond to a standup question
     */
    private function determineStandupResponder(string $message, array $teammates, array $pm, array $history): array
    {
        $scores = [];
        $messageLower = strtolower($message);

        // Score PM
        $pmScore = 1.0;
        if (preg_match('/(timeline|schedule|priority|assign|deadline|task|plan|project)/i', $message)) {
            $pmScore += 3.0;
        }
        $scores[$pm['id']] = $pmScore;

        // Score each teammate
        foreach ($teammates as $teammate) {
            $score = 1.0;

            // Name mentioned?
            if (stripos($message, $teammate['name']) !== false) {
                $score += 5.0;
            }

            // Role/specialty relevance
            $specialty = strtolower($teammate['specialty'] ?? '');
            $role = strtolower($teammate['role'] ?? '');
            $keywords = array_merge(
                explode(' ', $specialty),
                explode(' ', $role)
            );

            foreach ($keywords as $word) {
                if (strlen($word) > 3 && stripos($messageLower, $word) !== false) {
                    $score += 1.5;
                }
            }

            $scores[$teammate['id']] = $score;
        }

        // Find highest scorer
        $bestId = array_keys($scores, max($scores))[0];

        if ($bestId === $pm['id']) {
            return $pm;
        }

        foreach ($teammates as $teammate) {
            if ($teammate['id'] === $bestId) {
                return $teammate;
            }
        }

        return $pm; // Fallback
    }

    /**
     * Complete the standup - PM summarizes and activates teammates
     */
    public function completeStandup(int $projectId, int $conversationId): array
    {
        $pm = Teammate::getProjectManager($projectId);

        // PM summarizes and generates tasks
        $pmSummary = $this->generatePMStandupSummary($projectId, $pm, $conversationId);

        Message::add($conversationId, 'bot', $pmSummary['summary'], $pm['id']);

        // Mark standup complete
        GameState::completeStandup($projectId);
        GameState::consumeTime($projectId, $this->config['game']['standup_duration']);

        // Activate teammates to start working on their assigned tasks
        $activatedSessions = $this->activateTeammatesForWork($projectId);

        return [
            'pm_summary' => $pmSummary,
            'new_tasks' => $pmSummary['tasks'] ?? [],
            'activated_sessions' => $activatedSessions,
            'standup_complete' => true
        ];
    }

    /**
     * Generate standup response for a teammate
     */
    private function generateStandupResponse(int $projectId, array $teammate, int $dayNumber): array
    {
        // Get teammate's current task
        $currentTask = $teammate['current_task_id'] ? Task::find($teammate['current_task_id']) : null;

        // Get recent work
        $recentWork = $this->getTeammateRecentWork($teammate['id'], $dayNumber);

        $prompt = $this->ai->buildBotPrompt($projectId, $teammate, 'standup');

        $messages = [
            [
                'role' => 'user',
                'content' => "It's standup time. Please give your update.\n\n" .
                    "Current task: " . ($currentTask ? $currentTask['title'] : 'None assigned') . "\n" .
                    "Recent work: $recentWork\n\n" .
                    "Provide your standup update with:\n1. What you worked on yesterday\n2. What you plan to work on today\n3. Any blockers"
            ]
        ];

        $response = $this->ai->chat($teammate['ai_model'], $messages, $prompt);
        $content = $response['content'] ?? 'No update available.';

        // Parse the response into structured format
        return $this->parseStandupResponse($content);
    }

    /**
     * Parse standup response into structured format
     */
    private function parseStandupResponse(string $content): array
    {
        // Simple parsing - in production would be more robust
        $yesterday = '';
        $today = '';
        $blockers = 'None';

        $lines = explode("\n", $content);
        $currentSection = '';

        foreach ($lines as $line) {
            $lineLower = strtolower($line);
            if (str_contains($lineLower, 'yesterday') || str_contains($lineLower, 'worked on')) {
                $currentSection = 'yesterday';
            } elseif (str_contains($lineLower, 'today') || str_contains($lineLower, 'plan')) {
                $currentSection = 'today';
            } elseif (str_contains($lineLower, 'blocker')) {
                $currentSection = 'blockers';
            } else {
                switch ($currentSection) {
                    case 'yesterday':
                        $yesterday .= trim($line) . ' ';
                        break;
                    case 'today':
                        $today .= trim($line) . ' ';
                        break;
                    case 'blockers':
                        $blockers .= trim($line) . ' ';
                        break;
                }
            }
        }

        return [
            'yesterday' => trim($yesterday) ?: 'Reviewing project requirements.',
            'today' => trim($today) ?: 'Ready for task assignment.',
            'blockers' => trim($blockers) ?: 'None'
        ];
    }

    /**
     * Get teammate's recent work summary
     */
    private function getTeammateRecentWork(int $teammateId, int $dayNumber): string
    {
        $tasks = Task::where([
            'assigned_to' => $teammateId,
            'status' => 'done'
        ], 'updated_at DESC');

        if (empty($tasks)) {
            return 'No completed tasks yet.';
        }

        $summary = [];
        foreach (array_slice($tasks, 0, 3) as $task) {
            $summary[] = $task['title'];
        }

        return implode(', ', $summary);
    }

    /**
     * Generate PM standup summary and tasks
     */
    private function generatePMStandupSummary(int $projectId, array $pm, int $conversationId): array
    {
        $project = Project::find($projectId);
        $updates = StandupUpdate::getForDay($projectId, GameState::getForProject($projectId)['current_day']);
        $currentTasks = Task::getKanbanBoard($projectId);

        $prompt = $this->ai->buildPMPrompt($projectId, $project);

        $updatesText = '';
        foreach ($updates as $update) {
            $name = $update['is_player'] ? 'Player' : $update['teammate_name'];
            $updatesText .= "$name:\n- Yesterday: {$update['yesterday_work']}\n- Today: {$update['today_plan']}\n- Blockers: {$update['blockers']}\n\n";
        }

        $tasksInProgress = count($currentTasks['in_progress']);
        $tasksTodo = count($currentTasks['todo']);
        $tasksBacklog = count($currentTasks['backlog']);

        $messages = [
            [
                'role' => 'user',
                'content' => "Standup updates:\n$updatesText\n\nCurrent task status:\n- In Progress: $tasksInProgress\n- To Do: $tasksTodo\n- Backlog: $tasksBacklog\n\nPlease provide:\n1. A brief summary of today's focus\n2. Any concerns or blockers to address\n3. New tasks if needed (in JSON format)"
            ]
        ];

        $response = $this->ai->chat($pm['ai_model'], $messages, $prompt);
        $content = $response['content'] ?? '';

        // Extract tasks from JSON if present
        $tasks = [];
        if (preg_match('/\{[\s\S]*"tasks"[\s\S]*\}/m', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if (isset($json['tasks'])) {
                $tasks = $this->createTasksFromPM($projectId, $json['tasks']);
            }
        }

        return [
            'summary' => $content,
            'tasks' => $tasks
        ];
    }

    /**
     * Create tasks from PM's suggestions
     */
    private function createTasksFromPM(int $projectId, array $taskData): array
    {
        $state = GameState::getForProject($projectId);
        $teammates = Teammate::getTeammates($projectId);
        $createdTasks = [];

        foreach ($taskData as $task) {
            // Find best teammate for recommended role
            $recommendedId = null;
            foreach ($teammates as $tm) {
                if (stripos($tm['role'], $task['recommended_role'] ?? '') !== false) {
                    $recommendedId = $tm['id'];
                    break;
                }
            }

            $taskId = Task::create([
                'project_id' => $projectId,
                'title' => $task['title'],
                'description' => $task['description'] ?? '',
                'task_type' => $task['type'] ?? 'feature',
                'priority' => $task['priority'] ?? 'medium',
                'status' => 'todo',
                'recommended_assignee' => $recommendedId,
                'assigned_to' => $recommendedId, // Auto-assign to recommended teammate
                'estimated_time' => $task['estimated_time'] ?? 60,
                'day_created' => $state['current_day']
            ]);

            // Actually assign the task to the teammate so they know to work on it
            if ($recommendedId) {
                Teammate::assignTask($recommendedId, $taskId);
            }

            $createdTasks[] = Task::find($taskId);
        }

        return $createdTasks;
    }

    /**
     * Start a one-on-one conversation with a teammate
     */
    public function startOneOnOne(int $projectId, int $teammateId): int
    {
        $teammate = Teammate::find($teammateId);
        $state = GameState::getForProject($projectId);

        Teammate::setStatus($teammateId, 'busy');

        return Conversation::start(
            $projectId,
            'one_on_one',
            "Chat with {$teammate['name']}",
            $state['current_day']
        );
    }

    /**
     * Send message in one-on-one and get response
     */
    public function sendOneOnOneMessage(
        int $projectId,
        int $conversationId,
        int $teammateId,
        string $message
    ): array {
        $teammate = Teammate::find($teammateId);

        // Add player message
        Message::add($conversationId, 'player', $message);

        // Get conversation history
        $history = Message::getForConversation($conversationId);

        // Build messages for AI
        $aiMessages = [];
        foreach ($history as $msg) {
            $aiMessages[] = [
                'role' => $msg['sender_type'] === 'player' ? 'user' : 'assistant',
                'content' => $msg['content']
            ];
        }

        $prompt = $this->ai->buildBotPrompt($projectId, $teammate, 'one_on_one', $message);

        $response = $this->ai->chat($teammate['ai_model'], $aiMessages, $prompt);

        // Add bot response
        Message::add($conversationId, 'bot', $response['content'] ?? 'I need a moment to think about that.', $teammateId);

        return [
            'response' => $response['content'] ?? '',
            'teammate' => $teammate
        ];
    }

    /**
     * End one-on-one conversation
     */
    public function endOneOnOne(int $projectId, int $conversationId, int $teammateId): void
    {
        Teammate::setStatus($teammateId, 'available');

        // Generate summary
        $messages = Message::getForConversation($conversationId);
        $summary = $this->generateConversationSummary($messages);

        Conversation::end($conversationId, $summary);

        // Add to context
        ContextChunk::addChunk($projectId, 'conversation', $summary);

        // Consume time
        GameState::consumeTime($projectId, $this->config['game']['one_on_one_cost']);
    }

    /**
     * Start a coding session
     */
    public function startCodingSession(int $projectId, ?int $taskId = null): array
    {
        $assistant = Teammate::getPlayerAssistant($projectId);
        $state = GameState::getForProject($projectId);
        $task = $taskId ? Task::find($taskId) : null;

        // Create conversation
        $conversationId = Conversation::start(
            $projectId,
            'coding_session',
            $task ? "Working on: {$task['title']}" : 'Coding Session',
            $state['current_day']
        );

        // Create branch if task
        $branchName = null;
        if ($task && $task['branch_name']) {
            $branchName = $task['branch_name'];
            $this->git->createBranch($projectId, $branchName);
        }

        // Start coding session
        $sessionId = CodingSession::start(
            $projectId,
            $assistant['id'],
            $conversationId,
            $taskId,
            $branchName
        );

        Teammate::setStatus($assistant['id'], 'busy');

        return [
            'session_id' => $sessionId,
            'conversation_id' => $conversationId,
            'assistant' => $assistant,
            'task' => $task,
            'branch' => $branchName
        ];
    }

    /**
     * Send message in coding session
     */
    public function sendCodingMessage(
        int $projectId,
        int $sessionId,
        int $conversationId,
        string $message
    ): array {
        $session = CodingSession::getWithDetails($sessionId);
        $assistant = Teammate::find($session['teammate_id']);

        // Add player message
        Message::add($conversationId, 'player', $message);

        // Get conversation history
        $history = Message::getForConversation($conversationId);

        // Get current files in repo for context
        $repoFiles = $this->git->listFiles($projectId);

        // Build context
        $prompt = $this->ai->buildBotPrompt($projectId, $assistant, 'coding_session', $message);
        $prompt .= "\n\nCurrent files in repository: " . json_encode(array_column($repoFiles, 'path'));

        if ($session['task_title']) {
            $prompt .= "\n\nCurrently working on task: {$session['task_title']}";
        }

        // Build messages
        $aiMessages = [];
        foreach ($history as $msg) {
            $aiMessages[] = [
                'role' => $msg['sender_type'] === 'player' ? 'user' : 'assistant',
                'content' => $msg['content']
            ];
        }

        $response = $this->ai->chat($assistant['ai_model'], $aiMessages, $prompt);
        $content = $response['content'] ?? '';

        // Check for code blocks that should be written to files
        $filesWritten = $this->processCodeBlocks($projectId, $sessionId, $content);

        Message::add($conversationId, 'bot', $content, $assistant['id'], 'code');

        return [
            'response' => $content,
            'files_written' => $filesWritten
        ];
    }

    /**
     * Process code blocks in AI response and write to files
     */
    private function processCodeBlocks(int $projectId, int $sessionId, string $content): array
    {
        $filesWritten = [];

        // Look for file write instructions: ```filename.ext or <!-- file: filename.ext -->
        preg_match_all('/```(\S+)\n([\s\S]*?)```/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $identifier = $match[1];
            $code = $match[2];

            // Check if it looks like a file path
            if (preg_match('/^[\w\-\/]+\.\w+$/', $identifier)) {
                $this->git->writeFile($projectId, $identifier, $code);
                CodingSession::addFile($sessionId, $identifier);
                $filesWritten[] = $identifier;
            }
        }

        return $filesWritten;
    }

    /**
     * Commit changes in coding session
     */
    public function commitCodingSession(int $projectId, int $sessionId, string $message): array
    {
        $session = CodingSession::find($sessionId);
        $state = GameState::getForProject($projectId);

        $result = $this->git->commit(
            $projectId,
            $message,
            $session['teammate_id'],
            $sessionId,
            $state['current_day']
        );

        if ($result['success']) {
            CodingSession::addCommit($sessionId, [
                'hash' => $result['hash'],
                'message' => $message,
                'files' => $result['files']
            ]);
        }

        return $result;
    }

    /**
     * End coding session
     */
    public function endCodingSession(int $projectId, int $sessionId): void
    {
        $session = CodingSession::find($sessionId);

        Teammate::setStatus($session['teammate_id'], 'available');
        CodingSession::end($sessionId);

        // Generate summary
        $conversation = Conversation::getWithMessages($session['conversation_id']);
        $summary = $this->generateConversationSummary($conversation['messages']);
        Conversation::end($session['conversation_id'], $summary);

        // Add to context
        ContextChunk::addChunk($projectId, 'code', $summary);

        GameState::consumeTime($projectId, $this->config['game']['coding_session_cost']);
    }

    /**
     * Start a meeting
     */
    public function startMeeting(int $projectId, int $meetingId): array
    {
        $meeting = Meeting::getWithAttendees($meetingId);
        $pm = Teammate::getProjectManager($projectId);
        $state = GameState::getForProject($projectId);

        // Create conversation
        $conversationId = Conversation::start(
            $projectId,
            'meeting',
            $meeting['title'],
            $state['current_day']
        );

        Meeting::start($meetingId, $conversationId);

        // Set all attendees to in_meeting
        foreach ($meeting['attendees'] as $attendee) {
            Teammate::setStatus($attendee['id'], 'in_meeting');
        }

        // PM opens the meeting
        $openingMessage = $this->generateMeetingOpening($projectId, $pm, $meeting);
        Message::add($conversationId, 'bot', $openingMessage, $pm['id']);

        return [
            'meeting' => $meeting,
            'conversation_id' => $conversationId,
            'opening' => $openingMessage
        ];
    }

    /**
     * Generate meeting opening from PM
     */
    private function generateMeetingOpening(int $projectId, array $pm, array $meeting): string
    {
        $project = Project::find($projectId);
        $prompt = $this->ai->buildPMPrompt($projectId, $project);

        $attendeeNames = array_map(fn($a) => $a['name'], $meeting['attendees']);

        $messages = [
            [
                'role' => 'user',
                'content' => "You're starting a meeting titled \"{$meeting['title']}\".\nTopic: {$meeting['topic']}\nAttendees: " . implode(', ', $attendeeNames) . "\n\nPlease open the meeting briefly and set the agenda."
            ]
        ];

        $response = $this->ai->chat($pm['ai_model'], $messages, $prompt);
        return $response['content'] ?? "Let's begin our meeting on {$meeting['title']}.";
    }

    /**
     * Send message in meeting and get next speaker response
     */
    public function sendMeetingMessage(
        int $projectId,
        int $meetingId,
        int $conversationId,
        string $message
    ): array {
        $meeting = Meeting::getWithAttendees($meetingId);
        $pm = Teammate::getProjectManager($projectId);

        // Add player message
        Message::add($conversationId, 'player', $message);

        // Get conversation history
        $history = Message::getForConversation($conversationId);

        // Determine next speaker using weighted selection
        $nextSpeaker = $this->determineNextSpeaker($meeting, $history, $message, $pm);

        // Generate response from next speaker
        $prompt = $nextSpeaker['is_project_manager']
            ? $this->ai->buildPMPrompt($projectId, Project::find($projectId))
            : $this->ai->buildBotPrompt($projectId, $nextSpeaker, 'meeting', $message);

        $aiMessages = [];
        foreach (array_slice($history, -10) as $msg) {
            $aiMessages[] = [
                'role' => $msg['sender_type'] === 'player' ? 'user' : 'assistant',
                'content' => "[{$msg['sender_name']}]: {$msg['content']}"
            ];
        }
        $aiMessages[] = ['role' => 'user', 'content' => "[Player]: $message"];

        $response = $this->ai->chat($nextSpeaker['ai_model'], $aiMessages, $prompt);

        Message::add($conversationId, 'bot', $response['content'] ?? '', $nextSpeaker['id']);

        return [
            'speaker' => $nextSpeaker,
            'response' => $response['content'] ?? ''
        ];
    }

    /**
     * Determine next speaker in meeting using weighted selection
     */
    private function determineNextSpeaker(array $meeting, array $history, string $lastMessage, array $pm): array
    {
        $candidates = array_merge([$pm], $meeting['attendees']);
        $scores = [];

        // Get last speaker
        $lastBotMessage = null;
        foreach (array_reverse($history) as $msg) {
            if ($msg['sender_type'] === 'bot') {
                $lastBotMessage = $msg;
                break;
            }
        }

        foreach ($candidates as $candidate) {
            $score = 1.0;

            // Was directly addressed? (name mentioned in message)
            if (stripos($lastMessage, $candidate['name']) !== false) {
                $score += 3.0;
            }

            // Is their specialty relevant to the message?
            $specialty = strtolower($candidate['specialty'] ?? '');
            $messageLower = strtolower($lastMessage);
            $specialtyWords = explode(' ', $specialty);
            foreach ($specialtyWords as $word) {
                if (strlen($word) > 4 && stripos($messageLower, $word) !== false) {
                    $score += 0.5;
                }
            }

            // Avoid same speaker twice in a row
            if ($lastBotMessage && $lastBotMessage['sender_id'] == $candidate['id']) {
                $score -= 2.0;
            }

            // PM bonus for coordination topics
            if ($candidate['is_project_manager'] ?? false) {
                if (preg_match('/(timeline|schedule|priority|blocke|assign|deadline)/i', $lastMessage)) {
                    $score += 2.0;
                }
            }

            $scores[$candidate['id']] = max(0.1, $score);
        }

        // Weighted random selection
        $total = array_sum($scores);
        $rand = mt_rand() / mt_getrandmax() * $total;
        $cumulative = 0;

        foreach ($candidates as $candidate) {
            $cumulative += $scores[$candidate['id']];
            if ($rand <= $cumulative) {
                return $candidate;
            }
        }

        return $pm; // Fallback to PM
    }

    /**
     * End meeting
     */
    public function endMeeting(int $projectId, int $meetingId): void
    {
        $meeting = Meeting::getWithAttendees($meetingId);

        // Release all attendees
        foreach ($meeting['attendees'] as $attendee) {
            Teammate::setStatus($attendee['id'], 'available');
        }

        Meeting::complete($meetingId);

        // Generate and store summary
        if ($meeting['conversation_id']) {
            $conversation = Conversation::getWithMessages($meeting['conversation_id']);
            $summary = $this->generateConversationSummary($conversation['messages']);
            Conversation::end($meeting['conversation_id'], $summary);
            ContextChunk::addChunk($projectId, 'conversation', "Meeting: {$meeting['title']}\n\n$summary");
        }

        GameState::consumeTime($projectId, $meeting['duration']);
    }

    /**
     * Generate conversation summary
     */
    private function generateConversationSummary(array $messages): string
    {
        if (count($messages) < 3) {
            return 'Brief conversation.';
        }

        // Simple summary - concatenate key points
        $points = [];
        foreach ($messages as $msg) {
            if ($msg['sender_type'] === 'bot' && strlen($msg['content']) > 50) {
                // Extract first sentence
                $firstSentence = strtok($msg['content'], '.!?');
                if ($firstSentence) {
                    $points[] = trim($firstSentence);
                }
            }
        }

        return implode('. ', array_slice($points, 0, 5)) . '.';
    }

    /**
     * End the day and generate report
     */
    public function endDay(int $projectId): array
    {
        $state = GameState::getForProject($projectId);
        $pm = Teammate::getProjectManager($projectId);
        $project = Project::find($projectId);

        // Get day's data
        $tasksCompleted = Task::getByStatus($projectId, 'done');
        $tasksInProgress = Task::getByStatus($projectId, 'in_progress');
        $standupUpdates = StandupUpdate::getForDay($projectId, $state['current_day']);

        // Generate PM summary
        $prompt = $this->ai->buildPMPrompt($projectId, $project);

        $messages = [
            [
                'role' => 'user',
                'content' => "End of day {$state['current_day']}. Please compile the daily report.\n\n" .
                    "Tasks completed: " . count($tasksCompleted) . "\n" .
                    "Tasks in progress: " . count($tasksInProgress) . "\n\n" .
                    "Provide a summary and priorities for tomorrow."
            ]
        ];

        $response = $this->ai->chat($pm['ai_model'], $messages, $prompt);

        // Save report
        $reportId = DailyReport::saveReport($projectId, $state['current_day'], [
            'summary' => $response['content'] ?? '',
            'tasks_completed' => array_column($tasksCompleted, 'title'),
            'tasks_in_progress' => array_column($tasksInProgress, 'title'),
            'blockers' => [],
            'next_day_priorities' => [],
            'team_notes' => []
        ]);

        // Advance to next day
        GameState::advanceDay($projectId);

        return [
            'report' => DailyReport::find($reportId),
            'pm_summary' => $response['content'] ?? ''
        ];
    }

    /**
     * Start whiteboard session
     */
    public function startWhiteboard(int $projectId, int $teammateId, string $topic): int
    {
        $teammate = Teammate::find($teammateId);
        $state = GameState::getForProject($projectId);

        Teammate::setStatus($teammateId, 'busy');

        $conversationId = Conversation::start(
            $projectId,
            'whiteboard',
            "Whiteboard: $topic with {$teammate['name']}",
            $state['current_day']
        );

        return $conversationId;
    }

    /**
     * End whiteboard session and create document
     */
    public function endWhiteboard(int $projectId, int $conversationId, int $teammateId): int
    {
        $conversation = Conversation::getWithMessages($conversationId);
        $state = GameState::getForProject($projectId);

        Teammate::setStatus($teammateId, 'available');

        // Generate whiteboard document from conversation
        $content = $this->generateWhiteboardDocument($conversation['messages']);

        $docId = ProjectDocument::createVersion(
            $projectId,
            'whiteboard',
            $conversation['title'],
            $content,
            $teammateId,
            $state['current_day']
        );

        $summary = $this->generateConversationSummary($conversation['messages']);
        Conversation::end($conversationId, $summary);

        ContextChunk::addChunk($projectId, 'document', "Whiteboard session: {$conversation['title']}\n\n$content");

        GameState::consumeTime($projectId, $this->config['game']['whiteboard_cost']);

        return $docId;
    }

    /**
     * Generate whiteboard document from conversation
     */
    private function generateWhiteboardDocument(array $messages): string
    {
        $doc = "# Whiteboard Session Notes\n\n";

        foreach ($messages as $msg) {
            $sender = $msg['sender_type'] === 'player' ? 'You' : $msg['sender_name'];
            $doc .= "**$sender**: {$msg['content']}\n\n";
        }

        return $doc;
    }

    /**
     * Activate teammates to start working on their assigned tasks
     * This is called after standup or can be triggered manually
     */
    public function activateTeammatesForWork(int $projectId): array
    {
        $teammates = Teammate::getTeammates($projectId);
        $activatedSessions = [];

        foreach ($teammates as $teammate) {
            // Skip if teammate has no assigned task
            if (!$teammate['current_task_id']) {
                continue;
            }

            // Skip if teammate already has an active coding session
            $activeSession = CodingSession::getActive($teammate['id']);
            if ($activeSession) {
                continue;
            }

            // Get the assigned task
            $task = Task::find($teammate['current_task_id']);
            if (!$task || $task['status'] === 'done') {
                continue;
            }

            // Start autonomous coding session for this teammate
            $session = $this->startAutonomousCodingSession($projectId, $teammate, $task);
            if ($session) {
                $activatedSessions[] = $session;

                // Move task to in_progress
                if ($task['status'] === 'todo') {
                    Task::update($task['id'], ['status' => 'in_progress']);
                }
            }
        }

        return $activatedSessions;
    }

    /**
     * Start an autonomous coding session for a teammate
     * The teammate works independently on their task
     */
    private function startAutonomousCodingSession(int $projectId, array $teammate, array $task): ?array
    {
        $state = GameState::getForProject($projectId);
        $project = Project::find($projectId);

        // Create conversation for the autonomous session
        $conversationId = Conversation::start(
            $projectId,
            'coding_session',
            "{$teammate['name']} working on: {$task['title']}",
            $state['current_day']
        );

        // Create branch if needed
        $branchName = $task['branch_name'] ?? null;
        if (!$branchName) {
            $branchName = 'feature/' . preg_replace('/[^a-z0-9]+/', '-', strtolower($task['title']));
            $branchName = trim($branchName, '-');
            Task::update($task['id'], ['branch_name' => $branchName]);
        }
        $this->git->createBranch($projectId, $branchName);

        // Start coding session
        $sessionId = CodingSession::start(
            $projectId,
            $teammate['id'],
            $conversationId,
            $task['id'],
            $branchName
        );

        Teammate::setStatus($teammate['id'], 'coding');

        // Generate the autonomous work - teammate works on the task independently
        $workResult = $this->executeAutonomousWork($projectId, $teammate, $task, $sessionId, $conversationId);

        return [
            'session_id' => $sessionId,
            'conversation_id' => $conversationId,
            'teammate' => $teammate,
            'task' => $task,
            'branch' => $branchName,
            'work_result' => $workResult
        ];
    }

    /**
     * Execute autonomous work for a teammate
     * The teammate AI generates code and commits it
     */
    private function executeAutonomousWork(
        int $projectId,
        array $teammate,
        array $task,
        int $sessionId,
        int $conversationId
    ): array {
        $project = Project::find($projectId);

        // Get current files in repo for context
        $repoFiles = $this->git->listFiles($projectId);
        $fileList = json_encode(array_column($repoFiles, 'path'));

        // Build the work prompt for the teammate
        $prompt = $this->ai->buildBotPrompt($projectId, $teammate, 'coding_session');

        $workInstruction = "You are working autonomously on your assigned task.\n\n" .
            "TASK: {$task['title']}\n" .
            "DESCRIPTION: {$task['description']}\n" .
            "TYPE: {$task['task_type']}\n" .
            "PRIORITY: {$task['priority']}\n\n" .
            "PROJECT: {$project['name']}\n" .
            "VISION: {$project['vision']}\n\n" .
            "Current files in repository: $fileList\n\n" .
            "Please implement this task. Write the code needed to complete it. " .
            "Format your code in code blocks with the filename, like:\n" .
            "```src/components/MyComponent.js\n// code here\n```\n\n" .
            "After writing the code, provide a brief commit message for your changes.";

        $messages = [
            ['role' => 'user', 'content' => $workInstruction]
        ];

        // Record the task assignment in the conversation
        Message::add($conversationId, 'system', "Autonomous session started. Working on: {$task['title']}");

        // Get AI response with the implementation
        $response = $this->ai->chat($teammate['ai_model'], $messages, $prompt);
        $content = $response['content'] ?? '';

        // Record the work done
        Message::add($conversationId, 'bot', $content, $teammate['id'], 'code');

        // Process code blocks and write files
        $filesWritten = $this->processCodeBlocks($projectId, $sessionId, $content);

        // Extract commit message from response or generate one
        $commitMessage = $this->extractCommitMessage($content, $task);

        // If files were written, commit them
        $commitResult = null;
        if (!empty($filesWritten)) {
            $commitResult = $this->commitCodingSession($projectId, $sessionId, $commitMessage);

            // Add completion message
            Message::add($conversationId, 'system',
                "Committed " . count($filesWritten) . " file(s): " . implode(', ', $filesWritten));
        }

        // End the coding session
        $this->endAutonomousCodingSession($projectId, $sessionId, $conversationId, $teammate['id']);

        return [
            'files_written' => $filesWritten,
            'commit' => $commitResult,
            'response' => $content
        ];
    }

    /**
     * Extract commit message from AI response or generate a default one
     */
    private function extractCommitMessage(string $content, array $task): string
    {
        // Look for explicit commit message in response
        if (preg_match('/commit\s*message[:\s]+["\']?(.+?)["\']?\s*$/mi', $content, $matches)) {
            return trim($matches[1]);
        }

        // Generate default commit message based on task
        $type = $task['task_type'] ?? 'feat';
        $typeMap = [
            'feature' => 'feat',
            'bug' => 'fix',
            'enhancement' => 'improve',
            'refactor' => 'refactor',
            'test' => 'test',
            'docs' => 'docs'
        ];
        $prefix = $typeMap[$type] ?? 'feat';

        return "$prefix: {$task['title']}";
    }

    /**
     * End autonomous coding session
     */
    private function endAutonomousCodingSession(
        int $projectId,
        int $sessionId,
        int $conversationId,
        int $teammateId
    ): void {
        // Get the session to find the task
        $session = CodingSession::find($sessionId);

        // Mark teammate as available again
        Teammate::setStatus($teammateId, 'available');

        // Clear their current task since work is done
        Teammate::update($teammateId, ['current_task_id' => null]);

        // Mark the task as done (moved to review for player approval)
        if ($session && $session['task_id']) {
            Task::update($session['task_id'], ['status' => 'review']);
        }

        // End the session
        CodingSession::end($sessionId);

        // Generate and store summary
        $conversation = Conversation::getWithMessages($conversationId);
        $summary = $this->generateConversationSummary($conversation['messages']);
        Conversation::end($conversationId, $summary);

        // Add to context
        ContextChunk::addChunk($projectId, 'code', "Autonomous work: $summary");

        // Consume time for the session
        GameState::consumeTime($projectId, $this->config['game']['coding_session_cost'] ?? 30);
    }

    /**
     * Check for idle teammates with tasks and activate them
     * Can be called periodically or on-demand
     */
    public function checkAndActivateIdleTeammates(int $projectId): array
    {
        return $this->activateTeammatesForWork($projectId);
    }
}
