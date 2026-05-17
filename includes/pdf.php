<?php
// ── Server-Side PDF Generation (Dompdf) ──────────────────────
// Requires: composer require dompdf/dompdf
// Auto-installs on first call if composer is available.
//
// Usage:
//   generateInvoicePdf($invId);   // streams PDF to browser
//   generateQuotePdf($quoteId);
//   generateInvoicePdf($invId, '/tmp/out.pdf');  // save to file
//
// Falls back to browser-print URL if Dompdf is not available.

function dompdfAvailable(): bool
{
    return class_exists('\Dompdf\Dompdf')
        || file_exists(__DIR__ . '/../vendor/autoload.php');
}

function loadDompdf(): bool
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        return class_exists('\Dompdf\Dompdf');
    }
    return false;
}

function generateInvoicePdf(int $id, ?string $savePath = null): void
{
    $db  = getDB();
    $cfg = getConfig();

    $st = $db->prepare("SELECT * FROM ae_invoices WHERE id=?");
    $st->execute([$id]);
    $inv = $st->fetch();
    if (!$inv) { http_response_code(404); echo 'Facture introuvable'; return; }

    $lSt = $db->prepare("SELECT * FROM ae_invoice_lines WHERE invoice_id=? ORDER BY sort_order");
    $lSt->execute([$id]);
    $lines = $lSt->fetchAll();

    $html = buildDocumentHtml($inv, $lines, $cfg, 'invoice');
    streamOrSavePdf($html, 'Facture_' . $inv['invoice_number'] . '.pdf', $savePath);
}

function generateQuotePdf(int $id, ?string $savePath = null): void
{
    $db  = getDB();
    $cfg = getConfig();

    $st = $db->prepare("SELECT * FROM ae_quotes WHERE id=?");
    $st->execute([$id]);
    $doc = $st->fetch();
    if (!$doc) { http_response_code(404); echo 'Devis introuvable'; return; }

    $lSt = $db->prepare("SELECT * FROM ae_quote_lines WHERE quote_id=? ORDER BY sort_order");
    $lSt->execute([$id]);
    $lines = $lSt->fetchAll();

    $html = buildDocumentHtml($doc, $lines, $cfg, 'quote');
    streamOrSavePdf($html, 'Devis_' . $doc['quote_number'] . '.pdf', $savePath);
}

function streamOrSavePdf(string $html, string $filename, ?string $savePath): void
{
    if (!loadDompdf()) {
        // Dompdf not installed — redirect to browser-print fallback
        header('Location: ' . $_SERVER['REQUEST_URI'] . '&print=1&autoprint=1');
        exit;
    }

    $dompdf = new \Dompdf\Dompdf([
        'defaultFont'        => 'DejaVu Sans',
        'isRemoteEnabled'    => true,
        'isHtml5ParserEnabled' => true,
        'tempDir'            => sys_get_temp_dir(),
        'fontDir'            => __DIR__ . '/../storage/fonts/',
        'fontCache'          => __DIR__ . '/../storage/fonts/',
        'chroot'             => realpath(__DIR__ . '/..'),
    ]);

    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    if ($savePath) {
        file_put_contents($savePath, $dompdf->output());
        return;
    }

    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}

