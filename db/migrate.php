<?php
/**
 * Princess Beauty Salon — migration runner
 *
 *   php db/migrate.php            apply anything not yet applied
 *   php db/migrate.php --status   show what would run
 *   php db/migrate.php --fresh    DROP the database and rebuild from zero
 *
 * Files are applied in filename order and recorded in `migrations`, so
 * re-running is always safe.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Migrations run from the command line only.');
}

require_once __DIR__ . '/../includes/config.php';

$args   = array_slice($argv, 1);
$fresh  = in_array('--fresh', $args, true);
$status = in_array('--status', $args, true);

/* Connect WITHOUT a database first, so we can create it. */
$rootDsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
try {
    $pdo = new PDO($rootDsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot reach MySQL: {$e->getMessage()}\n");
    exit(1);
}

if ($fresh) {
    fwrite(STDOUT, "--fresh: dropping `" . DB_NAME . "`\n");
    $pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
}

$pdo->exec(
    'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` ' .
    'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
);
$pdo->exec('USE `' . DB_NAME . '`');

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        filename    VARCHAR(160) NOT NULL UNIQUE,
        batch       INT UNSIGNED NOT NULL,
        executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$files   = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);

$pending = array_values(array_filter(
    $files,
    fn($f) => !in_array(basename($f), $applied, true)
));

if ($status) {
    fwrite(STDOUT, "database: " . DB_NAME . "\n");
    foreach ($files as $f) {
        $name = basename($f);
        $mark = in_array($name, $applied, true) ? 'applied' : 'PENDING';
        fwrite(STDOUT, sprintf("  [%-7s] %s\n", $mark, $name));
    }
    exit(0);
}

if (!$pending) {
    fwrite(STDOUT, "Nothing to migrate — database is up to date.\n");
    exit(0);
}

$batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();

foreach ($pending as $file) {
    $name = basename($file);
    $sql  = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "  ! {$name} is empty or unreadable\n");
        exit(1);
    }

    fwrite(STDOUT, "  applying {$name} … ");
    try {
        /* Each file is one unit of work. A partially applied migration is
           worse than none at all, so anything that fails rolls back and the
           file stays unrecorded. DDL is not transactional in MySQL, but the
           record-keeping still stops us marking a broken file as done. */
        $pdo->exec($sql);
        $st = $pdo->prepare('INSERT INTO migrations (filename, batch) VALUES (?, ?)');
        $st->execute([$name, $batch]);
        fwrite(STDOUT, "ok\n");
    } catch (PDOException $e) {
        fwrite(STDOUT, "FAILED\n");
        fwrite(STDERR, "    {$e->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Done. Batch {$batch}, " . count($pending) . " migration(s) applied.\n");
