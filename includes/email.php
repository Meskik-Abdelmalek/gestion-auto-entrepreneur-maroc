<?php
// ── Email Helper (zero-dependency SMTP or mail()) ─────────────
// Usage:
//   sendDocument('invoice', $invId, $toEmail, $toName);
//   sendDocument('quote',   $quoteId, $toEmail, $toName);

function sendDocument(string $type, int $docId, string $toEmail, string $toName = ''): array
{
    $db  = getDB();
    $cfg = getConfig();

    // ── Load document ─────────────────────────────────────────
    if ($type === 'invoice') {
        $st  = $db->prepare("SELECT * FROM ae_invoices WHERE id=?");
        $st->execute([$docId]);
        $doc = $st->fetch();
        $lines = $db->prepare("SELECT * FROM ae_invoice_lines WHERE invoice_id=? ORDER BY sort_order");
        $lines->execute([$docId]);
        $lines = $lines->fetchAll();
        $subjectTpl = $cfg['email_invoice_subject'] ?: 'Votre facture {{number}}';
        $bodyTpl    = $cfg['email_invoice_body']    ?: defaultEmailBody('invoice');
        $number     = $doc['invoice_number'] ?? '';
    } else {
        $st = $db->prepare("SELECT * FROM ae_quotes WHERE id=?");
        $st->execute([$docId]);
        $doc = $st->fetch();
        $lines = $db->prepare("SELECT * FROM ae_quote_lines WHERE quote_id=? ORDER BY sort_order");
        $lines->execute([$docId]);
        $lines = $lines->fetchAll();
        $subjectTpl = $cfg['email_quote_subject'] ?: 'Votre devis {{number}}';
        $bodyTpl    = $cfg['email_quote_body']    ?: defaultEmailBody('quote');
        $number     = $doc['quote_number'] ?? '';
    }

    if (!$doc) return ['ok' => false, 'error' => 'Document introuvable'];

    // ── Merge template variables ──────────────────────────────
    $vars = [
        '{{number}}'      => $number,
        '{{client}}'      => $doc['client_name'] ?? '',
        '{{owner}}'       => $cfg['owner_name']  ?? '',
        '{{amount}}'      => number_format((float)($doc['amount_ttc'] ?? 0), 2, '.', ' ') . ' ' . ($cfg['currency'] ?? 'MAD'),
        '{{date}}'        => isset($doc['invoice_date']) ? date('d/m/Y', strtotime($doc['invoice_date'])) : (isset($doc['quote_date']) ? date('d/m/Y', strtotime($doc['quote_date'])) : ''),
    ];
    $subject = str_replace(array_keys($vars), array_values($vars), $subjectTpl);
    $htmlBody = buildEmailHtml($doc, $lines, $cfg, $type, $vars, $bodyTpl);

    // ── Send ──────────────────────────────────────────────────
    $fromEmail = $cfg['smtp_from_email'] ?: ($cfg['email'] ?: 'noreply@ae-maroc.local');
    $fromName  = $cfg['smtp_from_name']  ?: ($cfg['owner_name'] ?: 'Auto-Entrepreneur');

    $result = smtpSend([
        'host'       => $cfg['smtp_host']       ?: '',
        'port'       => (int)($cfg['smtp_port'] ?: 587),
        'user'       => $cfg['smtp_user']       ?: '',
        'pass'       => $cfg['smtp_pass']       ?: '',
        'encryption' => $cfg['smtp_encryption'] ?: 'tls',
        'from_email' => $fromEmail,
        'from_name'  => $fromName,
        'to_email'   => $toEmail,
        'to_name'    => $toName,
        'subject'    => $subject,
        'html'       => $htmlBody,
    ]);

    // ── Log ───────────────────────────────────────────────────
    $db->prepare("INSERT INTO ae_email_log (to_email,to_name,subject,document_type,document_id,status,error_msg) VALUES (?,?,?,?,?,?,?)")
       ->execute([$toEmail, $toName, $subject, $type, $docId, $result['ok'] ? 'sent' : 'failed', $result['error'] ?? '']);

    return $result;
}

