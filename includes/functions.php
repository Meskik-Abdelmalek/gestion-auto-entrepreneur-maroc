<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

// ── Config ────────────────────────────────────────────────────
function getConfig(): array {
    static $cfg = null;
    if ($cfg === null) $cfg = getDB()->query('SELECT * FROM ae_config WHERE id=1')->fetch() ?: [];
    return $cfg;
}

// ── Activities (from settings) ────────────────────────────────
function getActivities(): array {
    $cfg = getConfig();
    return array_filter([
        $cfg['activity_1'] ?? '',
        $cfg['activity_2'] ?? '',
        $cfg['activity_3'] ?? '',
    ], fn($a) => $a !== '');
}

// ── Money ─────────────────────────────────────────────────────
function money(float $n, bool $cur = true): string {
    $c = $cur ? ' ' . (getConfig()['currency'] ?? 'MAD') : '';
    return number_format($n, 2, '.', ' ') . $c;
}

// ── Quarter ───────────────────────────────────────────────────
function getQuarter(string $date): string {
    return 'Q' . ceil((int)date('n', strtotime($date)) / 3) . '-' . date('Y', strtotime($date));
}

// ── Next invoice number ───────────────────────────────────────
function nextInvoiceNumber(): string {
    $db = getDB(); $ym = date('Ym');
    $s = $db->prepare("SELECT COUNT(*) FROM ae_invoices WHERE invoice_number LIKE ?");
    $s->execute(["FAC-{$ym}-%"]);
    return sprintf('FAC-%s-%03d', $ym, (int)$s->fetchColumn() + 1);
}

// ── Next quote number ─────────────────────────────────────────
function nextQuoteNumber(): string {
    $db = getDB(); $ym = date('Ym');
    $s = $db->prepare("SELECT COUNT(*) FROM ae_quotes WHERE quote_number LIKE ?");
    $s->execute(["DEV-{$ym}-%"]);
    return sprintf('DEV-%s-%03d', $ym, (int)$s->fetchColumn() + 1);
}

