<?php
/** POST /api/contact.php — store a message from the contact form. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('POST only.', 405);
csrf_require();

$name  = trim((string) ($_POST['name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$body  = trim((string) ($_POST['message'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

if ($name === '' || $phone === '' || $body === '') json_error('Name, phone and message are required.', 422);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('That email is not valid.', 422);

db_insert('contact_messages', [
    'name' => $name, 'phone' => $phone, 'email' => $email ?: null,
    'subject' => trim((string) ($_POST['subject'] ?? '')) ?: null,
    'body' => $body, 'opt_in' => !empty($_POST['opt_in']) ? 1 : 0,
]);
json_response(['ok' => true, 'message' => 'Thanks! Your message has been sent — we reply within 2 hours.']);
