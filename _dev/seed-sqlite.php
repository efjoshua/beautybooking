<?php
/**
 * Build the standalone edition's database file from the live MySQL one.
 *
 *   php _dev/seed-sqlite.php
 *
 * One-time migration for this salon: creates ../princess-desktop/data/salon.sqlite
 * from the schema file, then copies every row across. After this the standalone
 * edition never touches MySQL again.
 *
 * Foreign keys are switched off for the copy and switched back on afterwards,
 * because rows arrive table by table and a child will often land before its
 * parent. They are verified at the end rather than trusted.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit('CLI only.'); }

require_once __DIR__ . '/../includes/db.php';

$dest   = __DIR__ . '/../../princess-desktop/data/salon.sqlite';
$schema = __DIR__ . '/../../princess-desktop/db/sqlite/0001_schema.sql';

if (!is_dir(dirname($dest))) { mkdir(dirname($dest), 0777, true); }
if (file_exists($dest)) {
    unlink($dest);
    fwrite(STDOUT, "Replaced the existing database file.\n");
}

$lite = new PDO('sqlite:' . $dest, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$lite->exec('PRAGMA journal_mode = WAL');
$lite->exec('PRAGMA foreign_keys = OFF');

/* ------------------------------------------------------------- schema */
$sql = file_get_contents($schema);
if ($sql === false) { fwrite(STDERR, "Cannot read $schema\n"); exit(1); }

/* Split on semicolons at end of line, keeping trigger bodies intact. */
$statements = [];
$buffer = '';
$inTrigger = false;
foreach (explode("\n", $sql) as $line) {
    $buffer .= $line . "\n";
    if (preg_match('/^\s*CREATE TRIGGER/i', $line)) { $inTrigger = true; }
    if ($inTrigger) {
        if (preg_match('/^\s*END;\s*$/i', $line)) { $statements[] = $buffer; $buffer = ''; $inTrigger = false; }
        continue;
    }
    if (preg_match('/;\s*$/', $line)) { $statements[] = $buffer; $buffer = ''; }
}

foreach ($statements as $s) {
    if (trim(preg_replace('/^\s*--.*$/m', '', $s)) === '') { continue; }
    try {
        $lite->exec($s);
    } catch (PDOException $e) {
        fwrite(STDERR, "Failed on:\n$s\n  {$e->getMessage()}\n");
        exit(1);
    }
}
/* The schema file turns foreign keys on for normal use, which is right for the
   app and wrong for the next thirty seconds — rows arrive table by table, so a
   child routinely lands before its parent. Off again for the copy, and checked
   properly at the end. It cannot be toggled inside a transaction, hence here. */
$lite->exec('PRAGMA foreign_keys = OFF');

fwrite(STDOUT, "Schema created.\n");

/* --------------------------------------------------------------- data */
$tables = db_run(
    'SELECT table_name FROM information_schema.tables
      WHERE table_schema = ? AND table_type = "BASE TABLE" ORDER BY table_name',
    [DB_NAME])->fetchAll(PDO::FETCH_COLUMN);

$total = 0;
$lite->beginTransaction();
foreach ($tables as $t) {
    $rows = db_all("SELECT * FROM `$t`");
    if (!$rows) { continue; }

    $cols = array_keys($rows[0]);
    $stmt = $lite->prepare(
        'INSERT INTO "' . $t . '" ('
        . implode(', ', array_map(fn($c) => '"' . $c . '"', $cols)) . ') VALUES ('
        . implode(', ', array_fill(0, count($cols), '?')) . ')');

    foreach ($rows as $r) {
        $stmt->execute(array_map(
            fn($v) => is_bool($v) ? (int) $v : $v,
            array_values($r)));
    }
    printf("  %-24s %d\n", $t, count($rows));
    $total += count($rows);
}
$lite->commit();

/* ------------------------------------------------------------- verify */
$lite->exec('PRAGMA foreign_keys = ON');
$bad = $lite->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
$integrity = $lite->query('PRAGMA integrity_check')->fetchColumn();

fwrite(STDOUT, "\n$total rows copied into " . realpath($dest) . "\n");
fwrite(STDOUT, 'integrity: ' . $integrity . "\n");
fwrite(STDOUT, 'foreign keys: ' . ($bad ? count($bad) . " BROKEN" : 'all valid') . "\n");
fwrite(STDOUT, 'file size: ' . round(filesize($dest) / 1024) . " KB\n");

if ($bad) {
    foreach (array_slice($bad, 0, 5) as $b) { fwrite(STDERR, '  ' . json_encode($b) . "\n"); }
    exit(1);
}
