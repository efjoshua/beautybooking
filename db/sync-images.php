<?php
/**
 * Princess Beauty Salon — image scanner
 *
 *   php db/sync-images.php          bring the gallery table in step with the folders
 *   php db/sync-images.php --dry    show what would change, touch nothing
 *
 * The folders under assets/img/ are the source of truth. This walks every
 * folder registered in `gallery_folders`, inserts a row for each new file,
 * removes the rows whose file has gone, and leaves everything else alone.
 * No image is ever read into the database — only its folder and filename.
 *
 * Safe to run as often as you like; it is idempotent.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('The image scanner runs from the command line only.');
}

require_once __DIR__ . '/../includes/db.php';

$dry = in_array('--dry', array_slice($argv, 1), true);

const IMG_ROOT   = APP_ROOT . '/assets/img';
const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

/**
 * A filename that already says something becomes the caption.
 * "smoky-makeup.jpeg" → "Smoky Makeup"; "p7.jpeg" → falls back to the folder.
 */
function caption_for(string $filename, string $folder, string $label, int $n): string
{
    $base = pathinfo($filename, PATHINFO_FILENAME);
    /* "p7" or "pedicure-3" says nothing; anything else the salon typed does. */
    $generic = preg_match('/^(p|img|image)?\d+$/i', $base)
            || preg_match('/^' . preg_quote($folder, '/') . '-\d+$/i', $base);
    if ($generic) {
        return sprintf('%s %02d', $label, $n);
    }
    return ucwords(str_replace('-', ' ', $base));
}

/** Every image file sitting directly inside a folder, in name order. */
function files_in(string $folder): array
{
    $dir = IMG_ROOT . '/' . $folder;
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    foreach (scandir($dir) ?: [] as $f) {
        if ($f[0] === '.' || is_dir("$dir/$f")) {
            continue;
        }
        if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), EXTENSIONS, true)) {
            $out[] = $f;
        }
    }
    natcasesort($out);
    return array_values($out);
}

$folders = db_all('SELECT * FROM gallery_folders ORDER BY sort_order, id');
if (!$folders) {
    fwrite(STDERR, "No folders registered. Run the migrations first.\n");
    exit(1);
}

$added = $removed = $kept = 0;
$nextRef = (int) db_val(
    "SELECT COALESCE(MAX(CAST(SUBSTRING(ref, 3) AS UNSIGNED)), 0) FROM gallery WHERE ref LIKE 'G-%'"
) + 1;

foreach ($folders as $f) {
    if ($f['role'] === 'team') {
        continue;                                   // portraits are matched to a staff ref below
    }

    $onDisk = files_in($f['folder']);
    $inDb   = db_run('SELECT filename FROM gallery WHERE folder_id = ?', [$f['id']])
                ->fetchAll(PDO::FETCH_COLUMN);

    $new  = array_diff($onDisk, $inDb);
    $gone = array_diff($inDb, $onDisk);
    $kept += count(array_intersect($onDisk, $inDb));

    $sort = 0;
    foreach ($onDisk as $filename) {
        $sort++;
        if (!in_array($filename, $new, true)) {
            /* Already known. Keep its position in step with the folder, so
               removing a file does not leave a gap in the running order. */
            if (!$dry) {
                db_run('UPDATE gallery SET sort_order = ? WHERE folder_id = ? AND filename = ?',
                       [$sort, $f['id'], $filename]);
            }
            continue;
        }
        $title = caption_for($filename, $f['folder'], $f['label'], $sort);
        printf("  + %s/%s  “%s”\n", $f['folder'], $filename, $title);
        if (!$dry) {
            db_run(
                'INSERT INTO gallery (ref, title, folder_id, filename, tone, is_public, sort_order)
                 VALUES (?, ?, ?, ?, ?, 1, ?)',
                [sprintf('G-%03d', $nextRef++), $title, $f['id'], $filename, $f['tone'], $sort]
            );
        }
        $added++;
    }

    foreach ($gone as $filename) {
        printf("  - %s/%s  (file no longer on disk)\n", $f['folder'], $filename);
        if (!$dry) {
            db_run('DELETE FROM gallery WHERE folder_id = ? AND filename = ?', [$f['id'], $filename]);
        }
        $removed++;
    }
}

/* Stylist portraits: assets/img/team/ST-02.jpeg lands on staff ST-02. */
$photos = 0;
foreach (files_in('team') as $filename) {
    $ref = strtoupper(pathinfo($filename, PATHINFO_FILENAME));
    $st  = db_val('SELECT id FROM staff WHERE ref = ? AND deleted_at IS NULL', [$ref]);
    if (!$st) {
        printf("  ? team/%s does not match a staff ref — skipped\n", $filename);
        continue;
    }
    printf("  + team/%s → staff %s\n", $filename, $ref);
    if (!$dry) {
        db_run('UPDATE staff SET photo = ? WHERE id = ?', [$filename, $st]);
    }
    $photos++;
}

printf(
    "%s%d added, %d removed, %d unchanged, %d stylist photo(s).\n",
    $dry ? "(dry run) " : '', $added, $removed, $kept, $photos
);
