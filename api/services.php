<?php
/** GET /api/services.php — the public price list, grouped by category. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/repo.php';

$cats = repo_categories();
$svcs = repo_services();
$out = [];
foreach ($cats as $c) {
    $out[] = [
        'slug'  => $c['slug'], 'name' => $c['name'], 'short' => $c['short_name'],
        'icon'  => $c['icon'], 'color' => $c['color'], 'blurb' => $c['blurb'],
        'services' => array_values(array_map(fn($s) => [
            'ref'      => $s['ref'],
            'name'     => $s['name'],
            'desc'     => $s['description'],
            'price'    => (float) $s['price'],
            'duration' => (int) $s['duration_minutes'],
            'popular'  => (bool) $s['is_popular'],
        ], array_filter($svcs, fn($s) => $s['category_slug'] === $c['slug']))),
    ];
}
json_response(['ok' => true, 'categories' => $out]);
