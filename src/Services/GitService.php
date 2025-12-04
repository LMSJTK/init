<?php
/**
 * Git Service - Handles local and remote git operations
 */

namespace StartupGame\Services;

use StartupGame\Models\Setting;
use StartupGame\Models\GitCommit;

class GitService
{
    private string $reposPath;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->reposPath = $config['storage']['git_repos'];
    }

    /**
     * Initialize a new repository for a project
     */
    public function initRepository(int $projectId, string $projectName): array
    {
        $repoPath = $this->getRepoPath($projectId);

        // Create directory
        if (!is_dir($repoPath)) {
            mkdir($repoPath, 0755, true);
        }

        // Initialize git repo
        $result = $this->runGit($repoPath, 'init');
        if (!$result['success']) {
            return $result;
        }

        // Configure git
        $authorName = $this->config['git']['author_name'];
        $authorEmail = $this->config['git']['author_email'];

        $this->runGit($repoPath, "config user.name \"$authorName\"");
        $this->runGit($repoPath, "config user.email \"$authorEmail\"");

        // Create initial README
        $readme = "# $projectName\n\nThis project was created with Startup Game.\n";
        file_put_contents("$repoPath/README.md", $readme);

        // Initial commit
        $this->runGit($repoPath, 'add .');
        $this->runGit($repoPath, 'commit -m "Initial commit"');

        return [
            'success' => true,
            'path' => $repoPath
        ];
    }

    /**
     * Create a new feature branch
     */
    public function createBranch(int $projectId, string $branchName): array
    {
        $repoPath = $this->getRepoPath($projectId);

        // Make sure we're on main first
        $this->runGit($repoPath, 'checkout ' . $this->config['git']['default_branch']);

        // Create and checkout new branch
        return $this->runGit($repoPath, "checkout -b $branchName");
    }

    /**
     * Switch to a branch
     */
    public function checkout(int $projectId, string $branchName): array
    {
        $repoPath = $this->getRepoPath($projectId);
        return $this->runGit($repoPath, "checkout $branchName");
    }

    /**
     * Write a file to the repository
     */
    public function writeFile(int $projectId, string $filepath, string $content): bool
    {
        $repoPath = $this->getRepoPath($projectId);
        $fullPath = "$repoPath/$filepath";

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($fullPath, $content) !== false;
    }

    /**
     * Read a file from the repository
     */
    public function readFile(int $projectId, string $filepath): ?string
    {
        $repoPath = $this->getRepoPath($projectId);
        $fullPath = "$repoPath/$filepath";

        if (!file_exists($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath);
    }

    /**
     * Delete a file from the repository
     */
    public function deleteFile(int $projectId, string $filepath): bool
    {
        $repoPath = $this->getRepoPath($projectId);
        $fullPath = "$repoPath/$filepath";

        if (file_exists($fullPath)) {
            unlink($fullPath);
            $this->runGit($repoPath, "add $filepath");
            return true;
        }
        return false;
    }

    /**
     * Stage and commit changes
     */
    public function commit(
        int $projectId,
        string $message,
        int $authorId,
        int $codingSessionId,
        int $dayNumber
    ): array {
        $repoPath = $this->getRepoPath($projectId);

        // Stage all changes
        $this->runGit($repoPath, 'add .');

        // Check if there are changes to commit
        $status = $this->runGit($repoPath, 'status --porcelain');
        if (empty(trim($status['output']))) {
            return ['success' => false, 'error' => 'No changes to commit'];
        }

        // Commit
        $result = $this->runGit($repoPath, "commit -m \"$message\"");
        if (!$result['success']) {
            return $result;
        }

        // Get commit hash
        $hashResult = $this->runGit($repoPath, 'rev-parse HEAD');
        $commitHash = trim($hashResult['output']);

        // Get current branch
        $branchResult = $this->runGit($repoPath, 'branch --show-current');
        $branchName = trim($branchResult['output']);

        // Get changed files
        $diffResult = $this->runGit($repoPath, 'diff-tree --no-commit-id --name-only -r HEAD');
        $filesChanged = array_filter(explode("\n", trim($diffResult['output'])));

        // Record in database
        GitCommit::record(
            $projectId,
            $codingSessionId,
            $commitHash,
            $branchName,
            $message,
            $authorId,
            $filesChanged,
            $dayNumber
        );

        return [
            'success' => true,
            'hash' => $commitHash,
            'branch' => $branchName,
            'files' => $filesChanged
        ];
    }

    /**
     * Get repository status
     */
    public function getStatus(int $projectId): array
    {
        $repoPath = $this->getRepoPath($projectId);
        $result = $this->runGit($repoPath, 'status --porcelain');

        $files = [];
        foreach (explode("\n", trim($result['output'])) as $line) {
            if (empty($line)) continue;
            $status = substr($line, 0, 2);
            $file = trim(substr($line, 3));
            $files[] = ['status' => trim($status), 'file' => $file];
        }

        return $files;
    }

    /**
     * Get list of branches
     */
    public function getBranches(int $projectId): array
    {
        $repoPath = $this->getRepoPath($projectId);
        $result = $this->runGit($repoPath, 'branch -a');

        $branches = [];
        foreach (explode("\n", trim($result['output'])) as $line) {
            if (empty($line)) continue;
            $current = str_starts_with($line, '*');
            $name = trim(str_replace('*', '', $line));
            $branches[] = ['name' => $name, 'current' => $current];
        }

        return $branches;
    }

    /**
     * Get commit log
     */
    public function getLog(int $projectId, int $limit = 10): array
    {
        $repoPath = $this->getRepoPath($projectId);
        $result = $this->runGit($repoPath, "log --oneline -n $limit");

        $commits = [];
        foreach (explode("\n", trim($result['output'])) as $line) {
            if (empty($line)) continue;
            $parts = explode(' ', $line, 2);
            $commits[] = [
                'hash' => $parts[0],
                'message' => $parts[1] ?? ''
            ];
        }

        return $commits;
    }

    /**
     * Merge a branch into main
     */
    public function mergeBranch(int $projectId, string $branchName): array
    {
        $repoPath = $this->getRepoPath($projectId);
        $mainBranch = $this->config['git']['default_branch'];

        // Checkout main
        $this->runGit($repoPath, "checkout $mainBranch");

        // Merge
        $result = $this->runGit($repoPath, "merge $branchName --no-ff -m \"Merge branch '$branchName'\"");

        if (!$result['success']) {
            // Abort merge on conflict
            $this->runGit($repoPath, 'merge --abort');
            return [
                'success' => false,
                'error' => 'Merge conflict detected. Please resolve manually.'
            ];
        }

        return [
            'success' => true,
            'message' => "Successfully merged $branchName into $mainBranch"
        ];
    }

    /**
     * Link to remote GitHub repository
     */
    public function linkRemote(int $projectId, string $remoteUrl): array
    {
        $repoPath = $this->getRepoPath($projectId);

        // Check if remote exists
        $existingRemote = $this->runGit($repoPath, 'remote get-url origin 2>/dev/null');

        if (!empty(trim($existingRemote['output']))) {
            $this->runGit($repoPath, 'remote remove origin');
        }

        return $this->runGit($repoPath, "remote add origin $remoteUrl");
    }

    /**
     * Push to remote
     */
    public function push(int $projectId, string $branch = null): array
    {
        $repoPath = $this->getRepoPath($projectId);
        $branch = $branch ?? $this->config['git']['default_branch'];

        return $this->runGit($repoPath, "push -u origin $branch");
    }

    /**
     * Get diff for a file
     */
    public function getDiff(int $projectId, string $filepath = null): string
    {
        $repoPath = $this->getRepoPath($projectId);
        $cmd = $filepath ? "diff HEAD -- $filepath" : 'diff HEAD';
        $result = $this->runGit($repoPath, $cmd);
        return $result['output'];
    }

    /**
     * List all files in repository
     */
    public function listFiles(int $projectId, string $path = ''): array
    {
        $repoPath = $this->getRepoPath($projectId);
        $fullPath = $path ? "$repoPath/$path" : $repoPath;

        if (!is_dir($fullPath)) {
            return [];
        }

        $files = [];
        $items = scandir($fullPath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.git') {
                continue;
            }

            $itemPath = $path ? "$path/$item" : $item;
            $fullItemPath = "$repoPath/$itemPath";

            $files[] = [
                'name' => $item,
                'path' => $itemPath,
                'type' => is_dir($fullItemPath) ? 'directory' : 'file',
                'size' => is_file($fullItemPath) ? filesize($fullItemPath) : null
            ];
        }

        return $files;
    }

    /**
     * Get path for a project's repository
     */
    public function getRepoPath(int $projectId): string
    {
        return $this->reposPath . '/project_' . $projectId;
    }

    /**
     * Run a git command
     */
    private function runGit(string $repoPath, string $command): array
    {
        $fullCommand = "cd " . escapeshellarg($repoPath) . " && git $command 2>&1";
        $output = shell_exec($fullCommand);

        return [
            'success' => true, // Simplified - in production check exit code
            'output' => $output ?? ''
        ];
    }
}