// ── Trend ─────────────────────────────────────────────────────
function getTrend(float $current, float $previous): array {
    if ($previous == 0) return ['pct' => null, 'dir' => 'neutral'];
    $pct = (($current - $previous) / $previous) * 100;
    return ['pct' => round($pct, 1), 'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'neutral')];
}

// ── Dashboard stats ───────────────────────────────────────────
function getDashboardStats(): array {
    $db = getDB(); $cfg = getConfig();
    $fy = $cfg['fiscal_year'] ?? date('Y');
    $cm = (int)date('n');
    $lm = $cm === 1 ? 12 : $cm - 1;
    $lmY = $cm === 1 ? $fy - 1 : $fy;

    $rev = $db->prepare("SELECT
        COALESCE(SUM(CASE WHEN status='Payé' THEN amount_ttc ELSE 0 END),0) AS total_paid,
        COALESCE(SUM(CASE WHEN status='Payé' AND category='Service' THEN amount_ttc ELSE 0 END),0) AS services_paid,
        COALESCE(SUM(CASE WHEN status='Payé' AND category='Commerce' THEN amount_ttc ELSE 0 END),0) AS commerce_paid,
        COALESCE(SUM(CASE WHEN status='En attente' THEN amount_ttc ELSE 0 END),0) AS total_pending,
        COUNT(CASE WHEN status='Payé' THEN 1 END) AS count_paid,
        COUNT(CASE WHEN status='En attente' THEN 1 END) AS count_pending,
        COUNT(CASE WHEN status='Annulé' THEN 1 END) AS count_cancelled
        FROM ae_invoices WHERE fiscal_year=?");
    $rev->execute([$fy]); $revenue = $rev->fetch();

    $cmR = $db->prepare("SELECT COALESCE(SUM(amount_ttc),0) FROM ae_invoices WHERE status='Payé' AND MONTH(payment_date)=? AND YEAR(payment_date)=?");
    $cmR->execute([$cm, $fy]); $thisMonthRev = (float)$cmR->fetchColumn();
    $lmR = $db->prepare("SELECT COALESCE(SUM(amount_ttc),0) FROM ae_invoices WHERE status='Payé' AND MONTH(payment_date)=? AND YEAR(payment_date)=?");
    $lmR->execute([$lm, $lmY]); $lastMonthRev = (float)$lmR->fetchColumn();
    $revTrend = getTrend($thisMonthRev, $lastMonthRev);

    $ir_due = $revenue['services_paid'] * ($cfg['ir_rate_services'] ?? 0.01)
            + $revenue['commerce_paid'] * ($cfg['ir_rate_commerce'] ?? 0.005);
    $ips = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM ae_tax_payments WHERE payment_type='IR' AND fiscal_year=?");
    $ips->execute([$fy]); $ir_paid = (float)$ips->fetchColumn();
    $cnss_due = ($cfg['cnss_monthly'] ?? 100) * $cm;
    $cps = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM ae_tax_payments WHERE payment_type='CNSS' AND fiscal_year=?");
    $cps->execute([$fy]); $cnss_paid = (float)$cps->fetchColumn();
    $es = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM ae_expenses WHERE fiscal_year=?");
    $es->execute([$fy]); $total_expenses = (float)$es->fetchColumn();

    // Quotes stats
    $qs = $db->prepare("SELECT COUNT(CASE WHEN status='Brouillon' THEN 1 END) draft, COUNT(CASE WHEN status='Envoyé' THEN 1 END) sent, COUNT(CASE WHEN status='Accepté' THEN 1 END) accepted, COALESCE(SUM(CASE WHEN status='Accepté' THEN amount_ttc END),0) accepted_val FROM ae_quotes WHERE YEAR(quote_date)=?");
    $qs->execute([$fy]); $quoteSt = $qs->fetch();

    $ceiling_svc = (float)($cfg['ceiling_services'] ?? 200000);
    $ceiling_com = (float)($cfg['ceiling_commerce'] ?? 500000);
    $svc_pct = $ceiling_svc > 0 ? $revenue['services_paid'] / $ceiling_svc : 0;
    $com_pct = $ceiling_com > 0 ? $revenue['commerce_paid'] / $ceiling_com : 0;

    $alert = 'normal';
    if ($svc_pct >= ($cfg['alert_red'] ?? 0.95) || $com_pct >= ($cfg['alert_red'] ?? 0.95)) $alert = 'red';
    elseif ($svc_pct >= ($cfg['alert_orange'] ?? 0.85) || $com_pct >= ($cfg['alert_orange'] ?? 0.85)) $alert = 'orange';
    elseif ($svc_pct >= ($cfg['alert_yellow'] ?? 0.75) || $com_pct >= ($cfg['alert_yellow'] ?? 0.75)) $alert = 'yellow';

    $cls = $db->prepare("SELECT COUNT(DISTINCT client_name) FROM ae_invoices WHERE status='Payé' AND fiscal_year=?");
    $cls->execute([$fy]); $unique_clients = (int)$cls->fetchColumn();

    $ms = $db->prepare("SELECT MONTH(invoice_date) AS m,
        COALESCE(SUM(CASE WHEN status='Payé' THEN amount_ttc ELSE 0 END),0) AS paid,
        COALESCE(SUM(CASE WHEN status='En attente' THEN amount_ttc ELSE 0 END),0) AS pending
        FROM ae_invoices WHERE fiscal_year=? GROUP BY m ORDER BY m");
    $ms->execute([$fy]);
    $monthly = array_fill(1, 12, ['paid' => 0, 'pending' => 0]);
    foreach ($ms->fetchAll() as $r) $monthly[(int)$r['m']] = $r;

    $projection = $cm > 0 ? (int)round($revenue['total_paid'] / $cm * 12) : 0;
    $net = $revenue['total_paid'] - $ir_due - $cnss_due - $total_expenses;

    return compact('revenue','ir_due','ir_paid','cnss_due','cnss_paid','total_expenses',
        'svc_pct','com_pct','alert','unique_clients','monthly','net','fy',
        'ceiling_svc','ceiling_com','thisMonthRev','lastMonthRev','revTrend','projection','quoteSt');
}

// ── Quote helpers ─────────────────────────────────────────────
function getDeclarations(int $year): array {
    $db = getDB(); $cfg = getConfig(); $rows = [];
    foreach (['Q1','Q2','Q3','Q4'] as $q) {
        $qk = "$q-$year";
        $st = $db->prepare("SELECT COALESCE(SUM(CASE WHEN category='Service' THEN amount_ttc ELSE 0 END),0) ca_s, COALESCE(SUM(CASE WHEN category='Commerce' THEN amount_ttc ELSE 0 END),0) ca_c FROM ae_invoices WHERE status='Payé' AND quarter=?");
        $st->execute([$qk]); $d = $st->fetch();
        $ir_s = $d['ca_s'] * ($cfg['ir_rate_services'] ?? 0.01);
        $ir_c = $d['ca_c'] * ($cfg['ir_rate_commerce'] ?? 0.005);
        $ps = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM ae_tax_payments WHERE payment_type='IR' AND quarter=? AND fiscal_year=?");
        $ps->execute([$qk,$year]); $ip = (float)$ps->fetchColumn();
        $it = $ir_s + $ir_c;
        $rows[] = ['quarter'=>$qk,'ca_services'=>$d['ca_s'],'ca_commerce'=>$d['ca_c'],'ca_total'=>$d['ca_s']+$d['ca_c'],
            'ir_services'=>$ir_s,'ir_commerce'=>$ir_c,'ir_total'=>$it,'ir_paid'=>$ip,
            'ir_balance'=>max(0,$it-$ip),'status'=>$ip>=$it&&$it>0?'paid':($it>0?'due':'empty')];
    }
    return $rows;
}

function nextDeclarationDeadline(): string {
    $m=(int)date('n'); $y=(int)date('Y');
    $n=(intdiv($m-1,3)*3)+4; if($n>12){$n-=12;$y++;}
    return date('d/m/Y', mktime(0,0,0,$n,1,$y));
}

function daysUntilDeclaration(): int {
    $m=(int)date('n'); $y=(int)date('Y');
    $n=(intdiv($m-1,3)*3)+4; if($n>12){$n-=12;$y++;}
    return max(0,(int)ceil((mktime(0,0,0,$n,1,$y)-time())/86400));
}

function getOverdueInvoices(): array {
    return getDB()->query("SELECT i.*, DATEDIFF(CURDATE(),i.invoice_date) AS days_overdue,
        r.status AS reminder_status FROM ae_invoices i
        LEFT JOIN ae_reminders r ON r.invoice_id=i.id
        WHERE i.status='En attente' ORDER BY i.invoice_date ASC")->fetchAll();
}

function getNotifications(): array {
    $db = getDB(); $cfg = getConfig(); $fy = $cfg['fiscal_year'] ?? date('Y'); $notes = [];
    $oc = (int)$db->query("SELECT COUNT(*) FROM ae_invoices WHERE status='En attente' AND DATEDIFF(CURDATE(),invoice_date)>30")->fetchColumn();
    if ($oc > 0) $notes[] = ['type'=>'warning','icon'=>'🔔','msg'=>"$oc facture(s) en retard >30j",'href'=>'/reminders.php'];
    $s = $db->prepare("SELECT COALESCE(SUM(amount_ttc),0) FROM ae_invoices WHERE status='Payé' AND category='Service' AND fiscal_year=?");
    $s->execute([$fy]); $svc=(float)$s->fetchColumn(); $ceil=(float)($cfg['ceiling_services']??200000);
    $p = $ceil>0 ? $svc/$ceil : 0;
    if ($p >= 0.95) $notes[] = ['type'=>'error','icon'=>'🚨','msg'=>'Plafond Services à '.round($p*100).'%','href'=>'/dashboard.php'];
    elseif ($p >= 0.75) $notes[] = ['type'=>'warning','icon'=>'⚠️','msg'=>'Plafond Services à '.round($p*100).'%','href'=>'/dashboard.php'];
    $days = daysUntilDeclaration();
    if ($days <= 15) $notes[] = ['type'=>$days<=7?'error':'warning','icon'=>'📅','msg'=>"Déclaration IR dans $days jour(s)",'href'=>'/declarations.php'];
    // Expired quotes
    $eq = (int)$db->query("SELECT COUNT(*) FROM ae_quotes WHERE status='Envoyé' AND valid_until < CURDATE()")->fetchColumn();
    if ($eq > 0) $notes[] = ['type'=>'warning','icon'=>'📋','msg'=>"$eq devis expiré(s)",'href'=>'/quotes.php'];
    return $notes;
}

function globalSearch(string $q, int $limit = 12): array {
    $db = getDB(); $like = "%$q%"; $results = [];
    $st = $db->prepare("SELECT id,invoice_number,client_name,amount_ttc,status FROM ae_invoices WHERE invoice_number LIKE ? OR client_name LIKE ? LIMIT 6");
    $st->execute([$like,$like]);
    foreach ($st->fetchAll() as $r)
        $results[] = ['icon'=>'📄','title'=>$r['invoice_number'],'sub'=>$r['client_name'].' — '.money($r['amount_ttc']),'href'=>'/invoice-view.php?id='.$r['id'],'badge'=>$r['status']];
    // Quotes
    $st = $db->prepare("SELECT id,quote_number,client_name,amount_ttc,status FROM ae_quotes WHERE quote_number LIKE ? OR client_name LIKE ? LIMIT 4");
    $st->execute([$like,$like]);
    foreach ($st->fetchAll() as $r)
        $results[] = ['icon'=>'📋','title'=>$r['quote_number'],'sub'=>$r['client_name'].' — '.money($r['amount_ttc']),'href'=>'/quote-view.php?id='.$r['id'],'badge'=>$r['status']];
    $st = $db->prepare("SELECT id,name,city FROM ae_clients WHERE name LIKE ? OR city LIKE ? LIMIT 3");
    $st->execute([$like,$like]);
    foreach ($st->fetchAll() as $r)
        $results[] = ['icon'=>'👤','title'=>$r['name'],'sub'=>$r['city']??'','href'=>'/clients.php?view='.$r['id'],'badge'=>null];
    return array_slice($results, 0, $limit);
}

// ── Convert quote to invoice ──────────────────────────────────
function convertQuoteToInvoice(int $quoteId): int {
    $db = getDB();
    $st = $db->prepare("SELECT * FROM ae_quotes WHERE id=?"); $st->execute([$quoteId]); $q = $st->fetch();
    if (!$q || $q['converted_invoice_id']) return 0;

    $num    = nextInvoiceNumber();
    $today  = date('Y-m-d');
    $due    = date('Y-m-d', strtotime('+30 days'));
    $qtr    = getQuarter($today);
    $fy     = (int)date('Y');

    $db->prepare("INSERT INTO ae_invoices (invoice_number,client_id,client_name,invoice_date,due_date,category,activity,amount_ht,has_tva,amount_ttc,status,quarter,fiscal_year,from_quote_id,notes)
        VALUES (?,?,?,?,?,?,?,?,0,?,'En attente',?,?,?,?)")
       ->execute([$num,$q['client_id'],$q['client_name'],$today,$due,$q['category'],$q['activity'],$q['amount_ht'],$q['amount_ttc'],$qtr,$fy,$quoteId,$q['notes']]);
    $invId = (int)$db->lastInsertId();

    // Copy lines
    $ls = $db->prepare("SELECT * FROM ae_quote_lines WHERE quote_id=? ORDER BY sort_order");
    $ls->execute([$quoteId]);
    foreach ($ls->fetchAll() as $l)
        $db->prepare("INSERT INTO ae_invoice_lines (invoice_id,description,quantity,unit_price,amount,sort_order) VALUES (?,?,?,?,?,?)")
           ->execute([$invId,$l['description'],$l['quantity'],$l['unit_price'],$l['amount'],$l['sort_order']]);

    // Mark quote as converted
    $db->prepare("UPDATE ae_quotes SET status='Accepté', converted_invoice_id=? WHERE id=?")->execute([$invId, $quoteId]);
    // Create reminder
    $db->prepare("INSERT INTO ae_reminders (invoice_id) VALUES (?)")->execute([$invId]);
    return $invId;
}

// ── Duplicate invoice ─────────────────────────────────────────
function duplicateInvoice(int $id): int {
    $db = getDB();
    $st = $db->prepare("SELECT * FROM ae_invoices WHERE id=?"); $st->execute([$id]); $inv = $st->fetch();
    if (!$inv) return 0;
    $newNum = nextInvoiceNumber(); $today = date('Y-m-d');
    $db->prepare("INSERT INTO ae_invoices (invoice_number,client_id,client_name,invoice_date,due_date,category,activity,amount_ht,has_tva,amount_ttc,status,quarter,fiscal_year,notes) VALUES (?,?,?,?,?,?,?,?,?,?,'En attente',?,?,?)")
       ->execute([$newNum,$inv['client_id'],$inv['client_name'],$today,date('Y-m-d',strtotime('+30 days')),$inv['category'],$inv['activity']??'',$inv['amount_ht'],$inv['has_tva'],$inv['amount_ttc'],getQuarter($today),(int)date('Y'),$inv['notes']]);
    $newId = (int)$db->lastInsertId();
    $ls = $db->prepare("SELECT * FROM ae_invoice_lines WHERE invoice_id=? ORDER BY sort_order"); $ls->execute([$id]);
    foreach ($ls->fetchAll() as $l)
        $db->prepare("INSERT INTO ae_invoice_lines (invoice_id,description,quantity,unit_price,amount,sort_order) VALUES (?,?,?,?,?,?)")
           ->execute([$newId,$l['description'],$l['quantity'],$l['unit_price'],$l['amount'],$l['sort_order']]);
    $db->prepare("INSERT INTO ae_reminders (invoice_id) VALUES (?)")->execute([$newId]);
    return $newId;
}

// ── Duplicate quote ───────────────────────────────────────────
function duplicateQuote(int $id): int {
    $db = getDB();
    $st = $db->prepare("SELECT * FROM ae_quotes WHERE id=?"); $st->execute([$id]); $q = $st->fetch();
    if (!$q) return 0;
    $newNum = nextQuoteNumber(); $today = date('Y-m-d');
    $cfg = getConfig(); $days = (int)($cfg['quote_validity_days'] ?? 30);
    $db->prepare("INSERT INTO ae_quotes (quote_number,client_id,client_name,quote_date,valid_until,category,activity,amount_ht,amount_ttc,status,notes) VALUES (?,?,?,?,?,?,?,?,?,'Brouillon',?)")
       ->execute([$newNum,$q['client_id'],$q['client_name'],$today,date('Y-m-d',strtotime("+$days days")),$q['category'],$q['activity']??'',$q['amount_ht'],$q['amount_ttc'],$q['notes']]);
    $newId = (int)$db->lastInsertId();
    $ls = $db->prepare("SELECT * FROM ae_quote_lines WHERE quote_id=? ORDER BY sort_order"); $ls->execute([$id]);
    foreach ($ls->fetchAll() as $l)
        $db->prepare("INSERT INTO ae_quote_lines (quote_id,description,quantity,unit_price,amount,sort_order) VALUES (?,?,?,?,?,?)")
           ->execute([$newId,$l['description'],$l['quantity'],$l['unit_price'],$l['amount'],$l['sort_order']]);
    return $newId;
}

// ── Bulk invoices ─────────────────────────────────────────────
function bulkUpdateInvoices(array $ids, string $action): int {
    if (empty($ids)) return 0;
    $db = getDB(); $phs = implode(',', array_fill(0, count($ids), '?'));
    if ($action === 'mark_paid') {
        $db->prepare("UPDATE ae_invoices SET status='Payé',payment_date=CURDATE() WHERE id IN ($phs)")->execute($ids);
        $db->prepare("UPDATE ae_reminders SET status='Payé' WHERE invoice_id IN ($phs)")->execute($ids);
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM ae_invoices WHERE id IN ($phs)")->execute($ids);
    } elseif ($action === 'mark_cancelled') {
        $db->prepare("UPDATE ae_invoices SET status='Annulé' WHERE id IN ($phs)")->execute($ids);
    }
    return count($ids);
}

// ── CSV exports ───────────────────────────────────────────────
function exportInvoicesCSV(array $filters = []): void {
    $db = getDB(); $where = ['1=1']; $params = [];
    if (!empty($filters['year']))   { $where[] = 'fiscal_year=?'; $params[] = $filters['year']; }
    if (!empty($filters['status'])) { $where[] = 'status=?';      $params[] = $filters['status']; }
    $st = $db->prepare("SELECT invoice_number,client_name,invoice_date,due_date,category,activity,amount_ht,amount_ttc,status,payment_date,payment_method,quarter,notes FROM ae_invoices WHERE ".implode(' AND ',$where)." ORDER BY invoice_date DESC");
    $st->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="factures-'.date('Y-m-d').'.csv"');
    $o = fopen('php://output','w'); fprintf($o,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($o,['N° Facture','Client','Date','Échéance','Catégorie','Activité','HT','TTC','Statut','Paiement','Mode','Trimestre','Notes'],';');
    foreach ($st->fetchAll() as $r) fputcsv($o, array_values($r), ';');
    fclose($o); exit;
}

function exportExpensesCSV(int $year): void {
    $db = getDB();
    $st = $db->prepare("SELECT expense_date,supplier,expense_number,description,category,amount,payment_method,has_receipt,notes FROM ae_expenses WHERE fiscal_year=? ORDER BY expense_date DESC");
    $st->execute([$year]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="depenses-'.$year.'.csv"');
    $o = fopen('php://output','w'); fprintf($o,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($o,['Date','Fournisseur','N°','Description','Catégorie','Montant','Mode','Justificatif','Notes'],';');
    foreach ($st->fetchAll() as $r) { $r['has_receipt'] = $r['has_receipt']?'Oui':'Non'; fputcsv($o,array_values($r),';'); }
    fclose($o); exit;
}

function exportQuotesCSV(int $year): void {
    $db = getDB();
    $st = $db->prepare("SELECT quote_number,client_name,quote_date,valid_until,category,activity,amount_ht,amount_ttc,status,notes FROM ae_quotes WHERE YEAR(quote_date)=? ORDER BY quote_date DESC");
    $st->execute([$year]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="devis-'.$year.'.csv"');
    $o = fopen('php://output','w'); fprintf($o,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($o,['N° Devis','Client','Date','Validité','Catégorie','Activité','HT','TTC','Statut','Notes'],';');
    foreach ($st->fetchAll() as $r) fputcsv($o, array_values($r), ';');
    fclose($o); exit;
}

// ── Activity feed ─────────────────────────────────────────────
function getActivityFeed(int $limit = 8): array {
    $db = getDB(); $feed = [];
    $st = $db->prepare("SELECT id,'invoice' t,invoice_number ref,client_name,amount_ttc,status,created_at FROM ae_invoices ORDER BY created_at DESC LIMIT ?");
    $st->execute([$limit]); foreach ($st->fetchAll() as $r) $feed[] = $r;
    $st = $db->prepare("SELECT id,'quote' t,quote_number ref,client_name,amount_ttc,status,created_at FROM ae_quotes ORDER BY created_at DESC LIMIT 4");
    $st->execute([]); foreach ($st->fetchAll() as $r) $feed[] = $r;
    $st = $db->prepare("SELECT id,'expense' t,COALESCE(expense_number,'DEP') ref,COALESCE(supplier,'—') client_name,amount amount_ttc,'Dépense' status,created_at FROM ae_expenses ORDER BY created_at DESC LIMIT 3");
    $st->execute([]); foreach ($st->fetchAll() as $r) $feed[] = $r;
    usort($feed, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
    return array_slice($feed, 0, $limit);
}

// ── Flash ─────────────────────────────────────────────────────
function flash(string $key, string $msg = '', string $type = 'success'): ?array {
    if (!isset($_SESSION)) session_start();
    if ($msg) { $_SESSION['flash'][$key] = ['msg'=>$msg,'type'=>$type]; return null; }
    if (isset($_SESSION['flash'][$key])) { $f=$_SESSION['flash'][$key]; unset($_SESSION['flash'][$key]); return $f; }
    return null;
}

// ── CSRF ──────────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verifyCsrf(): void {
    // Add $_GET['_csrf'] to the fallback chain
    $token = $_POST['_csrf'] ?? $_GET['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) { 
        http_response_code(403); 
        die('CSRF validation failed'); 
    }
}

// ── Helpers ───────────────────────────────────────────────────
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function clean(mixed $v): string { return trim((string)$v); }
function timeAgo(string $dt): string {
    $d = time()-strtotime($dt);
    if ($d < 60) return "À l'instant";
    if ($d < 3600) return intdiv($d,60).'min';
    if ($d < 86400) return intdiv($d,3600).'h';
    return date('d/m/Y', strtotime($dt));
}
function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit;
}
