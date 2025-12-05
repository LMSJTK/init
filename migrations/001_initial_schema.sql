-- Startup Game Database Schema
-- MySQL/MariaDB

-- Settings table for API keys and configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    encrypted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Projects table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    vision TEXT,
    current_phase ENUM('planning', 'design', 'development', 'testing', 'deployment', 'maintenance') DEFAULT 'planning',
    phase_progress INT DEFAULT 0,
    git_repo_path VARCHAR(500),
    git_remote_url VARCHAR(500),
    github_linked BOOLEAN DEFAULT FALSE,
    status ENUM('setup', 'active', 'paused', 'completed', 'archived') DEFAULT 'setup',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Game state for tracking days and time
CREATE TABLE IF NOT EXISTS game_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    current_day INT DEFAULT 1,
    day_phase ENUM('morning', 'standup', 'work', 'evening', 'end_of_day') DEFAULT 'morning',
    time_remaining INT DEFAULT 480, -- minutes in workday (8 hours)
    standup_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Teammates (bots)
CREATE TABLE IF NOT EXISTS teammates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(100) NOT NULL,
    specialty VARCHAR(255),
    ai_model ENUM('gemini-3', 'chatgpt-5.1', 'claude-sonnet-4.5', 'claude-opus-4.5') NOT NULL,
    personality TEXT,
    avatar_color VARCHAR(7) DEFAULT '#3498db',
    desk_position_x INT DEFAULT 0,
    desk_position_y INT DEFAULT 0,
    is_project_manager BOOLEAN DEFAULT FALSE,
    is_player_assistant BOOLEAN DEFAULT FALSE,
    status ENUM('available', 'busy', 'in_meeting', 'away') DEFAULT 'available',
    current_task_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Tasks/tickets
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    task_type ENUM('feature', 'bug', 'refactor', 'documentation', 'design', 'research', 'meeting') DEFAULT 'feature',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('backlog', 'todo', 'in_progress', 'review', 'done') DEFAULT 'backlog',
    assigned_to INT,
    recommended_assignee INT,
    estimated_time INT, -- in minutes
    actual_time INT DEFAULT 0,
    day_created INT,
    day_completed INT,
    branch_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES teammates(id) ON DELETE SET NULL,
    FOREIGN KEY (recommended_assignee) REFERENCES teammates(id) ON DELETE SET NULL
);

-- Conversations (chat history for RAG)
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    conversation_type ENUM('standup', 'one_on_one', 'meeting', 'whiteboard', 'coding_session', 'pm_setup') NOT NULL,
    title VARCHAR(255),
    day_number INT,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    summary TEXT,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Messages within conversations
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_type ENUM('player', 'bot', 'system') NOT NULL,
    sender_id INT, -- teammate id if bot
    content TEXT NOT NULL,
    message_type ENUM('text', 'code', 'file', 'action', 'decision') DEFAULT 'text',
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES teammates(id) ON DELETE SET NULL
);

-- Meetings (scheduled by PM)
CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    topic TEXT,
    day_scheduled INT NOT NULL,
    duration INT DEFAULT 30, -- minutes
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    conversation_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL
);

-- Meeting attendees
CREATE TABLE IF NOT EXISTS meeting_attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    teammate_id INT NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    attended BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (teammate_id) REFERENCES teammates(id) ON DELETE CASCADE
);

-- Daily reports generated by PM
CREATE TABLE IF NOT EXISTS daily_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    day_number INT NOT NULL,
    summary TEXT,
    tasks_completed JSON,
    tasks_in_progress JSON,
    blockers JSON,
    next_day_priorities JSON,
    team_notes JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Project documents (generated artifacts)
CREATE TABLE IF NOT EXISTS project_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    doc_type ENUM('kanban', 'gantt', 'timeline', 'architecture', 'readme', 'spec', 'notes', 'whiteboard') NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    version INT DEFAULT 1,
    created_by INT,
    day_created INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES teammates(id) ON DELETE SET NULL
);

-- Coding sessions
CREATE TABLE IF NOT EXISTS coding_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    teammate_id INT NOT NULL,
    task_id INT,
    conversation_id INT,
    branch_name VARCHAR(255),
    files_modified JSON,
    commits JSON,
    status ENUM('active', 'paused', 'completed') DEFAULT 'active',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (teammate_id) REFERENCES teammates(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL
);

-- RAG context chunks for bot memory
CREATE TABLE IF NOT EXISTS context_chunks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    chunk_type ENUM('code', 'conversation', 'document', 'decision', 'architecture') NOT NULL,
    content TEXT NOT NULL,
    embedding_vector BLOB, -- for future vector similarity search
    metadata JSON,
    relevance_score FLOAT DEFAULT 1.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Git commits tracking
CREATE TABLE IF NOT EXISTS git_commits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    coding_session_id INT,
    commit_hash VARCHAR(40),
    branch_name VARCHAR(255),
    commit_message TEXT,
    author_id INT,
    files_changed JSON,
    day_number INT,
    merged BOOLEAN DEFAULT FALSE,
    merge_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (coding_session_id) REFERENCES coding_sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES teammates(id) ON DELETE SET NULL
);

-- Standup updates
CREATE TABLE IF NOT EXISTS standup_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    day_number INT NOT NULL,
    teammate_id INT,
    is_player BOOLEAN DEFAULT FALSE,
    yesterday_work TEXT,
    today_plan TEXT,
    blockers TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (teammate_id) REFERENCES teammates(id) ON DELETE SET NULL
);

-- Indexes for performance
CREATE INDEX idx_messages_conversation ON messages(conversation_id);
CREATE INDEX idx_tasks_project ON tasks(project_id);
CREATE INDEX idx_tasks_status ON tasks(status);
CREATE INDEX idx_teammates_project ON teammates(project_id);
CREATE INDEX idx_context_project ON context_chunks(project_id);
CREATE INDEX idx_commits_project ON git_commits(project_id);
CREATE INDEX idx_game_state_project ON game_state(project_id);
