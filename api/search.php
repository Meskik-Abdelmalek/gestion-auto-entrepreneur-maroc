<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: application/json');
$q = clean($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }
echo json_encode(globalSearch($q));
