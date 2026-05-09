<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
if (!isset($_SESSION)) session_start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

switch ($action) {

    case 'delete':
        verifyCsrf();
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $inv = $db->prepare("SELECT invoice_number FROM ae_invoices WHERE id=?");
            $inv->execute([$id]); $inv = $inv->fetch();
            $db->prepare("DELETE FROM ae_invoices WHERE id=?")->execute([$id]);
            flash('message', "Facture {$inv['invoice_number']} supprimée.", 'error');
        }
        header('Location: /invoices.php'); exit;

    case 'mark_paid':
        verifyCsrf();
        $id   = (int)($_POST['id'] ?? 0);
        $date = clean($_POST['payment_date'] ?? date('Y-m-d'));
        $mode = clean($_POST['payment_method'] ?? '');
        if ($id) {
            $db->prepare("UPDATE ae_invoices SET status='Payé',payment_date=?,payment_method=? WHERE id=?")
               ->execute([$date, $mode, $id]);
            $db->prepare("UPDATE ae_reminders SET status='Payé' WHERE invoice_id=?")->execute([$id]);
            flash('message', 'Facture marquée comme payée !');
        }
        header('Location: ' . ($_POST['redirect'] ?? '/invoices.php')); exit;

    case 'duplicate':
        verifyCsrf();
        $id    = (int)($_GET['id'] ?? 0);
        $newId = $id ? duplicateInvoice($id) : 0;
        if ($newId) {
            flash('message', 'Facture dupliquée !');
            header("Location: /invoice-edit.php?id=$newId");
        } else {
            header('Location: /invoices.php');
        }
        exit;

    case 'bulk':
        verifyCsrf();
        $ids    = array_map('intval', $_POST['ids'] ?? []);
        $op     = clean($_POST['bulk_action'] ?? '');
        $allowed = ['mark_paid', 'mark_cancelled', 'delete'];
        if ($ids && in_array($op, $allowed)) {
            $n = bulkUpdateInvoices($ids, $op);
            flash('message', "$n facture(s) mise(s) à jour.");
        }
        header('Location: /invoices.php'); exit;

    case 'search_clients':
        header('Content-Type: application/json');
        $q = clean($_GET['q'] ?? '');
        $st = $db->prepare("SELECT id,name,city,email FROM ae_clients WHERE name LIKE ? LIMIT 8");
        $st->execute(["%$q%"]);
        echo json_encode($st->fetchAll()); exit;

    case 'stats':
        header('Content-Type: application/json');
        echo json_encode(getDashboardStats()); exit;

    case 'next_number':
        header('Content-Type: application/json');
        echo json_encode(['number' => nextInvoiceNumber()]); exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
