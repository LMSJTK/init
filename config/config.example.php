<?php
/**
 * Startup Game Configuration
 * Copy this file to config.php and update with your settings
 */

return [
    // Database Configuration
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'startup_game',
        'user' => 'your_db_user',
        'password' => 'your_db_password',
        'charset' => 'utf8mb4',
    ],

    // Application Settings
    'app' => [
        'name' => 'Startup Game',
        'url' => 'http://localhost/startup-game',
        'debug' => true,
        'timezone' => 'UTC',
    ],

    // Storage Paths
    'storage' => [
        'logs' => __DIR__ . '/../storage/logs',
        'git_repos' => __DIR__ . '/../storage/git-repos',
    ],

    // Git Configuration
    'git' => [
        'default_branch' => 'main',
        'author_name' => 'Startup Game Bot',
        'author_email' => 'bot@startup-game.local',
    ],

    // Game Settings
    'game' => [
        'workday_minutes' => 480, // 8 hours
        'standup_duration' => 15,
        'meeting_default_duration' => 30,
        'coding_session_cost' => 60, // minutes
        'one_on_one_cost' => 30,
        'whiteboard_cost' => 45,
    ],

    // AI Model Endpoints (API keys stored in database for security)
    'ai_models' => [
        'gemini-3' => [
            'name' => 'Gemini 3',
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
            'requires_key' => 'gemini_api_key',
        ],
        'chatgpt-5.1' => [
            'name' => 'ChatGPT 5.1',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'model_id' => 'gpt-4o',
            'requires_key' => 'openai_api_key',
        ],
        'claude-sonnet-4.5' => [
            'name' => 'Claude Sonnet 4.5',
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'model_id' => 'claude-sonnet-4-5-20250929',
            'requires_key' => 'anthropic_api_key',
        ],
        'claude-opus-4.5' => [
            'name' => 'Claude Opus 4.5',
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'model_id' => 'claude-opus-4-5-20251101',
            'requires_key' => 'anthropic_api_key',
        ],
    ],

    // Available teammate roles/specialties
    'roles' => [
        'frontend_developer' => [
            'name' => 'Frontend Developer',
            'specialty' => 'React, Vue, CSS, UI/UX implementation',
            'color' => '#3498db',
        ],
        'backend_developer' => [
            'name' => 'Backend Developer',
            'specialty' => 'APIs, databases, server architecture',
            'color' => '#2ecc71',
        ],
        'fullstack_developer' => [
            'name' => 'Fullstack Developer',
            'specialty' => 'End-to-end feature development',
            'color' => '#9b59b6',
        ],
        'devops_engineer' => [
            'name' => 'DevOps Engineer',
            'specialty' => 'CI/CD, infrastructure, deployment',
            'color' => '#e67e22',
        ],
        'qa_engineer' => [
            'name' => 'QA Engineer',
            'specialty' => 'Testing, quality assurance, bug hunting',
            'color' => '#e74c3c',
        ],
        'designer' => [
            'name' => 'UI/UX Designer',
            'specialty' => 'Design systems, wireframes, prototypes',
            'color' => '#1abc9c',
        ],
        'tech_lead' => [
            'name' => 'Tech Lead',
            'specialty' => 'Architecture decisions, code review, mentoring',
            'color' => '#34495e',
        ],
    ],
];
