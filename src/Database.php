<?php
/**
 * Database Connection Handler using PDO
 */

namespace StartupGame;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Get the PDO database connection instance
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    /**
     * Initialize database configuration
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Create database connection
     */
    private static function connect(): void
    {
        if (empty(self::$config)) {
            throw new \RuntimeException('Database configuration not initialized. Call Database::init() first.');
        }

        $config = self::$config;

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'] ?? 3306,
            $config['name'],
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            self::$instance = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Run a query and return results
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Run a query and return single row
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Execute a statement and return affected rows
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Insert and return last insert ID
     */
    public static function insert(string $sql, array $params = []): int
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * Begin a transaction
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * Rollback a transaction
     */
    public static function rollback(): bool
    {
        return self::getInstance()->rollBack();
    }

    /**
     * Run database migrations
     */
    public static function migrate(string $migrationsPath): array
    {
        $results = [];
        $files = glob($migrationsPath . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);
            $sql = file_get_contents($file);

            try {
                // Split by semicolon for multiple statements
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    fn($s) => !empty($s) && !str_starts_with($s, '--')
                );

                foreach ($statements as $statement) {
                    if (!empty(trim($statement))) {
                        self::getInstance()->exec($statement);
                    }
                }
                $results[$filename] = 'success';
            } catch (PDOException $e) {
                $results[$filename] = 'error: ' . $e->getMessage();
            }
        }

        return $results;
    }
}