// ── HTML template shared by invoice + quote ───────────────────
function buildDocumentHtml(array $doc, array $lines, array $cfg, string $type): string
{
    $isInvoice = $type === 'invoice';
    $docLabel  = $isInvoice ? 'FACTURE' : 'DEVIS';
    $number    = $isInvoice ? ($doc['invoice_number'] ?? '') : ($doc['quote_number'] ?? '');
    $dateField = $isInvoice ? ($doc['invoice_date'] ?? '') : ($doc['quote_date'] ?? '');
    $dateStr   = $dateField ? date('d/m/Y', strtotime($dateField)) : '';
    $owner     = htmlspecialchars($cfg['owner_name'] ?? '', ENT_QUOTES);
    $address   = nl2br(htmlspecialchars($cfg['address'] ?? '', ENT_QUOTES));
    $ice       = htmlspecialchars($cfg['ice'] ?? '', ENT_QUOTES);
    $ifFiscal  = htmlspecialchars($cfg['if_fiscal'] ?? '', ENT_QUOTES);
    $tp        = htmlspecialchars($cfg['tp'] ?? '', ENT_QUOTES);
    $phone     = htmlspecialchars($cfg['cnss_phone'] ?? '', ENT_QUOTES);
    $email     = htmlspecialchars($cfg['email'] ?? '', ENT_QUOTES);
    $rib       = htmlspecialchars($cfg['bank_rib'] ?? '', ENT_QUOTES);
    $client    = htmlspecialchars($doc['client_name'] ?? '', ENT_QUOTES);
    $amountHt  = number_format((float)($doc['amount_ht'] ?? 0), 2, '.', ' ');
    $amountTtc = number_format((float)($doc['amount_ttc'] ?? 0), 2, '.', ' ');
    $currency  = $cfg['currency'] ?? 'MAD';
    $notes     = htmlspecialchars($doc['notes'] ?? '', ENT_QUOTES);
    $footer    = $isInvoice
        ? htmlspecialchars($cfg['invoice_footer_text'] ?? '', ENT_QUOTES)
        : htmlspecialchars($cfg['quote_footer_text']   ?? '', ENT_QUOTES);

    // Logo
    $logoHtml = '';
    if (!empty($cfg['logo_path'])) {
        $logoAbs = realpath(__DIR__ . '/..' . $cfg['logo_path']);
        if ($logoAbs && file_exists($logoAbs)) {
            $mime    = mime_content_type($logoAbs);
            $b64     = base64_encode(file_get_contents($logoAbs));
            $w       = (int)($cfg['logo_width_mm'] ?? 40);
            $logoHtml = "<img src=\"data:$mime;base64,$b64\" style=\"max-width:{$w}mm;max-height:20mm;\" alt=\"Logo\">";
        }
    }

    // Due date / validity
    $dueHtml = '';
    if ($isInvoice && !empty($doc['due_date'])) {
        $dueHtml = '<div><span class="label">ÉCHÉANCE</span><br>' . date('d/m/Y', strtotime($doc['due_date'])) . '</div>';
    } elseif (!$isInvoice && !empty($doc['valid_until'])) {
        $dueHtml = '<div><span class="label">VALABLE JUSQU\'AU</span><br>' . date('d/m/Y', strtotime($doc['valid_until'])) . '</div>';
    }

    // IR estimate (invoice only)
    $irHtml = '';
    if ($isInvoice) {
        $rate    = $doc['category'] === 'Commerce'
            ? (float)($cfg['ir_rate_commerce'] ?? 0.005)
            : (float)($cfg['ir_rate_services'] ?? 0.01);
        $ir      = (float)($doc['amount_ttc'] ?? 0) * $rate;
        $irPct   = round($rate * 100, 1);
        $irHtml  = '<div class="ir-note">IR retenu à la source : ' . number_format($ir, 2, '.', ' ') . " $currency ($irPct%)</div>";
    }

    // Status badge (invoice only)
    $statusHtml = '';
    if ($isInvoice) {
        $status = $doc['status'] ?? '';
        $statusColors = ['Payé' => '#16a34a', 'En attente' => '#d97706', 'Annulé' => '#dc2626'];
        $sc = $statusColors[$status] ?? '#6b7280';
        $statusHtml = "<span style=\"background:$sc;color:#fff;padding:2px 8px;border-radius:4px;font-size:9pt;\">$status</span>";
    }

    // Build lines rows
    $linesHtml = '';
    foreach ($lines as $i => $l) {
        $bg = $i % 2 === 0 ? '#fafafa' : '#ffffff';
        $linesHtml .= '<tr style="background:' . $bg . ';">'
            . '<td class="td">' . htmlspecialchars($l['description'] ?? '', ENT_QUOTES) . '</td>'
            . '<td class="td center">' . number_format((float)($l['quantity'] ?? 0), 2) . '</td>'
            . '<td class="td right">' . number_format((float)($l['unit_price'] ?? 0), 2, '.', ' ') . '</td>'
            . '<td class="td right bold">' . number_format((float)($l['amount'] ?? 0), 2, '.', ' ') . '</td>'
            . '</tr>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: "DejaVu Sans", "Helvetica", Arial, sans-serif; font-size: 9pt; color: #323130; background: #fff; }
  .page { padding: 15mm 18mm; }

  /* Header */
  .header { display: table; width: 100%; margin-bottom: 8mm; }
  .header-left { display: table-cell; vertical-align: top; width: 50%; }
  .header-right { display: table-cell; vertical-align: top; text-align: right; }
  .owner-name { font-size: 16pt; font-weight: bold; color: #1B2A4A; margin-bottom: 2mm; }
  .owner-meta { font-size: 8pt; color: #605e5c; line-height: 1.5; }
  .doc-type { font-size: 22pt; font-weight: 900; color: #1B2A4A; letter-spacing: 1px; }
  .doc-number { font-size: 11pt; font-family: monospace; color: #0078d4; font-weight: bold; }

  /* Divider */
  .divider { border: none; border-top: 2px solid #1B2A4A; margin: 5mm 0; }

  /* Meta grid */
  .meta { display: table; width: 100%; margin-bottom: 6mm; }
  .meta-cell { display: table-cell; vertical-align: top; }
  .label { font-size: 7pt; color: #a19f9d; text-transform: uppercase; letter-spacing: .5px; }
  .meta-val { font-size: 9pt; font-weight: bold; color: #1B2A4A; margin-top: 1mm; }

  /* Lines table */
  table.lines { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
  .lines thead tr { background: #1B2A4A; color: #fff; }
  .lines thead th { padding: 5px 8px; font-size: 8pt; text-align: left; }
  .lines thead th.right { text-align: right; }
  .lines thead th.center { text-align: center; }
  .td { padding: 5px 8px; border-bottom: 1px solid #edebe9; font-size: 8.5pt; }
  .td.right { text-align: right; }
  .td.center { text-align: center; }
  .td.bold { font-weight: bold; }

  /* Totals */
  .totals { display: table; margin-left: auto; min-width: 60mm; }
  .total-row { display: table-row; }
  .total-lbl { display: table-cell; padding: 2px 8px; font-size: 8.5pt; color: #605e5c; text-align: right; }
  .total-val { display: table-cell; padding: 2px 8px; font-size: 8.5pt; text-align: right; min-width: 30mm; }
  .total-final .total-lbl, .total-final .total-val { background: #1B2A4A; color: #fff; font-weight: bold; font-size: 10pt; padding: 4px 8px; border-radius: 2px; }

  .ir-note { font-size: 7.5pt; color: #a19f9d; text-align: right; margin-top: 2mm; }
  .notes-box { background: #f3f2f1; padding: 4mm; border-radius: 3px; margin: 6mm 0 4mm; font-size: 8.5pt; color: #605e5c; }
  .footer-divider { border: none; border-top: 1px solid #edebe9; margin: 5mm 0 3mm; }
  .payment-section { font-size: 8pt; color: #605e5c; margin-bottom: 4mm; }
  .footer-note { font-size: 7.5pt; color: #a19f9d; text-align: center; margin-top: 3mm; border-top: 1px solid #edebe9; padding-top: 3mm; }
</style>
</head>
<body>
<div class="page">

  <!-- Header -->
  <div class="header">
    <div class="header-left">
      $logoHtml
      <div class="owner-name">$owner</div>
      <div class="owner-meta">
        $address<br>
        ICE : $ice &nbsp;|&nbsp; IF : $ifFiscal<br>
        TP : $tp &nbsp;|&nbsp; Tél : $phone<br>
        $email
      </div>
    </div>
    <div class="header-right">
      <div class="doc-type">$docLabel</div>
      <div class="doc-number">$number</div>
      <div style="margin-top:2mm;">$statusHtml</div>
    </div>
  </div>

  <hr class="divider">

  <!-- Meta -->
  <div class="meta">
    <div class="meta-cell">
      <span class="label">Client</span>
      <div class="meta-val">$client</div>
    </div>
    <div class="meta-cell">
      <span class="label">Date</span>
      <div class="meta-val">$dateStr</div>
    </div>
    $dueHtml
    <div class="meta-cell" style="text-align:right;">
      <span class="label">Montant TTC</span>
      <div class="meta-val" style="font-size:13pt;color:#0078d4;">$amountTtc $currency</div>
    </div>
  </div>

  <!-- Lines -->
  <table class="lines">
    <thead>
      <tr>
        <th>Désignation</th>
        <th class="center">Qté</th>
        <th class="right">Prix unit.</th>
        <th class="right">Montant</th>
      </tr>
    </thead>
    <tbody>
      $linesHtml
    </tbody>
  </table>

  <!-- Totals -->
  <div class="totals">
    <div class="total-row">
      <div class="total-lbl">Total HT</div>
      <div class="total-val">$amountHt $currency</div>
    </div>
    <div class="total-row">
      <div class="total-lbl">TVA</div>
      <div class="total-val" style="color:#a19f9d;font-style:italic;">Exonéré (AE)</div>
    </div>
    <div class="total-row total-final">
      <div class="total-lbl">Total TTC</div>
      <div class="total-val">$amountTtc $currency</div>
    </div>
  </div>
  $irHtml

  <!-- Notes -->
  <?php if ($notes): ?>
  <div class="notes-box"><strong>Notes :</strong> $notes</div>
  <?php endif; ?>

  <!-- Payment -->
  <hr class="footer-divider">
  <div class="payment-section">
    <strong>Modalités de paiement</strong><br>
    <?php if ($rib): ?>RIB : $rib<br><?php endif; ?>
    Réf. virement : $number<br>
    Auto-Entrepreneur exonéré de TVA — Art. 91-I-B-1° du CGI marocain
  </div>

  <?php if ($footer): ?>
  <div class="notes-box">$footer</div>
  <?php endif; ?>

  <div class="footer-note">$owner &nbsp;·&nbsp; ICE : $ice &nbsp;·&nbsp; Merci pour votre confiance</div>

</div>
</body>
</html>
HTML;
}
