<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth(); // PDFs contain private business data

require_once __DIR__ . '/../includes/pdf.php';

$type = sanitize($_GET['type'] ?? '');
$id   = sanitizeInt($_GET['id'] ?? 0, 1);

if (!in_array($type, ['invoice', 'quote']) || !$id) {
    http_response_code(400);
    echo 'Paramètres invalides';
    exit;
}

if ($type === 'invoice') {
    generateInvoicePdf($id);
} else {
    generateQuotePdf($id);
}
