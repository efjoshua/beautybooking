<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

/* POST-only: a GET logout can be triggered by an <img> tag on another site. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
}
auth_logout();
redirect('admin/login.php');