// ── SMTP sender (pure PHP sockets, no PHPMailer needed) ───────
function smtpSend(array $p): array
{
    // If no SMTP host configured, fall back to PHP mail()
    if (empty($p['host'])) {
        return mailFallback($p);
    }

    try {
        $enc  = strtolower($p['encryption']);
        $host = ($enc === 'ssl' ? 'ssl://' : '') . $p['host'];
        $sock = @fsockopen($host, $p['port'], $errno, $errstr, 10);
        if (!$sock) return ['ok' => false, 'error' => "Connexion SMTP échouée: $errstr ($errno)"];

        $read = fn() => fgets($sock, 515);
        $send = function(string $cmd) use ($sock, $read): string {
            fputs($sock, $cmd . "\r\n");
            return $read();
        };

        $read(); // banner
        $send("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        // Read multi-line EHLO
        while (($line = $read()) && substr($line, 3, 1) === '-') {}

        if ($enc === 'tls') {
            $send("STARTTLS");
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            while (($line = $read()) && substr($line, 3, 1) === '-') {}
        }

        if (!empty($p['user'])) {
            $send("AUTH LOGIN");
            $send(base64_encode($p['user']));
            $r = $send(base64_encode($p['pass']));
            if ((int)substr($r, 0, 3) !== 235) {
                fclose($sock);
                return ['ok' => false, 'error' => 'Authentification SMTP échouée: ' . trim($r)];
            }
        }

        $send("MAIL FROM:<{$p['from_email']}>");
        $send("RCPT TO:<{$p['to_email']}>");
        $send("DATA");

        $boundary = '==AE_' . md5(uniqid());
        $msgId    = '<' . uniqid() . '@ae-maroc>';
        $headers  = implode("\r\n", [
            "From: {$p['from_name']} <{$p['from_email']}>",
            "To: {$p['to_name']} <{$p['to_email']}>",
            "Subject: =?UTF-8?B?" . base64_encode($p['subject']) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
            "Message-ID: $msgId",
            "Date: " . date('r'),
            "X-Mailer: AE-Maroc v2.1",
        ]);

        $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $p['html']));
        $body  = "--$boundary\r\n"
               . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
               . chunk_split(base64_encode($plain))
               . "--$boundary\r\n"
               . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
               . chunk_split(base64_encode($p['html']))
               . "--$boundary--";

        fputs($sock, $headers . "\r\n\r\n" . $body . "\r\n.\r\n");
        $r = $read();
        $send("QUIT");
        fclose($sock);

        $code = (int)substr($r, 0, 3);
        if ($code !== 250) return ['ok' => false, 'error' => "SMTP rejeté (code $code): " . trim($r)];
        return ['ok' => true, 'message_id' => $msgId, 'error' => null];

    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function mailFallback(array $p): array
{
    $headers = "From: {$p['from_name']} <{$p['from_email']}>\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n";
    $ok = mail($p['to_email'], $p['subject'], $p['html'], $headers);
    return ['ok' => $ok, 'error' => $ok ? null : 'mail() a retourné false — vérifiez la config PHP sendmail.'];
}

// ── HTML email builder ────────────────────────────────────────
function buildEmailHtml(array $doc, array $lines, array $cfg, string $type, array $vars, string $introTpl): string
{
    $intro    = str_replace(array_keys($vars), array_values($vars), $introTpl);
    $owner    = htmlspecialchars($cfg['owner_name'] ?? '', ENT_QUOTES);
    $docLabel = $type === 'invoice' ? 'Facture' : 'Devis';
    $number   = $vars['{{number}}'];
    $client   = htmlspecialchars($doc['client_name'] ?? '', ENT_QUOTES);
    $amount   = $vars['{{amount}}'];
    $dateVal  = $vars['{{date}}'];
    $logoHtml = '';
    if (!empty($cfg['logo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $cfg['logo_path'])) {
        $logoHtml = '<img src="cid:logo" alt="Logo" style="max-height:60px;max-width:200px;">';
    }

    $linesHtml = '';
    foreach ($lines as $i => $l) {
        $bg = $i % 2 === 0 ? '#f9f9f9' : '#ffffff';
        $linesHtml .= '<tr style="background:' . $bg . '">'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;">' . htmlspecialchars($l['description'] ?? '', ENT_QUOTES) . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:center;">' . number_format((float)$l['quantity'], 2) . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;">' . number_format((float)$l['unit_price'], 2, '.', ' ') . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;font-weight:bold;">' . number_format((float)$l['amount'], 2, '.', ' ') . '</td>'
            . '</tr>';
    }

    return <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>$docLabel $number</title></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:'Segoe UI',Arial,sans-serif;font-size:14px;color:#323130;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">

  <!-- Header -->
  <tr><td style="background:#1B2A4A;padding:24px 30px;">
    <table width="100%"><tr>
      <td style="color:#fff;">$logoHtml<div style="font-size:20px;font-weight:700;margin-top:4px;">$owner</div></td>
      <td align="right" style="color:#93c5fd;font-size:24px;font-weight:800;">$docLabel<br>
        <span style="font-size:13px;font-family:monospace;">$number</span></td>
    </tr></table>
  </td></tr>

  <!-- Meta -->
  <tr><td style="padding:20px 30px;border-bottom:1px solid #edebe9;">
    <table width="100%"><tr>
      <td><span style="color:#a19f9d;font-size:11px;">CLIENT</span><br><strong>$client</strong></td>
      <td><span style="color:#a19f9d;font-size:11px;">DATE</span><br>$dateVal</td>
      <td align="right"><span style="color:#a19f9d;font-size:11px;">MONTANT</span><br>
        <strong style="font-size:18px;color:#0078d4;">$amount</strong></td>
    </tr></table>
  </td></tr>

  <!-- Intro text -->
  <tr><td style="padding:20px 30px;color:#605e5c;line-height:1.6;">$intro</td></tr>

  <!-- Lines table -->
  <tr><td style="padding:0 30px 20px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #edebe9;border-radius:8px;overflow:hidden;">
      <thead><tr style="background:#1B2A4A;color:#fff;">
        <th style="padding:8px 10px;text-align:left;font-size:11px;">DÉSIGNATION</th>
        <th style="padding:8px 10px;text-align:center;font-size:11px;">QTÉ</th>
        <th style="padding:8px 10px;text-align:right;font-size:11px;">P.U.</th>
        <th style="padding:8px 10px;text-align:right;font-size:11px;">MONTANT</th>
      </tr></thead>
      <tbody>$linesHtml</tbody>
      <tfoot><tr style="background:#1B2A4A;color:#fff;">
        <td colspan="3" style="padding:10px;text-align:right;font-weight:700;">TOTAL TTC</td>
        <td style="padding:10px;text-align:right;font-weight:700;font-size:16px;">$amount</td>
      </tr></tfoot>
    </table>
  </td></tr>

  <!-- Footer -->
  <tr><td style="padding:20px 30px;background:#f3f2f1;border-top:1px solid #edebe9;text-align:center;color:#a19f9d;font-size:11px;">
    Auto-Entrepreneur exonéré de TVA — Art. 91-I-B-1° du CGI marocain<br>
    $owner
  </td></tr>

</table>
</td></tr></table>
</body></html>
HTML;
}

function defaultEmailBody(string $type): string
{
    if ($type === 'invoice') {
        return "Bonjour {{client}},\n\nVeuillez trouver ci-dessous votre facture <strong>{{number}}</strong> d'un montant de <strong>{{amount}}</strong>.\n\nMerci pour votre confiance.\n\nCordialement,\n{{owner}}";
    }
    return "Bonjour {{client}},\n\nVeuillez trouver ci-dessous votre devis <strong>{{number}}</strong> d'un montant de <strong>{{amount}}</strong>.\n\nN'hésitez pas à nous contacter pour toute question.\n\nCordialement,\n{{owner}}";
}
