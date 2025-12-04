<?php
/**
 * AI Service - Handles communication with multiple AI providers
 */

namespace StartupGame\Services;

use StartupGame\Models\Setting;
use StartupGame\Models\ContextChunk;

class AIService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Send a message to an AI model
     */
    public function chat(
        string $model,
        array $messages,
        string $systemPrompt = '',
        array $options = []
    ): array {
        $modelConfig = $this->config['ai_models'][$model] ?? null;
        if (!$modelConfig) {
            return ['error' => "Unknown model: $model"];
        }

        $apiKey = Setting::get($modelConfig['requires_key']);
        if (!$apiKey) {
            return ['error' => "API key not configured for $model"];
        }

        return match($model) {
            'gemini-3' => $this->callGemini($apiKey, $messages, $systemPrompt, $options),
            'chatgpt-5.1' => $this->callOpenAI($apiKey, $modelConfig['model_id'], $messages, $systemPrompt, $options),
            'claude-sonnet-4.5', 'claude-opus-4.5' => $this->callAnthropic($apiKey, $modelConfig['model_id'], $messages, $systemPrompt, $options),
            default => ['error' => "Unsupported model: $model"]
        };
    }

    /**
     * Call Anthropic Claude API
     */
    private function callAnthropic(
        string $apiKey,
        string $modelId,
        array $messages,
        string $systemPrompt,
        array $options
    ): array {
        $payload = [
            'model' => $modelId,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages' => $this->formatMessagesForAnthropic($messages)
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $response = $this->makeRequest(
            'https://api.anthropic.com/v1/messages',
            $payload,
            [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json'
            ]
        );

        if (isset($response['error'])) {
            return $response;
        }

        return [
            'content' => $response['content'][0]['text'] ?? '',
            'model' => $modelId,
            'usage' => $response['usage'] ?? []
        ];
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(
        string $apiKey,
        string $modelId,
        array $messages,
        string $systemPrompt,
        array $options
    ): array {
        $formattedMessages = [];

        if ($systemPrompt) {
            $formattedMessages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['content']
            ];
        }

        $payload = [
            'model' => $modelId,
            'messages' => $formattedMessages,
            'max_tokens' => $options['max_tokens'] ?? 4096
        ];

        $response = $this->makeRequest(
            'https://api.openai.com/v1/chat/completions',
            $payload,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        );

        if (isset($response['error'])) {
            return $response;
        }

        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'model' => $modelId,
            'usage' => $response['usage'] ?? []
        ];
    }

    /**
     * Call Google Gemini API
     */
    private function callGemini(
        string $apiKey,
        array $messages,
        string $systemPrompt,
        array $options
    ): array {
        $contents = [];

        foreach ($messages as $msg) {
            $contents[] = [
                'role' => $msg['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $msg['content']]]
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? 4096
            ]
        ];

        if ($systemPrompt) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemPrompt]]
            ];
        }

        $response = $this->makeRequest(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$apiKey",
            $payload,
            ['Content-Type: application/json']
        );

        if (isset($response['error'])) {
            return $response;
        }

        return [
            'content' => $response['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'model' => 'gemini-2.0-flash',
            'usage' => $response['usageMetadata'] ?? []
        ];
    }

    /**
     * Format messages for Anthropic API
     */
    private function formatMessagesForAnthropic(array $messages): array
    {
        $formatted = [];
        foreach ($messages as $msg) {
            $formatted[] = [
                'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['content']
            ];
        }
        return $formatted;
    }

    /**
     * Make HTTP request
     */
    private function makeRequest(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => "Request failed: $error"];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $decoded['error']['message'] ?? $decoded['error'] ?? 'Unknown error';
            return ['error' => "API error ($httpCode): $errorMsg"];
        }

        return $decoded;
    }

    /**
     * Build context-aware prompt for a bot
     */
    public function buildBotPrompt(
        int $projectId,
        array $teammate,
        string $conversationType,
        string $query = ''
    ): string {
        // Get relevant context
        $context = ContextChunk::getRelevant($projectId, $query, 5);
        $contextStr = ContextChunk::buildContextString($context);

        $rolePrompt = $this->getRolePrompt($teammate['role']);

        return <<<PROMPT
You are {$teammate['name']}, a {$teammate['role']} at a startup.
Your specialty is: {$teammate['specialty']}

Personality: {$teammate['personality']}

You are currently in a $conversationType.

$rolePrompt

Here is relevant project context:
$contextStr

Respond naturally as your character. Be helpful, collaborative, and focused on the project goals.
Keep responses concise but informative. If discussing code, be specific and provide examples.
PROMPT;
    }

    /**
     * Get role-specific prompt additions
     */
    private function getRolePrompt(string $role): string
    {
        return match($role) {
            'frontend_developer' => "Focus on UI/UX implementation, component architecture, and user experience. You're proficient in React, Vue, and modern CSS.",
            'backend_developer' => "Focus on API design, database architecture, and server-side logic. You're proficient in various backend technologies.",
            'fullstack_developer' => "You can work across the entire stack. Balance frontend and backend concerns.",
            'devops_engineer' => "Focus on deployment, CI/CD, infrastructure, and operational concerns. Think about scalability and reliability.",
            'qa_engineer' => "Focus on testing strategies, bug identification, and quality assurance. Think about edge cases and user scenarios.",
            'designer' => "Focus on user experience, visual design, and usability. Think about user flows and accessibility.",
            'tech_lead' => "Provide architectural guidance, code review insights, and technical mentorship. Think about long-term maintainability.",
            default => "Contribute your expertise to help the team succeed."
        };
    }

    /**
     * Build Project Manager prompt
     */
    public function buildPMPrompt(int $projectId, array $projectData): string
    {
        return <<<PROMPT
You are the Project Manager for "{$projectData['name']}".

Project Description: {$projectData['description']}
Project Vision: {$projectData['vision']}
Current Phase: {$projectData['current_phase']}
Phase Progress: {$projectData['phase_progress']}%

Your responsibilities:
1. Keep the project on track toward its goals
2. Generate appropriate tasks for the team based on current needs
3. Assign tasks to the most suitable team members based on their specialties
4. Schedule meetings when detailed discussions are needed
5. Provide daily summaries and identify blockers
6. Help the team make technical decisions
7. Keep documentation up to date

When generating tasks, provide them in this JSON format:
{
    "tasks": [
        {
            "title": "Task title",
            "description": "Detailed description",
            "type": "feature|bug|refactor|documentation|design|research",
            "priority": "low|medium|high|critical",
            "recommended_role": "role that best fits this task",
            "estimated_time": 60
        }
    ]
}

Be practical and break down work into manageable pieces. Consider dependencies between tasks.
PROMPT;
    }
}
