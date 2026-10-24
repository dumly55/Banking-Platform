<?php

/** Shared PDO connection, opened on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $pdo = new PDO(
            "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        // Keep NOW()/CURDATE() in the same timezone as PHP's date().
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    }
    return $pdo;
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_value(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/** Runs $fn inside a transaction, rolling back if it throws. */
function db_transaction(callable $fn): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Picks a random, unused numeric id of the given length for $table.$column.
 * Ids are random rather than sequential so account and ticket numbers can't be guessed.
 */
function unique_number(string $table, string $column, int $digits, string $prefix = ''): string
{
    $randomDigits = $digits - strlen($prefix);
    for ($i = 0; $i < 20; $i++) {
        $n = $prefix . str_pad((string) random_int(0, 10 ** $randomDigits - 1), $randomDigits, '0', STR_PAD_LEFT);
        if ($prefix === '' && $n[0] === '0') continue;
        if (!db_value("SELECT 1 FROM $table WHERE $column = ?", [$n])) return $n;
    }
    throw new RuntimeException("Could not generate a unique $column");
}
