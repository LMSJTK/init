<?php
/**
 * Base Model Class
 */

namespace StartupGame\Models;

use StartupGame\Database;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    /**
     * Find a record by ID
     */
    public static function find(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM %s WHERE %s = ?', static::$table, static::$primaryKey);
        return Database::queryOne($sql, [$id]);
    }

    /**
     * Find all records
     */
    public static function all(): array
    {
        $sql = sprintf('SELECT * FROM %s', static::$table);
        return Database::query($sql);
    }

    /**
     * Find records by conditions
     */
    public static function where(array $conditions, string $orderBy = null): array
    {
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            if (is_null($value)) {
                $whereClauses[] = "$key IS NULL";
            } else {
                $whereClauses[] = "$key = ?";
                $params[] = $value;
            }
        }

        $sql = sprintf('SELECT * FROM %s WHERE %s', static::$table, implode(' AND ', $whereClauses));

        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }

        return Database::query($sql, $params);
    }

    /**
     * Find first record matching conditions
     */
    public static function findWhere(array $conditions): ?array
    {
        $results = static::where($conditions);
        return $results[0] ?? null;
    }

    /**
     * Create a new record
     */
    public static function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            static::$table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        return Database::insert($sql, array_values($data));
    }

    /**
     * Update a record by ID
     */
    public static function update(int $id, array $data): int
    {
        $setClauses = [];
        $params = [];

        foreach ($data as $key => $value) {
            $setClauses[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            static::$table,
            implode(', ', $setClauses),
            static::$primaryKey
        );

        return Database::execute($sql, $params);
    }

    /**
     * Delete a record by ID
     */
    public static function delete(int $id): int
    {
        $sql = sprintf('DELETE FROM %s WHERE %s = ?', static::$table, static::$primaryKey);
        return Database::execute($sql, [$id]);
    }

    /**
     * Count records matching conditions
     */
    public static function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            $sql = sprintf('SELECT COUNT(*) as count FROM %s', static::$table);
            $result = Database::queryOne($sql);
        } else {
            $whereClauses = [];
            $params = [];

            foreach ($conditions as $key => $value) {
                $whereClauses[] = "$key = ?";
                $params[] = $value;
            }

            $sql = sprintf('SELECT COUNT(*) as count FROM %s WHERE %s', static::$table, implode(' AND ', $whereClauses));
            $result = Database::queryOne($sql, $params);
        }

        return (int) ($result['count'] ?? 0);
    }
}
