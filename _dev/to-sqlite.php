<?php
/**
 * Generate the standalone edition's SQLite schema from the live MySQL one.
 *
 *   php _dev/to-sqlite.php > ../princess-desktop/db/sqlite/0001_schema.sql
 *
 * Run once. The output is a real, readable schema file that is then checked in
 * and owned by hand — this script exists so the first draft matches the MySQL
 * schema exactly rather than being retyped from memory, not so it can be
 * re-run forever. The standalone edition must not depend on MySQL existing.
 *
 * Type mapping, and why:
 *   INT/BIGINT/…      INTEGER   SQLite has one integer type; widths are noise.
 *   DECIMAL           NUMERIC   Keeps exact money values, unlike REAL.
 *   VARCHAR/TEXT      TEXT
 *   DATE/TIME/…       TEXT      ISO strings sort and compare correctly, and it
 *                               is what the app already reads and writes.
 *   ENUM(a,b,c)       TEXT + CHECK — the constraint is the point of an enum, so
 *                               it is kept rather than silently dropped.
 *
 * AUTO_INCREMENT becomes INTEGER PRIMARY KEY AUTOINCREMENT, which in SQLite
 * must be spelled exactly that way and must be the only primary key column.
 * ON UPDATE CURRENT_TIMESTAMP has no equivalent, so each one becomes a trigger.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit('CLI only.'); }

require_once __DIR__ . '/../includes/db.php';

$db  = DB_NAME;
$out = [];

$out[] = "-- Princess Beauty Salon — standalone edition schema (SQLite)";
$out[] = "--";
$out[] = "-- Generated from the MySQL schema, then owned by hand. The online";
$out[] = "-- edition still runs on MySQL; this edition runs on a single file so";
$out[] = "-- the salon needs no database server and a backup is one file copy.";
$out[] = "";
$out[] = "PRAGMA foreign_keys = ON;";
$out[] = "";

$tables = db_run(
    'SELECT table_name FROM information_schema.tables
      WHERE table_schema = ? AND table_type = "BASE TABLE" ORDER BY table_name',
    [$db])->fetchAll(PDO::FETCH_COLUMN);

$triggers = [];
$indexes  = [];

foreach ($tables as $t) {
    $cols = db_all(
        'SELECT column_name, data_type, column_type, is_nullable, column_default,
                extra, column_key, column_comment
           FROM information_schema.columns
          WHERE table_schema = ? AND table_name = ?
          ORDER BY ordinal_position', [$db, $t]);

    /* Primary key columns, in key order. */
    $pk = db_run(
        'SELECT column_name FROM information_schema.key_column_usage
          WHERE table_schema = ? AND table_name = ? AND constraint_name = "PRIMARY"
          ORDER BY ordinal_position', [$db, $t])->fetchAll(PDO::FETCH_COLUMN);

    $autoInc = null;
    foreach ($cols as $c) {
        if (str_contains((string) $c['extra'], 'auto_increment')) {
            $autoInc = $c['column_name'];
        }
    }

    $lines = [];
    foreach ($cols as $c) {
        $name = $c['column_name'];
        $def  = '  "' . $name . '" ';

        if ($name === $autoInc) {
            $def .= 'INTEGER PRIMARY KEY AUTOINCREMENT';
            $lines[] = $def;
            continue;
        }

        $def .= sqlite_type($c['data_type']);

        /* An enum's whole value is the restriction it puts on the column. */
        if ($c['data_type'] === 'enum') {
            preg_match_all("/'((?:[^']|'')*)'/", $c['column_type'], $m);
            $vals = array_map(fn($v) => "'" . str_replace("''", "''", $v) . "'", $m[1]);
            if ($vals) {
                $def .= ' CHECK ("' . $name . '" IN (' . implode(', ', $vals) . '))';
            }
        }

        if ($c['is_nullable'] === 'NO') { $def .= ' NOT NULL'; }

        /* MariaDB reports defaults the way they were written, which means string
           defaults arrive already quoted and an absent default arrives as the
           four characters N-U-L-L rather than as SQL NULL. Quoting either of
           those again produces 'NULL' and '''pending''' — both wrong, and both
           silent: the column simply holds the wrong value forever. */
        $d = $c['column_default'];
        if ($d !== null && strcasecmp((string) $d, 'NULL') !== 0) {
            $d = (string) $d;
            if (stripos($d, 'current_timestamp') !== false) {
                $def .= " DEFAULT (datetime('now','localtime'))";
            } elseif (is_numeric($d)) {
                $def .= ' DEFAULT ' . $d;
            } elseif (strlen($d) >= 2 && $d[0] === "'" && str_ends_with($d, "'")) {
                $def .= ' DEFAULT ' . $d;          // already quoted by MariaDB
            } else {
                $def .= " DEFAULT '" . str_replace("'", "''", $d) . "'";
            }
        }

        /* A trailing -- comment would swallow the comma that separates this
           column from the next, and SQLite does not complain: it quietly reads
           the two as one column with a strange type, and the second column
           simply never exists. Comments go on their own line above. */
        if ($c['column_comment']) {
            $def = '  -- ' . $c['column_comment'] . "\n" . $def;
        }
        $lines[] = $def;

        if (stripos((string) $c['extra'], 'on update current_timestamp') !== false) {
            $triggers[] =
                "CREATE TRIGGER IF NOT EXISTS trg_{$t}_{$name}\n" .
                "AFTER UPDATE ON \"$t\" FOR EACH ROW BEGIN\n" .
                "  UPDATE \"$t\" SET \"$name\" = datetime('now','localtime')\n" .
                "   WHERE rowid = NEW.rowid;\nEND;";
        }
    }

    /* Composite or non-auto primary keys need their own clause. */
    if ($pk && $autoInc === null) {
        $lines[] = '  PRIMARY KEY (' . implode(', ', array_map(fn($c) => '"' . $c . '"', $pk)) . ')';
    }

    /* Foreign keys, with their delete rules preserved. */
    foreach (db_all(
        'SELECT k.column_name, k.referenced_table_name, k.referenced_column_name,
                r.delete_rule, r.update_rule
           FROM information_schema.key_column_usage k
           JOIN information_schema.referential_constraints r
             ON r.constraint_name = k.constraint_name AND r.constraint_schema = k.table_schema
          WHERE k.table_schema = ? AND k.table_name = ?', [$db, $t]) as $fk) {
        $line = '  FOREIGN KEY ("' . $fk['column_name'] . '") REFERENCES "'
              . $fk['referenced_table_name'] . '"("' . $fk['referenced_column_name'] . '")';
        if ($fk['delete_rule'] !== 'RESTRICT' && $fk['delete_rule'] !== 'NO ACTION') {
            $line .= ' ON DELETE ' . $fk['delete_rule'];
        }
        $lines[] = $line;
    }

    $out[] = "CREATE TABLE IF NOT EXISTS \"$t\" (";
    $out[] = implode(",\n", $lines);
    $out[] = ");";
    $out[] = "";

    /* Indexes and unique keys become separate statements in SQLite. */
    $seen = [];
    foreach (db_all("SHOW INDEX FROM `$t`") as $ix) {
        $n = $ix['Key_name'];
        if ($n === 'PRIMARY') { continue; }
        $seen[$n]['unique'] = !$ix['Non_unique'];
        $seen[$n]['cols'][(int) $ix['Seq_in_index']] = $ix['Column_name'];
    }
    foreach ($seen as $n => $ix) {
        ksort($ix['cols']);
        $indexes[] = 'CREATE ' . ($ix['unique'] ? 'UNIQUE ' : '') . "INDEX IF NOT EXISTS \"{$t}_{$n}\""
                   . " ON \"$t\" (" . implode(', ', array_map(fn($c) => '"' . $c . '"', $ix['cols'])) . ');';
    }
}

$out[] = "-- ------------------------------------------------------------ indexes";
$out = array_merge($out, $indexes, ['']);

if ($triggers) {
    $out[] = "-- ----------------------------------------------------------- triggers";
    $out[] = "-- SQLite has no ON UPDATE CURRENT_TIMESTAMP, so each one is a trigger.";
    $out[] = "";
    $out = array_merge($out, $triggers, ['']);
}

echo implode("\n", $out), "\n";

function sqlite_type(string $mysqlType): string
{
    return match ($mysqlType) {
        'int', 'bigint', 'smallint', 'tinyint', 'mediumint' => 'INTEGER',
        'decimal', 'numeric', 'float', 'double'             => 'NUMERIC',
        default                                             => 'TEXT',
    };
}
