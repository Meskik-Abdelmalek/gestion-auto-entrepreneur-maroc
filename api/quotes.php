<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
$action = $_GET['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'convert':
        verifyCsrf();
        $id    = (int)($_GET['id'] ?? 0);
        $invId = $id ? convertQuoteToInvoice($id) : 0;
        if ($invId) {
            flash('message', 'Devis converti en facture avec succès !');
            header("Location: /invoice-view.php?id=$invId");
        } else {
            flash('message', 'Impossible de convertir ce devis (déjà converti ou introuvable).', 'error');
            header('Location: /quotes.php');
        }
        exit;

    case 'delete':
        verifyCsrf();
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $st = $db->prepare("SELECT quote_number FROM ae_quotes WHERE id=?");
            $st->execute([$id]); $q = $st->fetch();
            $db->prepare("DELETE FROM ae_quotes WHERE id=?")->execute([$id]);
            flash('message', "Devis {$q['quote_number']} supprimé.", 'error');
        }
        header('Location: /quotes.php'); exit;

    case 'duplicate':
        verifyCsrf();
        $newId = duplicateQuote((int)($_GET['id'] ?? 0));
        if ($newId) {
            flash('message', 'Devis dupliqué !');
            header("Location: /quote-edit.php?id=$newId");
        } else {
            header('Location: /quotes.php');
        }
        exit;

    case 'next_number':
        header('Content-Type: application/json');
        echo json_encode(['number' => nextQuoteNumber()]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
