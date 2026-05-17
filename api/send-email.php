<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}
verifyCsrf();

$type    = sanitize($_POST['type']     ?? '');
$id      = sanitizeInt($_POST['id']    ?? 0, 1);
$toEmail = sanitizeEmail($_POST['to_email'] ?? '');
$toName  = sanitize($_POST['to_name']  ?? '', 100);

if (!in_array($type, ['invoice', 'quote'])) {
    jsonResponse(['ok' => false, 'error' => 'Type invalide']);
}
if ($id < 1) {
    jsonResponse(['ok' => false, 'error' => 'ID manquant']);
}
if (!$toEmail) {
    jsonResponse(['ok' => false, 'error' => 'Adresse email invalide']);
}

$result = sendDocument($type, $id, $toEmail, $toName);
jsonResponse($result);
