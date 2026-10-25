<?php
/**
 * Princess Beauty Salon — database access
 *
 * A single PDO connection plus thin query helpers. Every statement is
 * prepared; no caller ever concatenates a value into SQL.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Shared PDO connection.
 *
 * ERRMODE_EXCEPTION so a mistake is loud rather than silently returning false.
 * Emulated prepares are OFF so LIMIT/OFFSET bind as real integers and the
 * driver reports true column types instead of stringifying everything.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        if (APP_DEBUG) {
            throw $e;
        }
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        exit('Service temporarily unavailable.');
    }

    /* MariaDB session hardening: reject silent truncation and zero dates. */
    $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

    return $pdo;
}

/** Run a prepared statement and return the statement. */
function db_run(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** All rows. */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/** First row, or null. */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** First column of the first row, or null. */
function db_val(string $sql, array $params = [])
{
    $v = db_run($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** INSERT helper returning the new id. */
function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s)',
        $table,
        implode(', ', array_map(fn($c) => "`$c`", $cols)),
        implode(', ', array_map(fn($c) => ":$c", $cols))
    );
    db_run($sql, $data);
    return (int) db()->lastInsertId();
}

/** UPDATE helper. $where is a column => value map, ANDed. Returns rows affected. */
function db_update(string $table, array $data, array $where): int
{
    $set = implode(', ', array_map(fn($c) => "`$c` = :set_$c", array_keys($data)));
    $cond = implode(' AND ', array_map(fn($c) => "`$c` = :whr_$c", array_keys($where)));

    $params = [];
    foreach ($data as $k => $v)  { $params["set_$k"] = $v; }
    foreach ($where as $k => $v) { $params["whr_$k"] = $v; }

    return db_run("UPDATE `$table` SET $set WHERE $cond", $params)->rowCount();
}

/**
 * Run a callable inside a transaction, rolling back on any exception.
 * Nested calls join the outer transaction rather than starting a second one.
 */
function db_transaction(callable $fn)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn($pdo);
    }
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
 * Build `IN (?, ?, ?)` with matching placeholders for a list.
 * Returns [sqlFragment, params]. Empty lists yield a never-true fragment so
 * the caller does not accidentally select everything.
 */
function db_in(array $values): array
{
    if (!$values) {
        return ['(NULL)', []];
    }
    return ['(' . implode(', ', array_fill(0, count($values), '?')) . ')', array_values($values)];
}

/**
 * The app's notion of "today" — pinned in demo mode so reports show data.
 *
 * Use this EVERYWHERE, for writes as well as reads. Reads once filtered by
 * app_today() while writes stamped date('Y-m-d'), and once the two drifted
 * apart the app quietly broke: a counter sale made "now" landed on a date
 * today's takings did not cover, so the owner rang up a sale and watched
 * today's revenue not move. The booking window was offered from the real
 * date while the diary showed the pinned one, putting new bookings in a
 * week the schedule never displays. There must be one definition of today.
 */
function app_today(): string
{
    return DEMO_TODAY ?: date('Y-m-d');
}
