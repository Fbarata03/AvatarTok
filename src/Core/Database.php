<?php

declare(strict_types=1);

namespace AvatarTok\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'],
            $_ENV['DB_PORT'] ?? 3306,
            $_ENV['DB_NAME']
        );

        try {
            self::$instance = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }

        return self::$instance;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        foreach ($params as $key => $val) {
            $paramType = PDO::PARAM_STR;
            if (is_int($val)) {
                $paramType = PDO::PARAM_INT;
            } elseif (is_bool($val)) {
                $paramType = PDO::PARAM_BOOL;
            } elseif (is_null($val)) {
                $paramType = PDO::PARAM_NULL;
            }
            $paramKey = is_int($key) ? $key + 1 : $key;
            $stmt->bindValue($paramKey, $val, $paramType);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function fetchOne(string $sql, array $params = []): mixed
    {
        return self::query($sql, $params)->fetch();
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): string
    {
        $cols        = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
        self::query("INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})", $data);
        return self::connection()->lastInsertId();
    }

    public static function update(string $table, array $data, array $where): int
    {
        $set      = implode(', ', array_map(fn($k) => "{$k} = :set_{$k}", array_keys($data)));
        $cond     = implode(' AND ', array_map(fn($k) => "{$k} = :where_{$k}", array_keys($where)));
        $params   = array_merge(
            array_combine(array_map(fn($k) => "set_{$k}", array_keys($data)), $data),
            array_combine(array_map(fn($k) => "where_{$k}", array_keys($where)), $where)
        );
        $stmt = self::query("UPDATE {$table} SET {$set} WHERE {$cond}", $params);
        return $stmt->rowCount();
    }

    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::connection()->commit();
    }

    public static function rollback(): void
    {
        self::connection()->rollBack();
    }
}
