<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

verifyCsrf();

$cfg      = getConfig();
$toEmail  = $cfg['smtp_from_email'] ?: ($cfg['email'] ?: '');
$fromName = $cfg['smtp_from_name']  ?: ($cfg['owner_name'] ?: 'AE Maroc');

if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'error' => 'Aucune adresse email expéditeur configurée dans les paramètres.']);
}

$result = smtpSend([
    'host'       => $cfg['smtp_host']       ?: '',
    'port'       => (int)($cfg['smtp_port'] ?: 587),
    'user'       => $cfg['smtp_user']       ?: '',
    'pass'       => $cfg['smtp_pass']       ?: '',
    'encryption' => $cfg['smtp_encryption'] ?: 'tls',
    'from_email' => $toEmail,
    'from_name'  => $fromName,
    'to_email'   => $toEmail,
    'to_name'    => $fromName,
    'subject'    => 'Test SMTP — AE Maroc v2.1',
    'html'       => '<p>Email de test envoyé depuis <strong>AE Maroc v2.1</strong>.<br>Votre configuration SMTP fonctionne correctement ✅</p>',
]);

if ($result['ok']) {
    $result['message'] = "Email de test envoyé à $toEmail";
}
jsonResponse($result);
