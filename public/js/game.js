/**
 * Startup Game - Main JavaScript
 */

class StartupGame {
    constructor() {
        this.projectId = null;
        this.gameState = null;
        this.teammates = [];
        this.currentConversationId = null;
        this.currentSessionId = null;
        this.currentTeammateId = null;
        this.currentMeetingId = null;
        this.chatMode = null; // 'standup', 'one_on_one', 'coding', 'meeting', 'whiteboard'

        this.canvas = null;
        this.ctx = null;

        this.init();
    }

    async init() {
        this.bindEvents();
        await this.checkProject();
    }

    bindEvents() {
        // Setup events
        document.getElementById('setup-keys-btn').addEventListener('click', () => this.saveSetupKeys());
        document.getElementById('setup-project-btn').addEventListener('click', () => this.createProject());
        document.getElementById('setup-team-btn').addEventListener('click', () => this.generateTeam());

        // Settings events
        document.getElementById('settings-btn').addEventListener('click', () => this.openSettings());
        document.getElementById('settings-close').addEventListener('click', () => this.closeModal('settings-modal'));
        document.getElementById('save-settings-btn').addEventListener('click', () => this.saveSettings());
        document.getElementById('link-github-btn').addEventListener('click', () => this.linkGithub());

        // Office events
        document.getElementById('standup-btn').addEventListener('click', () => this.startStandup());
        document.getElementById('my-desk-btn').addEventListener('click', () => this.goToDesk());
        document.getElementById('end-day-btn').addEventListener('click', () => this.endDay());

        // Chat events
        document.getElementById('chat-back-btn').addEventListener('click', () => this.backToOffice());
        document.getElementById('chat-end-btn').addEventListener('click', () => this.endSession());
        document.getElementById('chat-send').addEventListener('click', () => this.sendMessage());
        document.getElementById('chat-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        document.getElementById('standup-submit').addEventListener('click', () => this.submitStandup());
        document.getElementById('chat-commit').addEventListener('click', () => this.openCommitModal());

        // Tab events
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.switchTab(e.target.dataset.tab));
        });

        // Modal close on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) this.closeModal(modal.id);
            });
        });

        // Context menu
        document.querySelectorAll('#teammate-menu .menu-item').forEach(item => {
            item.addEventListener('click', (e) => this.handleTeammateAction(e.target.dataset.action));
        });

        // Report modal
        document.getElementById('report-close').addEventListener('click', () => this.closeModal('report-modal'));
        document.getElementById('next-day-btn').addEventListener('click', () => this.startNextDay());

        // Commit modal
        document.getElementById('commit-close').addEventListener('click', () => this.closeModal('commit-modal'));
        document.getElementById('do-commit-btn').addEventListener('click', () => this.doCommit());

        // Merge modal
        document.getElementById('merge-close').addEventListener('click', () => this.closeModal('merge-modal'));
        document.getElementById('approve-merge-btn').addEventListener('click', () => this.approveMerge());

        // Task modal
        document.getElementById('task-close').addEventListener('click', () => this.closeModal('task-modal'));
        document.getElementById('task-reassign-btn').addEventListener('click', () => this.reassignTask());
        document.getElementById('task-move-btn').addEventListener('click', () => this.moveTaskToNext());

        // Click outside context menu closes it
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#teammate-menu')) {
                document.getElementById('teammate-menu').style.display = 'none';
            }
        });
    }

    // ==================== API Helpers ====================

    async api(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: { 'Content-Type': 'application/json' }
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        let url = endpoint;
        if (method === 'GET' && data) {
            const params = new URLSearchParams(data);
            url += '?' + params.toString();
        }

        const response = await fetch(url, options);
        return await response.json();
    }

    // ==================== Initial Load ====================

    async checkProject() {
        const result = await this.api('/api/project');

        if (result.needs_setup || !result.project) {
            this.showScreen('setup-screen');
            await this.loadSettings();
        } else {
            this.projectId = result.project.id;
            this.loadGame(result.project);
        }
    }

    async loadSettings() {
        const result = await this.api('/api/settings');

        document.getElementById('anthropic-status').className =
            'key-status ' + (result.anthropic_configured ? 'configured' : 'not-configured');
        document.getElementById('openai-status').className =
            'key-status ' + (result.openai_configured ? 'configured' : 'not-configured');
        document.getElementById('gemini-status').className =
            'key-status ' + (result.gemini_configured ? 'configured' : 'not-configured');
    }

    async loadGame(project) {
        this.projectId = project.id;

        // Update header
        document.getElementById('project-name').textContent = project.name;

        if (project.git_remote_url) {
            const gitLink = document.getElementById('git-link');
            gitLink.href = project.git_remote_url.replace('.git', '');
            gitLink.style.display = 'inline-block';
        }

        // Load state
        const stateResult = await this.api('/api/game/state', 'GET', { project_id: this.projectId });
        this.gameState = stateResult.state;
        this.updateGameStateDisplay();

        // Load team
        const teamResult = await this.api('/api/team', 'GET', { project_id: this.projectId });
        this.teammates = [
            teamResult.pm,
            teamResult.assistant,
            ...teamResult.teammates
        ].filter(Boolean);

        // Show office
        this.showScreen('office-screen');
        this.initOfficeCanvas();
        this.loadSidePanelData();
    }

    // ==================== Setup Flow ====================

    async saveSetupKeys() {
        const anthropicKey = document.getElementById('setup-anthropic-key').value;
        const openaiKey = document.getElementById('setup-openai-key').value;
        const geminiKey = document.getElementById('setup-gemini-key').value;

        if (!anthropicKey && !openaiKey && !geminiKey) {
            alert('Please enter at least one API key');
            return;
        }

        await this.api('/api/settings/api-keys', 'POST', {
            anthropic_api_key: anthropicKey,
            openai_api_key: openaiKey,
            gemini_api_key: geminiKey
        });

        // Run migrations first
        await fetch('/migrate');

        this.showSetupStep(2);
    }

    async createProject() {
        const name = document.getElementById('setup-project-name').value;
        const description = document.getElementById('setup-project-desc').value;
        const vision = document.getElementById('setup-project-vision').value;
        const pmModel = document.getElementById('setup-pm-model').value;

        if (!name) {
            alert('Please enter a project name');
            return;
        }

        const result = await this.api('/api/project', 'POST', {
            name, description, vision, pm_model: pmModel
        });

        if (result.success) {
            this.projectId = result.project_id;
            this.buildTeamConfigUI();
            this.showSetupStep(3);
        }
    }

    buildTeamConfigUI() {
        const roles = [
            { key: 'frontend_developer', name: 'Frontend Developer' },
            { key: 'backend_developer', name: 'Backend Developer' },
            { key: 'fullstack_developer', name: 'Fullstack Developer' },
            { key: 'qa_engineer', name: 'QA Engineer' },
            { key: 'assistant', name: 'Your Coding Assistant' }
        ];

        const container = document.getElementById('team-config-container');
        container.innerHTML = '';

        roles.forEach(role => {
            const div = document.createElement('div');
            div.className = 'team-member-config';
            div.innerHTML = `
                <span class="role-name">${role.name}</span>
                <select data-role="${role.key}">
                    <option value="claude-sonnet-4.5">Claude Sonnet 4.5</option>
                    <option value="chatgpt-5.1">ChatGPT 5.1</option>
                    <option value="gemini-3">Gemini 3</option>
                </select>
            `;
            container.appendChild(div);
        });
    }

    async generateTeam() {
        const teamConfig = {};
        document.querySelectorAll('#team-config-container select').forEach(select => {
            teamConfig[select.dataset.role] = select.value;
        });

        await this.api('/api/team/generate', 'POST', {
            project_id: this.projectId,
            team_config: teamConfig
        });

        await this.api('/api/project/activate', 'POST', {
            project_id: this.projectId
        });

        // Load the game
        const result = await this.api('/api/project');
        this.loadGame(result.project);
    }

    showSetupStep(step) {
        document.querySelectorAll('.setup-step').forEach(s => s.classList.remove('active'));
        document.getElementById(`setup-step-${step}`).classList.add('active');
    }

    // ==================== Office Canvas ====================

    initOfficeCanvas() {
        this.canvas = document.getElementById('office-canvas');
        this.ctx = this.canvas.getContext('2d');

        this.resizeCanvas();
        window.addEventListener('resize', () => this.resizeCanvas());

        this.canvas.addEventListener('click', (e) => this.handleCanvasClick(e));
        this.canvas.addEventListener('mousemove', (e) => this.handleCanvasHover(e));

        this.drawOffice();
    }

    resizeCanvas() {
        const container = document.getElementById('office-view');
        this.canvas.width = container.clientWidth;
        this.canvas.height = container.clientHeight;
        this.drawOffice();
    }

    drawOffice() {
        if (!this.ctx) return;

        const ctx = this.ctx;
        const w = this.canvas.width;
        const h = this.canvas.height;

        // Clear
        ctx.fillStyle = '#1a1a2e';
        ctx.fillRect(0, 0, w, h);

        // Draw floor grid
        ctx.strokeStyle = '#252545';
        ctx.lineWidth = 1;
        for (let x = 0; x < w; x += 50) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, h);
            ctx.stroke();
        }
        for (let y = 0; y < h; y += 50) {
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(w, y);
            ctx.stroke();
        }

        // Draw conference room area
        ctx.fillStyle = '#16213e';
        ctx.fillRect(w/2 - 150, 50, 300, 150);
        ctx.strokeStyle = '#0f3460';
        ctx.lineWidth = 2;
        ctx.strokeRect(w/2 - 150, 50, 300, 150);

        // Conference table
        ctx.fillStyle = '#34495e';
        ctx.fillRect(w/2 - 80, 90, 160, 70);

        // Draw desks and teammates
        this.teammates.forEach(tm => {
            if (!tm) return;

            const x = this.scaleX(tm.desk_position_x);
            const y = this.scaleY(tm.desk_position_y);

            // Draw desk
            ctx.fillStyle = '#4a4a6a';
            ctx.fillRect(x - 40, y - 20, 80, 40);

            // Draw teammate (circle)
            ctx.beginPath();
            ctx.arc(x, y - 40, 20, 0, Math.PI * 2);
            ctx.fillStyle = tm.avatar_color;
            ctx.fill();

            // Status indicator
            let statusColor = '#27ae60';
            if (tm.status === 'busy') statusColor = '#f39c12';
            if (tm.status === 'in_meeting') statusColor = '#e94560';

            ctx.beginPath();
            ctx.arc(x + 15, y - 55, 6, 0, Math.PI * 2);
            ctx.fillStyle = statusColor;
            ctx.fill();

            // Name label
            ctx.fillStyle = '#fff';
            ctx.font = '12px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(tm.name.split(' ')[0], x, y + 30);
        });

        // Player's desk
        const playerX = w / 2;
        const playerY = h - 100;

        ctx.fillStyle = '#2ecc71';
        ctx.fillRect(playerX - 50, playerY - 25, 100, 50);
        ctx.fillStyle = '#fff';
        ctx.font = '14px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Your Desk', playerX, playerY + 45);
    }

    scaleX(x) {
        return (x / 800) * this.canvas.width;
    }

    scaleY(y) {
        return (y / 600) * this.canvas.height;
    }

    handleCanvasClick(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        // Check if clicked on a teammate
        for (const tm of this.teammates) {
            if (!tm || tm.is_player_assistant) continue;

            const tmX = this.scaleX(tm.desk_position_x);
            const tmY = this.scaleY(tm.desk_position_y) - 40;

            const dist = Math.sqrt((x - tmX) ** 2 + (y - tmY) ** 2);
            if (dist < 25) {
                this.showTeammateMenu(e.clientX, e.clientY, tm);
                return;
            }
        }

        // Check if clicked on player desk
        const playerX = this.canvas.width / 2;
        const playerY = this.canvas.height - 100;
        if (x > playerX - 50 && x < playerX + 50 && y > playerY - 25 && y < playerY + 25) {
            this.goToDesk();
        }
    }

    handleCanvasHover(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        let tooltip = document.querySelector('.teammate-tooltip');

        for (const tm of this.teammates) {
            if (!tm) continue;

            const tmX = this.scaleX(tm.desk_position_x);
            const tmY = this.scaleY(tm.desk_position_y) - 40;

            const dist = Math.sqrt((x - tmX) ** 2 + (y - tmY) ** 2);
            if (dist < 25) {
                if (!tooltip) {
                    tooltip = document.createElement('div');
                    tooltip.className = 'teammate-tooltip';
                    document.body.appendChild(tooltip);
                }

                tooltip.innerHTML = `
                    <div class="name">${tm.name}</div>
                    <div class="role">${tm.role}</div>
                    <div class="status ${tm.status}">${tm.status}</div>
                `;
                tooltip.style.left = (e.clientX + 15) + 'px';
                tooltip.style.top = (e.clientY + 15) + 'px';
                tooltip.style.display = 'block';
                return;
            }
        }

        if (tooltip) tooltip.style.display = 'none';
    }

    showTeammateMenu(x, y, teammate) {
        this.currentTeammateId = teammate.id;
        const menu = document.getElementById('teammate-menu');
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
        menu.style.display = 'block';
    }

    handleTeammateAction(action) {
        document.getElementById('teammate-menu').style.display = 'none';

        switch (action) {
            case 'talk':
                this.startOneOnOne();
                break;
            case 'whiteboard':
                this.startWhiteboard();
                break;
            case 'assign':
                // TODO: Show task assignment modal
                break;
        }
    }

    // ==================== Game State Display ====================

    updateGameStateDisplay() {
        if (!this.gameState) return;

        document.getElementById('day-display').textContent = `Day ${this.gameState.current_day}`;
        document.getElementById('time-display').textContent = this.formatTime(this.gameState.time_remaining);
        document.getElementById('phase-display').textContent = this.capitalize(this.gameState.day_phase);
    }

    formatTime(minutes) {
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return `${h}:${m.toString().padStart(2, '0')}`;
    }

    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ');
    }

    // ==================== Side Panel ====================

    async loadSidePanelData() {
        await Promise.all([
            this.loadTasks(),
            this.loadMeetings(),
            this.loadDocs(),
            this.loadGitStatus()
        ]);
    }

    switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.toggle('active', content.id === `tab-${tabName}`);
        });
    }

    async loadTasks() {
        const result = await this.api('/api/tasks/kanban', 'GET', { project_id: this.projectId });

        ['todo', 'in_progress', 'done'].forEach(status => {
            const container = document.getElementById(`tasks-${status.replace('_', '-')}`);
            container.innerHTML = '';

            const tasks = result.kanban[status] || [];
            tasks.forEach(task => {
                const card = document.createElement('div');
                card.className = `task-card priority-${task.priority}`;
                card.innerHTML = `
                    <div class="task-title">${task.title}</div>
                    <div class="task-meta">
                        <span>${task.task_type}</span>
                        <span>${task.assignee_name || 'Unassigned'}</span>
                    </div>
                `;
                card.addEventListener('click', () => this.showTaskDetails(task));
                container.appendChild(card);
            });
        });
    }

    async loadMeetings() {
        const result = await this.api('/api/meetings', 'GET', { project_id: this.projectId });
        const container = document.getElementById('meetings-list');
        container.innerHTML = '';

        if (!result.meetings || result.meetings.length === 0) {
            container.innerHTML = '<p style="color: var(--text-secondary)">No meetings scheduled today</p>';
            return;
        }

        result.meetings.forEach(meeting => {
            const item = document.createElement('div');
            item.className = 'meeting-item';
            item.innerHTML = `
                <div class="meeting-title">${meeting.title}</div>
                <div class="meeting-info">${meeting.duration} min - ${meeting.attendee_names || 'Team'}</div>
            `;
            item.addEventListener('click', () => this.joinMeeting(meeting.id));
            container.appendChild(item);
        });
    }

    async loadDocs() {
        const result = await this.api('/api/documents', 'GET', { project_id: this.projectId });
        const container = document.getElementById('docs-list');
        container.innerHTML = '';

        if (!result.documents || result.documents.length === 0) {
            container.innerHTML = '<p style="color: var(--text-secondary)">No documents yet</p>';
            return;
        }

        const icons = {
            kanban: '📋',
            gantt: '📊',
            whiteboard: '🖼️',
            architecture: '🏗️',
            spec: '📄',
            notes: '📝'
        };

        result.documents.forEach(doc => {
            const item = document.createElement('div');
            item.className = 'doc-item';
            item.innerHTML = `
                <span class="doc-icon">${icons[doc.doc_type] || '📄'}</span>
                <span class="doc-name">${doc.title}</span>
            `;
            item.addEventListener('click', () => this.viewDocument(doc.id));
            container.appendChild(item);
        });
    }

    async loadGitStatus() {
        const [statusResult, mergesResult] = await Promise.all([
            this.api('/api/git/status', 'GET', { project_id: this.projectId }),
            this.api('/api/git/pending-merges', 'GET', { project_id: this.projectId })
        ]);

        // Git status
        const statusContainer = document.getElementById('git-status');
        if (statusResult.status && statusResult.status.length > 0) {
            statusContainer.innerHTML = '<h4>Uncommitted Changes</h4>' +
                statusResult.status.map(f => `<div>${f.status} ${f.file}</div>`).join('');
        } else {
            statusContainer.innerHTML = '<p style="color: var(--text-secondary)">Working tree clean</p>';
        }

        // Pending merges
        const mergesContainer = document.getElementById('pending-merges');
        if (mergesResult.merges && mergesResult.merges.length > 0) {
            mergesContainer.innerHTML = '<h4>Pending Merges</h4>';
            mergesResult.merges.forEach(merge => {
                const item = document.createElement('div');
                item.className = 'merge-item';
                item.innerHTML = `
                    <div class="branch-name">${merge.branch_name}</div>
                    <div class="commit-msg">${merge.commit_message}</div>
                `;
                item.addEventListener('click', () => this.showMergeModal(merge));
                mergesContainer.appendChild(item);
            });
        } else {
            mergesContainer.innerHTML = '';
        }
    }

    // ==================== Standup ====================

    async startStandup() {
        const result = await this.api('/api/standup/start', 'POST', { project_id: this.projectId });
        this.currentConversationId = result.conversation_id;
        this.chatMode = 'standup';

        this.showScreen('chat-screen');
        this.setupChatHeader('Daily Standup', 'Share your updates with the team');

        document.getElementById('standup-form').style.display = 'flex';
        document.getElementById('chat-form').style.display = 'none';
        document.getElementById('chat-messages').innerHTML = '';
    }

    async submitStandup() {
        const yesterday = document.getElementById('standup-yesterday').value;
        const today = document.getElementById('standup-today').value;
        const blockers = document.getElementById('standup-blockers').value;

        document.getElementById('standup-form').style.display = 'none';

        // Show player's update
        this.addMessage('player', `Yesterday: ${yesterday}\nToday: ${today}\nBlockers: ${blockers || 'None'}`);

        const result = await this.api('/api/standup/update', 'POST', {
            project_id: this.projectId,
            conversation_id: this.currentConversationId,
            yesterday, today, blockers
        });

        // Show teammate responses
        if (result.teammate_responses) {
            for (const response of result.teammate_responses) {
                const tm = this.teammates.find(t => t && t.id === response.teammate_id);
                const name = tm ? tm.name : 'Teammate';
                this.addMessage('bot', `Yesterday: ${response.yesterday}\nToday: ${response.today}\nBlockers: ${response.blockers}`, name);
            }
        }

        // Show PM summary
        if (result.pm_summary) {
            const pm = this.teammates.find(t => t && t.is_project_manager);
            this.addMessage('bot', result.pm_summary.summary, pm ? pm.name : 'Project Manager');
        }

        // Update game state
        await this.refreshGameState();

        // Show return button
        document.getElementById('chat-form').style.display = 'flex';
        document.getElementById('chat-input').placeholder = 'Standup complete. Click Back to Office to continue.';
        document.getElementById('chat-input').disabled = true;
        document.getElementById('chat-send').style.display = 'none';
    }

    // ==================== One-on-One ====================

    async startOneOnOne() {
        const result = await this.api('/api/conversation/one-on-one/start', 'POST', {
            project_id: this.projectId,
            teammate_id: this.currentTeammateId
        });

        this.currentConversationId = result.conversation_id;
        this.chatMode = 'one_on_one';

        this.showScreen('chat-screen');
        this.setupChatHeader(result.teammate.name, result.teammate.role);

        document.getElementById('standup-form').style.display = 'none';
        document.getElementById('chat-form').style.display = 'flex';
        document.getElementById('chat-input').disabled = false;
        document.getElementById('chat-send').style.display = 'inline-block';
        document.getElementById('chat-commit').style.display = 'none';
        document.getElementById('chat-messages').innerHTML = '';
    }

    // ==================== Coding Session ====================

    async goToDesk() {
        const assistant = this.teammates.find(t => t && t.is_player_assistant);
        if (!assistant) {
            alert('No coding assistant assigned');
            return;
        }

        const result = await this.api('/api/coding/start', 'POST', {
            project_id: this.projectId
        });

        this.currentConversationId = result.conversation_id;
        this.currentSessionId = result.session_id;
        this.currentTeammateId = result.assistant.id;
        this.chatMode = 'coding';

        this.showScreen('chat-screen');
        this.setupChatHeader('Coding Session', result.task ? `Working on: ${result.task.title}` : 'Vibe coding');

        document.getElementById('standup-form').style.display = 'none';
        document.getElementById('chat-form').style.display = 'flex';
        document.getElementById('chat-input').disabled = false;
        document.getElementById('chat-send').style.display = 'inline-block';
        document.getElementById('chat-commit').style.display = 'inline-block';
        document.getElementById('chat-messages').innerHTML = '';

        this.addMessage('bot', "Hey! Ready to code. What would you like to work on?", result.assistant.name);
    }

    // ==================== Meeting ====================

    async joinMeeting(meetingId) {
        const result = await this.api('/api/meeting/start', 'POST', {
            project_id: this.projectId,
            meeting_id: meetingId
        });

        this.currentConversationId = result.conversation_id;
        this.currentMeetingId = meetingId;
        this.chatMode = 'meeting';

        this.showScreen('chat-screen');
        this.setupChatHeader(result.meeting.title, `Topic: ${result.meeting.topic}`);

        document.getElementById('standup-form').style.display = 'none';
        document.getElementById('chat-form').style.display = 'flex';
        document.getElementById('chat-input').disabled = false;
        document.getElementById('chat-send').style.display = 'inline-block';
        document.getElementById('chat-commit').style.display = 'none';
        document.getElementById('chat-messages').innerHTML = '';

        // Show PM opening
        const pm = this.teammates.find(t => t && t.is_project_manager);
        this.addMessage('bot', result.opening, pm ? pm.name : 'Project Manager');
    }

    // ==================== Whiteboard ====================

    async startWhiteboard() {
        const topic = prompt('What topic would you like to whiteboard?');
        if (!topic) return;

        const result = await this.api('/api/whiteboard/start', 'POST', {
            project_id: this.projectId,
            teammate_id: this.currentTeammateId,
            topic
        });

        this.currentConversationId = result.conversation_id;
        this.chatMode = 'whiteboard';

        this.showScreen('chat-screen');
        this.setupChatHeader(`Whiteboard: ${topic}`, `With ${result.teammate.name}`);

        document.getElementById('standup-form').style.display = 'none';
        document.getElementById('chat-form').style.display = 'flex';
        document.getElementById('chat-input').disabled = false;
        document.getElementById('chat-send').style.display = 'inline-block';
        document.getElementById('chat-commit').style.display = 'none';
        document.getElementById('chat-messages').innerHTML = '';
    }

    // ==================== Chat Interface ====================

    setupChatHeader(name, context) {
        document.getElementById('chat-with-name').textContent = name;
        document.getElementById('chat-context').textContent = context;
    }

    async sendMessage() {
        const input = document.getElementById('chat-input');
        const message = input.value.trim();
        if (!message) return;

        input.value = '';
        this.addMessage('player', message);

        let endpoint, data;
        switch (this.chatMode) {
            case 'one_on_one':
                endpoint = '/api/conversation/one-on-one/message';
                data = {
                    project_id: this.projectId,
                    conversation_id: this.currentConversationId,
                    teammate_id: this.currentTeammateId,
                    message
                };
                break;
            case 'coding':
                endpoint = '/api/coding/message';
                data = {
                    project_id: this.projectId,
                    session_id: this.currentSessionId,
                    conversation_id: this.currentConversationId,
                    message
                };
                break;
            case 'meeting':
                endpoint = '/api/meeting/message';
                data = {
                    project_id: this.projectId,
                    meeting_id: this.currentMeetingId,
                    conversation_id: this.currentConversationId,
                    message
                };
                break;
            case 'whiteboard':
                endpoint = '/api/whiteboard/message';
                data = {
                    project_id: this.projectId,
                    conversation_id: this.currentConversationId,
                    teammate_id: this.currentTeammateId,
                    message
                };
                break;
        }

        const result = await this.api(endpoint, 'POST', data);

        if (result.response) {
            const speaker = result.speaker || result.teammate;
            this.addMessage('bot', result.response, speaker ? speaker.name : 'Assistant');
        }

        if (result.files_written && result.files_written.length > 0) {
            this.addMessage('system', `Files written: ${result.files_written.join(', ')}`);
        }
    }

    addMessage(type, content, senderName = null) {
        const container = document.getElementById('chat-messages');
        const msg = document.createElement('div');
        msg.className = `message ${type}`;

        let html = '';
        if (senderName && type === 'bot') {
            html += `<span class="sender-name">${senderName}</span>`;
        }

        // Format code blocks
        const formattedContent = this.formatMessageContent(content);
        html += `<div class="content">${formattedContent}</div>`;

        msg.innerHTML = html;
        container.appendChild(msg);
        container.scrollTop = container.scrollHeight;
    }

    formatMessageContent(content) {
        // Escape HTML
        let formatted = content.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        // Format code blocks
        formatted = formatted.replace(/```(\w*)\n([\s\S]*?)```/g, (match, lang, code) => {
            return `<pre><code class="language-${lang}">${code}</code></pre>`;
        });

        // Format inline code
        formatted = formatted.replace(/`([^`]+)`/g, '<code>$1</code>');

        // Line breaks
        formatted = formatted.replace(/\n/g, '<br>');

        return formatted;
    }

    async endSession() {
        let endpoint, data;
        switch (this.chatMode) {
            case 'one_on_one':
            case 'whiteboard':
                endpoint = this.chatMode === 'whiteboard' ? '/api/whiteboard/end' : '/api/conversation/one-on-one/end';
                data = {
                    project_id: this.projectId,
                    conversation_id: this.currentConversationId,
                    teammate_id: this.currentTeammateId
                };
                break;
            case 'coding':
                endpoint = '/api/coding/end';
                data = {
                    project_id: this.projectId,
                    session_id: this.currentSessionId
                };
                break;
            case 'meeting':
                endpoint = '/api/meeting/end';
                data = {
                    project_id: this.projectId,
                    meeting_id: this.currentMeetingId
                };
                break;
        }

        await this.api(endpoint, 'POST', data);
        this.backToOffice();
    }

    backToOffice() {
        this.chatMode = null;
        this.currentConversationId = null;
        this.currentSessionId = null;
        this.currentMeetingId = null;

        this.showScreen('office-screen');
        this.refreshGameState();
        this.loadSidePanelData();
        this.drawOffice();
    }

    // ==================== Commit ====================

    openCommitModal() {
        document.getElementById('commit-modal').style.display = 'flex';
    }

    async doCommit() {
        const message = document.getElementById('commit-message').value;
        if (!message) {
            alert('Please enter a commit message');
            return;
        }

        const result = await this.api('/api/coding/commit', 'POST', {
            project_id: this.projectId,
            session_id: this.currentSessionId,
            message
        });

        this.closeModal('commit-modal');
        document.getElementById('commit-message').value = '';

        if (result.success) {
            this.addMessage('system', `Committed: ${message}\nHash: ${result.hash}\nFiles: ${result.files.join(', ')}`);
        } else {
            this.addMessage('system', `Commit failed: ${result.error}`);
        }
    }

    // ==================== Merge ====================

    showMergeModal(merge) {
        this.currentMerge = merge;
        document.getElementById('merge-details').innerHTML = `
            <p><strong>Branch:</strong> ${merge.branch_name}</p>
            <p><strong>Message:</strong> ${merge.commit_message}</p>
            <p><strong>Files:</strong> ${JSON.parse(merge.files_changed || '[]').join(', ')}</p>
        `;
        document.getElementById('merge-modal').style.display = 'flex';
    }

    async approveMerge() {
        const result = await this.api('/api/git/merge', 'POST', {
            project_id: this.projectId,
            branch: this.currentMerge.branch_name,
            commit_id: this.currentMerge.id
        });

        this.closeModal('merge-modal');

        if (result.success) {
            alert('Merge successful!');
            this.loadGitStatus();
        } else {
            alert('Merge failed: ' + result.error);
        }
    }

    // ==================== End Day ====================

    async endDay() {
        if (!confirm('End the day? All incomplete work will carry over to tomorrow.')) return;

        const result = await this.api('/api/game/day/end', 'POST', { project_id: this.projectId });

        document.getElementById('report-content').innerHTML = `
            <h4>Day ${this.gameState.current_day} Summary</h4>
            <p>${result.pm_summary || 'No summary available.'}</p>
        `;

        document.getElementById('report-modal').style.display = 'flex';
    }

    async startNextDay() {
        this.closeModal('report-modal');
        await this.refreshGameState();
        this.loadSidePanelData();
    }

    async refreshGameState() {
        const result = await this.api('/api/game/state', 'GET', { project_id: this.projectId });
        this.gameState = result.state;
        this.updateGameStateDisplay();
    }

    // ==================== Settings ====================

    async openSettings() {
        await this.loadSettings();
        document.getElementById('settings-modal').style.display = 'flex';
    }

    async saveSettings() {
        const anthropicKey = document.getElementById('settings-anthropic-key').value;
        const openaiKey = document.getElementById('settings-openai-key').value;
        const geminiKey = document.getElementById('settings-gemini-key').value;
        const githubToken = document.getElementById('settings-github-token').value;

        await this.api('/api/settings/api-keys', 'POST', {
            anthropic_api_key: anthropicKey,
            openai_api_key: openaiKey,
            gemini_api_key: geminiKey,
            github_token: githubToken
        });

        await this.loadSettings();
        alert('Settings saved!');
    }

    async linkGithub() {
        const remoteUrl = document.getElementById('settings-git-remote').value;
        if (!remoteUrl) {
            alert('Please enter a remote repository URL');
            return;
        }

        const result = await this.api('/api/git/link', 'POST', {
            project_id: this.projectId,
            remote_url: remoteUrl
        });

        if (result.success) {
            alert('Repository linked!');
            const gitLink = document.getElementById('git-link');
            gitLink.href = remoteUrl.replace('.git', '');
            gitLink.style.display = 'inline-block';
        } else {
            alert('Failed to link: ' + result.error);
        }
    }

    // ==================== Utilities ====================

    showScreen(screenId) {
        document.querySelectorAll('.screen').forEach(s => s.style.display = 'none');
        document.getElementById(screenId).style.display = 'block';
    }

    closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    showTaskDetails(task) {
        this.currentTask = task;

        // Populate modal fields
        document.getElementById('task-modal-title').textContent = task.title;
        document.getElementById('task-detail-status').textContent = this.formatStatus(task.status);
        document.getElementById('task-detail-status').className = `task-status-badge status-${task.status}`;
        document.getElementById('task-detail-priority').textContent = this.capitalize(task.priority);
        document.getElementById('task-detail-priority').className = `task-priority-badge priority-${task.priority}`;
        document.getElementById('task-detail-type').textContent = this.capitalize(task.task_type);
        document.getElementById('task-detail-assignee').textContent = task.assignee_name || 'Unassigned';
        document.getElementById('task-detail-time').textContent = task.estimated_time ? `${task.estimated_time} min` : 'Not estimated';
        document.getElementById('task-detail-description').textContent = task.description || 'No description provided.';

        // Populate reassign dropdown with teammates
        const select = document.getElementById('task-reassign-select');
        select.innerHTML = '<option value="">-- Select Teammate --</option>';
        this.teammates.forEach(tm => {
            if (tm && !tm.is_project_manager && !tm.is_player_assistant) {
                const option = document.createElement('option');
                option.value = tm.id;
                option.textContent = `${tm.name} (${tm.role})`;
                if (task.assigned_to === tm.id) {
                    option.selected = true;
                }
                select.appendChild(option);
            }
        });

        // Show/hide move button based on status
        const moveBtn = document.getElementById('task-move-btn');
        if (task.status === 'done') {
            moveBtn.style.display = 'none';
        } else {
            moveBtn.style.display = 'inline-block';
            const nextStatus = {
                'backlog': 'To Do',
                'todo': 'In Progress',
                'in_progress': 'Review',
                'review': 'Done'
            };
            moveBtn.textContent = `Move to ${nextStatus[task.status] || 'Next'}`;
        }

        document.getElementById('task-modal').style.display = 'flex';
    }

    formatStatus(status) {
        const statusNames = {
            'backlog': 'Backlog',
            'todo': 'To Do',
            'in_progress': 'In Progress',
            'review': 'Review',
            'done': 'Done'
        };
        return statusNames[status] || status;
    }

    async reassignTask() {
        const teammateId = document.getElementById('task-reassign-select').value;
        if (!teammateId) {
            alert('Please select a teammate to assign');
            return;
        }

        await this.api('/api/task/assign', 'POST', {
            task_id: this.currentTask.id,
            teammate_id: parseInt(teammateId)
        });

        this.closeModal('task-modal');
        await this.loadTasks();
    }

    async moveTaskToNext() {
        await this.api('/api/task/move', 'POST', {
            task_id: this.currentTask.id
        });

        this.closeModal('task-modal');
        await this.loadTasks();
    }

    viewDocument(docId) {
        // TODO: Implement document viewer
        console.log('View document:', docId);
    }
}

// Initialize game when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.game = new StartupGame();
});
