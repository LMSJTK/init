# Startup Game - Installation Guide

A web-based game where you lead a team of AI developers to build your dream app.

## Requirements

- PHP 8.1+ with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite enabled
- Git (for project repositories)

## Quick Setup (Amazon Linux 2023)

### 1. Install Dependencies

```bash
# Install PHP and MySQL
sudo dnf install php php-pdo php-mysqlnd php-json php-mbstring mysql-server git

# Start MySQL
sudo systemctl start mysqld
sudo systemctl enable mysqld
```

### 2. Create Database

```bash
# Login to MySQL
sudo mysql

# Create database and user
CREATE DATABASE startup_game CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'startup_game'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON startup_game.* TO 'startup_game'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Clone the Game

```bash
# Clone to your web directory (example: as subdirectory)
cd /var/www/html
git clone <your-repo-url> startup-game
cd startup-game

# Set permissions
sudo chown -R apache:apache storage/
sudo chmod -R 755 storage/
```

### 4. Configure

```bash
# Copy and edit config
cp config/config.example.php config/config.php
nano config/config.php
```

Update the database credentials in `config/config.php`:
```php
'database' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'startup_game',
    'user' => 'startup_game',
    'password' => 'your_secure_password',
    'charset' => 'utf8mb4',
],
```

Update the app URL:
```php
'app' => [
    'name' => 'Startup Game',
    'url' => 'http://your-server/startup-game',
    ...
],
```

### 5. Apache Configuration

Add to your Apache config or create a new virtual host:

```apache
<Directory /var/www/html/startup-game/public>
    AllowOverride All
    Require all granted
</Directory>

# If using as subdirectory
Alias /startup-game /var/www/html/startup-game/public
```

Restart Apache:
```bash
sudo systemctl restart httpd
```

### 6. Run Migrations

Visit: `http://your-server/startup-game/migrate`

This will create all database tables.

### 7. Start Playing!

Visit: `http://your-server/startup-game/`

## First Run Setup

1. **API Keys**: Enter at least one AI API key:
   - Anthropic API key (for Claude models)
   - OpenAI API key (for ChatGPT)
   - Google API key (for Gemini)

2. **Project Setup**: Describe what you want to build

3. **Team Selection**: Choose AI models for each team member

4. **Start Playing!**

## Game Flow

1. **Morning**: Review project status and daily reports
2. **Standup**: Share updates with the team
3. **Work Time**:
   - Vibe code at your desk with your AI assistant
   - Talk to teammates at their desks
   - Have whiteboard sessions for brainstorming
   - Attend meetings scheduled by the PM
4. **End of Day**: PM compiles daily report

## Features

- **Multiple AI Models**: Choose from Claude, ChatGPT, and Gemini
- **Real Git Integration**: All code is committed to actual git repositories
- **Project Management**: Kanban boards, task tracking, daily reports
- **Team Dynamics**: Different teammates with different specialties
- **Meetings**: Multi-bot conversations with weighted speaker selection

## GitHub Integration (Optional)

To push your project to GitHub:

1. Create a new repository on GitHub
2. In Settings, add your GitHub Personal Access Token
3. Add the remote URL and click "Link Repository"
4. Approve merges to sync branches

## Directory Structure

```
startup-game/
├── config/
│   └── config.php          # Main configuration
├── migrations/
│   └── 001_initial_schema.sql
├── public/
│   ├── index.php           # Entry point
│   ├── game.html           # Main game UI
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── game.js
├── src/
│   ├── Controllers/
│   │   └── ApiController.php
│   ├── Models/
│   │   └── *.php
│   ├── Services/
│   │   ├── AIService.php
│   │   ├── GitService.php
│   │   └── GameService.php
│   └── Database.php
├── storage/
│   ├── logs/
│   └── git-repos/          # Project repositories stored here
└── templates/
```

## Troubleshooting

### "Database connection failed"
- Check MySQL is running: `sudo systemctl status mysqld`
- Verify credentials in config.php
- Ensure database exists

### "API key not configured"
- Enter at least one API key in Settings
- Keys are encrypted and stored in the database

### "Git operations failing"
- Ensure git is installed: `git --version`
- Check storage/git-repos is writable

### "Mod_rewrite not working"
- Enable mod_rewrite: `sudo a2enmod rewrite` (Debian) or check httpd.conf
- Ensure AllowOverride is set

## License

MIT License
