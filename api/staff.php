<?php
/**
 * GET /api/staff.php?categories=braids,nails
 * Stylists qualified for EVERY listed category.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/repo.php';

$cats = array_filter(explode(',', (string) ($_GET['categories'] ?? '')));
$rows = $cats ? repo_staff_for_categories($cats) : repo_staff(true);

json_response(['ok' => true, 'staff' => array_map(fn($s) => [
    'ref'     => $s['ref'],
    'name'    => $s['name'],
    'role'    => $s['job_title'],
    'rating'  => (float) $s['rating'],
    'reviews' => (int) $s['reviews_count'],
    'hours'   => $s['working_hours'],
    'tone'    => $s['tone'],
], $rows)]);
