<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email.php';
$db  = getDB();
$cfg = getConfig();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /invoices.php'); exit; }

$stmt = $db->prepare("SELECT * FROM ae_invoices WHERE id=?");
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { header('Location: /invoices.php'); exit; }

$lines = $db->prepare("SELECT * FROM ae_invoice_lines WHERE invoice_id=? ORDER BY sort_order");
$lines->execute([$id]);
$lines = $lines->fetchAll();

$pageName = 'Facture ' . $inv['invoice_number'];
$isPrint  = isset($_GET['print']);

// Quick pay
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='mark_paid') {
    verifyCsrf();
    $db->prepare("UPDATE ae_invoices SET status='Payé',payment_date=?,payment_method=? WHERE id=?")
       ->execute([clean($_POST['payment_date']??date('Y-m-d')), clean($_POST['payment_method']??''), $id]);
    $db->prepare("UPDATE ae_reminders SET status='Payé' WHERE invoice_id=?")->execute([$id]);
    flash('message','Facture marquée comme payée !');
    header("Location: /invoice-view.php?id=$id"); exit;
}

$irRate     = (float)($cfg[$inv['category']==='Commerce'?'ir_rate_commerce':'ir_rate_services'] ?? 0.01);
$irEstimate = (float)$inv['amount_ttc'] * $irRate;

// Client email for modal pre-fill
$clientEmail = '';
if ($inv['client_id']) {
    $ce = $db->prepare("SELECT email FROM ae_clients WHERE id=?");
    $ce->execute([$inv['client_id']]);
    $clientEmail = $ce->fetchColumn() ?: '';
}

// ── PRINT LAYOUT ──────────────────────────────────────────────
if ($isPrint):
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8">
<title>Facture <?= h($inv['invoice_number']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{navy:'#1B2A4A',blue:'#0078d4'}},fontFamily:{sans:['"Segoe UI"','Arial','sans-serif']}}}}</script>
<style>
  @media print{@page{size:A4;margin:14mm 16mm}body{print-color-adjust:exact;-webkit-print-color-adjust:exact}.no-print{display:none!important}}
  body{font-family:"Segoe UI",Arial,sans-serif;}
</style>
</head>
<body class="bg-white text-gray-800 text-sm p-8 max-w-3xl mx-auto">

<!-- Print toolbar -->
<div class="no-print flex flex-wrap gap-2 mb-6 p-3 bg-gray-50 rounded-xl border border-gray-200">
    <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold text-sm hover:bg-blue-700">🖨️ Imprimer / PDF</button>
    <a href="/invoice-view.php?id=<?= $id ?>" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">← Retour</a>
    <a href="/api/pdf.php?type=invoice&id=<?= $id ?>" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">📥 Télécharger PDF</a>
</div>

<!-- Header -->
<div class="flex justify-between items-start mb-8">
    <div>
        <?php if (getLogoPath() && logoExistsOnDisk(getLogoPath())): ?>
        <img src="<?= h(getLogoPath() ?? "") ?>" alt="Logo"
            style="max-height:60px;max-width:<?= (int)($cfg["logo_width_mm"]??40)*3 ?>px;margin-bottom:8px;display:block;">
        <?php endif; ?>
        <div class="text-2xl font-bold text-navy mb-1"><?= h($cfg['owner_name']??'') ?></div>
        <div class="text-xs text-gray-500 space-y-0.5">
            <div><?= nl2br(h($cfg['address']??'')) ?></div>
            <div>ICE : <?= h($cfg['ice']??'') ?> &nbsp;|&nbsp; IF : <?= h($cfg['if_fiscal']??'') ?></div>
            <div>TP : <?= h($cfg['tp']??'') ?> &nbsp;|&nbsp; Tél : <?= h($cfg['cnss_phone']??'') ?></div>
            <?php if ($cfg['email']??''): ?><div><?= h($cfg['email']) ?></div><?php endif; ?>
        </div>
    </div>
    <div class="text-right">
        <div class="text-3xl font-black text-navy tracking-tight">FACTURE</div>
        <div class="font-mono text-blue text-lg font-bold mt-1"><?= h($inv['invoice_number']) ?></div>
        <?php
        $sc = ['Payé'=>'#107c10','En attente'=>'#d97706','Annulé'=>'#d13438'][$inv['status']] ?? '#6b7280';
        ?>
        <span class="inline-block mt-1 px-3 py-0.5 rounded-full text-xs font-bold text-white" style="background:<?= $sc ?>">
            <?= h($inv['status']) ?>
        </span>
    </div>
</div>

<hr class="border-navy border-2 mb-6">

<!-- Meta row -->
<div class="grid grid-cols-3 gap-6 mb-6 text-xs">
    <div>
        <div class="text-gray-400 uppercase text-[10px] tracking-wider mb-1">Client</div>
        <div class="font-bold text-navy"><?= h($inv['client_name']) ?></div>
    </div>
    <div>
        <div class="text-gray-400 uppercase text-[10px] tracking-wider mb-1">Date</div>
        <div class="font-bold"><?= $inv['invoice_date'] ? date('d/m/Y',strtotime($inv['invoice_date'])) : '—' ?></div>
        <?php if ($inv['due_date']): ?>
        <div class="text-gray-500 text-[10px] mt-0.5">Échéance : <?= date('d/m/Y',strtotime($inv['due_date'])) ?></div>
        <?php endif; ?>
    </div>
    <div class="text-right">
        <div class="text-gray-400 uppercase text-[10px] tracking-wider mb-1">Montant TTC</div>
        <div class="font-black text-navy text-xl"><?= money($inv['amount_ttc']) ?></div>
    </div>
</div>

<!-- Lines table -->
<table class="w-full text-xs mb-6" style="border-collapse:collapse">
    <thead>
        <tr style="background:#1B2A4A;color:#fff;">
            <th class="px-3 py-2 text-left rounded-tl-lg">Désignation</th>
            <th class="px-3 py-2 text-center w-16">Qté</th>
            <th class="px-3 py-2 text-right w-24">Prix unit.</th>
            <th class="px-3 py-2 text-right w-28 rounded-tr-lg">Montant</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lines as $i=>$l): ?>
        <tr style="background:<?= $i%2===0?'#fafafa':'#fff' ?>;border-bottom:1px solid #edebe9;">
            <td class="px-3 py-2"><?= h($l['description']) ?></td>
            <td class="px-3 py-2 text-center"><?= number_format((float)$l['quantity'],2) ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)$l['unit_price'],2,'.',' ') ?></td>
            <td class="px-3 py-2 text-right font-bold"><?= number_format((float)$l['amount'],2,'.',' ') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr style="background:#1B2A4A;color:#fff;">
            <td colspan="3" class="px-3 py-2 text-right font-bold">Total HT</td>
            <td class="px-3 py-2 text-right font-bold"><?= money($inv['amount_ht']) ?></td>
        </tr>
        <tr style="background:#e8f0fe;">
            <td colspan="3" class="px-3 py-1.5 text-right text-gray-600 text-[10px]">TVA (Exonéré AE)</td>
            <td class="px-3 py-1.5 text-right text-gray-500 text-[10px] italic">Art. 91-I-B-1° CGI</td>
        </tr>
        <tr style="background:#1B2A4A;color:#fff;">
            <td colspan="3" class="px-3 py-3 text-right font-black text-sm">TOTAL TTC</td>
            <td class="px-3 py-3 text-right font-black text-sm"><?= money($inv['amount_ttc']) ?></td>
        </tr>
    </tfoot>
</table>

<!-- IR + payment -->
<div class="grid grid-cols-2 gap-6 text-xs mb-6">
    <div>
        <div class="font-bold text-navy mb-1">Modalités de règlement</div>
        <?php if ($cfg['bank_rib']??''): ?><div class="text-gray-600">RIB : <?= h($cfg['bank_rib']) ?></div><?php endif; ?>
        <div class="text-gray-600">Réf. virement : <?= h($inv['invoice_number']) ?></div>
        <?php if ($inv['payment_method']??''): ?><div class="text-gray-600">Mode : <?= h($inv['payment_method']) ?></div><?php endif; ?>
    </div>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3">
        <div class="font-bold text-amber-800 mb-1">IR retenu à la source</div>
        <div class="text-amber-700"><?= money($irEstimate) ?> (<?= round($irRate*100,1) ?>%)</div>
        <div class="text-amber-600 text-[10px] mt-0.5">Retenu par le donneur d'ordre</div>
    </div>
</div>

<?php if ($inv['notes']??''): ?>
<div class="bg-gray-50 border border-gray-200 rounded-xl p-3 mb-4 text-xs text-gray-600">
    <strong>Notes :</strong> <?= h($inv['notes']) ?>
</div>
<?php endif; ?>

<?php if ($cfg['invoice_footer_text']??''): ?>
<div class="bg-gray-50 border border-gray-200 rounded-xl p-3 mb-4 text-xs text-gray-600"><?= h($cfg['invoice_footer_text']) ?></div>
<?php endif; ?>

<div class="border-t border-gray-200 pt-3 text-center text-gray-400 text-[10px]">
    <?= h($cfg['owner_name']??'') ?> &nbsp;·&nbsp; ICE : <?= h($cfg['ice']??'') ?> &nbsp;·&nbsp; Merci pour votre confiance
</div>

<script>
<?php if (isset($_GET['autoprint'])): ?>window.addEventListener('load', () => window.print());<?php endif; ?>
</script>
</body></html>
<?php exit; endif; // end print ?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- ── Action bar ───────────────────────────────────────────── -->
<div class="flex flex-wrap items-center gap-2 mb-5">
    <a href="/invoices.php" class="btn-f flex items-center gap-1.5 px-3 py-2 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 bg-white dark:bg-gray-800 shadow-f">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Retour
    </a>

    <!-- PDF server (v2.1 primary) -->
    <a href="/api/pdf.php?type=invoice&id=<?= $id ?>" target="_blank"
        class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-white dark:bg-gray-800 border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 shadow-f">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Télécharger PDF
    </a>

    <!-- Email send (v2.1) -->
    <button onclick="openEmailModal('invoice', <?= $id ?>, '<?= addslashes(h($clientEmail)) ?>', '<?= addslashes(h($inv['client_name'])) ?>')"
        class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-white dark:bg-gray-800 border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-blue-lt hover:text-fluent-blue hover:border-fluent-blue shadow-f">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        Envoyer par email
    </button>

    <!-- Print fallback -->
    <a href="/invoice-view.php?id=<?= $id ?>&print=1" target="_blank"
        class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-white dark:bg-gray-800 border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 shadow-f">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimer
    </a>

    <a href="/invoice-edit.php?id=<?= $id ?>"
        class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-white dark:bg-gray-800 border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 shadow-f">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Modifier
    </a>

    <?php if ($inv['status']!=='Payé'): ?>
    <button onclick="document.getElementById('pay-modal').classList.remove('hidden')"
        class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-fluent-green text-white rounded-xl font-semibold shadow-f hover:bg-green-700 ml-auto">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Marquer Payée
    </button>
    <?php endif; ?>
</div>

<!-- ── Main grid ─────────────────────────────────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <!-- Invoice preview card -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">

            <!-- Gradient header -->
            <div class="bg-gradient-to-r from-[#1B2A4A] to-[#0d1f38] px-6 py-5 text-white">
                <div class="flex items-start justify-between">
                    <div>
                        <?php if (getLogoPath() && logoExistsOnDisk(getLogoPath())): ?>
                        <img src="<?= h(getLogoPath() ?? "") ?>" alt="Logo" class="h-10 mb-2 object-contain">
                        <?php endif; ?>
                        <div class="text-lg font-bold"><?= h($cfg['owner_name']??'') ?></div>
                        <div class="text-xs text-white/60 mt-0.5 space-y-0.5">
                            <?php if ($cfg['address']??''): ?><div><?= h($cfg['address']) ?></div><?php endif; ?>
                            <div class="flex gap-3 flex-wrap">
                                <?php if ($cfg['ice']??''): ?><span>ICE : <?= h($cfg['ice']) ?></span><?php endif; ?>
                                <?php if ($cfg['if_fiscal']??''): ?><span>IF : <?= h($cfg['if_fiscal']) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-black tracking-wider opacity-80">FACTURE</div>
                        <div class="font-mono text-fluent-blue text-lg font-bold"><?= h($inv['invoice_number']) ?></div>
                        <?php
                        $badgeCfg = ['Payé'=>['bg-green-500','✓ Payé'],'En attente'=>['bg-amber-500','⏳ En attente'],'Annulé'=>['bg-red-600','✕ Annulé']];
                        [$bc,$bl] = $badgeCfg[$inv['status']] ?? ['bg-gray-500',$inv['status']];
                        ?>
                        <span class="inline-block mt-1 px-3 py-0.5 rounded-full text-xs font-bold text-white <?= $bc ?>"><?= $bl ?></span>
                    </div>
                </div>
            </div>

            <!-- Meta strip -->
            <div class="grid grid-cols-3 divide-x divide-fluent-n5 dark:divide-white/10 border-b border-fluent-n5 dark:border-white/10">
                <?php
                $metaFields = [
                    ['Client',    h($inv['client_name'])],
                    ['Date',      $inv['invoice_date'] ? date('d/m/Y',strtotime($inv['invoice_date'])) : '—'],
                    ['Montant',   '<span class="font-bold text-fluent-blue">' . money($inv['amount_ttc']) . '</span>'],
                ];
                foreach ($metaFields as [$label,$val]):
                ?>
                <div class="px-4 py-3">
                    <div class="text-[10px] text-fluent-n3 uppercase tracking-wider mb-0.5"><?= $label ?></div>
                    <div class="text-sm font-semibold text-fluent-neutral"><?= $val ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Lines table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-fluent-n7 dark:bg-white/5">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-fluent-n3 uppercase tracking-wider">Désignation</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-fluent-n3 uppercase tracking-wider w-16">Qté</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-fluent-n3 uppercase tracking-wider w-24">P.U.</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-fluent-n3 uppercase tracking-wider w-28">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-fluent-n6 dark:divide-white/5">
                        <?php foreach ($lines as $l): ?>
                        <tr class="hover:bg-fluent-n7 dark:hover:bg-white/5">
                            <td class="px-5 py-3.5 text-fluent-neutral font-medium"><?= h($l['description']) ?></td>
                            <td class="px-4 py-3.5 text-center text-fluent-n2"><?= number_format((float)$l['quantity'],2) ?></td>
                            <td class="px-4 py-3.5 text-right text-fluent-n2"><?= money($l['unit_price']) ?></td>
                            <td class="px-5 py-3.5 text-right font-bold text-fluent-neutral"><?= money($l['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="px-5 py-4 border-t border-fluent-n5 dark:border-white/10">
                <div class="ml-auto max-w-xs space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-fluent-n3">Total HT</span>
                        <span class="text-fluent-neutral font-medium"><?= money($inv['amount_ht']) ?></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-fluent-n4 italic">TVA (exonéré AE)</span>
                        <span class="text-fluent-n4 italic">Art. 91-I-B-1°</span>
                    </div>
                    <div class="flex justify-between items-center py-2.5 px-4 bg-[#1B2A4A] dark:bg-[#0d1f38] rounded-xl text-white">
                        <span class="font-bold text-sm">Total TTC</span>
                        <span class="font-black text-lg"><?= money($inv['amount_ttc']) ?></span>
                    </div>
                    <div class="flex justify-between text-xs pt-1">
                        <span class="text-fluent-n3">IR retenu (<?= round($irRate*100,1) ?>%)</span>
                        <span class="text-amber-600 font-medium"><?= money($irEstimate) ?></span>
                    </div>
                </div>
            </div>

            <!-- Notes & footer -->
            <?php if ($inv['notes']||($cfg['invoice_footer_text']??'')): ?>
            <div class="px-5 pb-5 space-y-2">
                <?php if ($inv['notes']): ?>
                <div class="p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl text-xs text-fluent-n2">
                    <strong>Notes :</strong> <?= h($inv['notes']) ?>
                </div>
                <?php endif; ?>
                <?php if ($cfg['invoice_footer_text']??''): ?>
                <div class="p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl text-xs text-fluent-n2">
                    <?= h($cfg['invoice_footer_text']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Right panel -->
    <div class="space-y-3">

        <!-- Status card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <div class="text-xs font-semibold text-fluent-n3 uppercase tracking-wider mb-3">Statut paiement</div>
            <?php
            $statusConfig = [
                'Payé'       => ['bg-fluent-green-lt dark:bg-green-900/30', 'text-fluent-green', '✅', 'Payée'],
                'En attente' => ['bg-amber-50 dark:bg-amber-900/20', 'text-amber-600', '⏳', 'En attente'],
                'Annulé'     => ['bg-fluent-red-lt dark:bg-red-900/20', 'text-fluent-red', '✕', 'Annulée'],
            ];
            [$sbg,$stc,$sic,$slb] = $statusConfig[$inv['status']] ?? ['bg-fluent-n6','text-fluent-n2','?',$inv['status']];
            ?>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-12 h-12 rounded-xl <?= $sbg ?> flex items-center justify-center text-2xl"><?= $sic ?></div>
                <div>
                    <div class="font-semibold text-fluent-neutral"><?= $slb ?></div>
                    <?php if ($inv['payment_date']): ?>
                    <div class="text-xs text-fluent-n3">Le <?= date('d/m/Y',strtotime($inv['payment_date'])) ?></div>
                    <?php endif; ?>
                    <?php if ($inv['payment_method']): ?>
                    <div class="text-xs text-fluent-blue font-medium"><?= h($inv['payment_method']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($inv['status']==='En attente'):
                $age = (int)(date_diff(date_create($inv['invoice_date']),date_create())->days);
            ?>
            <div class="text-xs px-3 py-2 rounded-lg font-medium <?= $age>30?'bg-fluent-red-lt text-fluent-red':'bg-amber-50 text-amber-600' ?>">
                <?= $age>30?'⚠️':'⏱️' ?> <?= $age ?> jours depuis émission
            </div>
            <?php endif; ?>
        </div>

        <!-- Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <div class="text-xs font-semibold text-fluent-n3 uppercase tracking-wider mb-3">Résumé fiscal</div>
            <div class="space-y-2.5 text-sm">
                <?php foreach ([
                    ['Montant HT', money($inv['amount_ht'])],
                    ['Montant TTC', money($inv['amount_ttc']), true],
                    ['IR estimé ('.round($irRate*100,1).'%)', money($irEstimate)],
                    ['Activité', $inv['activity_label']??$inv['activity']??$inv['category']],
                    ['Trimestre', $inv['quarter']??'—'],
                    ['Exercice', $inv['fiscal_year']??'—'],
                ] as $metaRow): [$k,$v,$bold] = array_pad($metaRow, 3, false);
                ?>
                <div class="flex justify-between items-center <?= ($bold??false)?'font-semibold border-t border-fluent-n5 dark:border-white/10 pt-2.5':'' ?>">
                    <span class="text-fluent-n3 text-xs"><?= $k ?></span>
                    <span class="text-fluent-neutral text-xs"><?= $v ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Actions v2.1 -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <div class="text-xs font-semibold text-fluent-n3 uppercase tracking-wider mb-3">Actions</div>
            <div class="space-y-2">
                <a href="/api/pdf.php?type=invoice&id=<?= $id ?>" target="_blank"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-blue-lt hover:text-fluent-blue hover:border-fluent-blue transition-colors">
                    📥 Télécharger PDF
                </a>
                <button onclick="openEmailModal('invoice',<?= $id ?>,'<?= addslashes(h($clientEmail)) ?>','<?= addslashes(h($inv['client_name'])) ?>')"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-blue-lt hover:text-fluent-blue hover:border-fluent-blue transition-colors text-left">
                    📧 Envoyer par email
                </button>
                <a href="/invoice-edit.php?id=<?= $id ?>"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 transition-colors">
                    ✏️ Modifier
                </a>
                <a href="/invoice-view.php?id=<?= $id ?>&print=1" target="_blank"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 transition-colors">
                    🖨️ Imprimer
                </a>
                <a href="/reminders.php?invoice=<?= $id ?>"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 transition-colors">
                    📬 Relances
                </a>
                <a href="/api/invoices.php?action=delete&id=<?= $id ?>&_csrf=<?= $csrf ?>"
                    onclick="return confirmDelete('Supprimer définitivement cette facture ?')"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm border border-red-200 dark:border-red-900/50 rounded-xl text-fluent-red hover:bg-fluent-red-lt transition-colors">
                    🗑️ Supprimer
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ── Pay modal ─────────────────────────────────────────────── -->
<div id="pay-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
    <div class="w-full max-w-sm bg-white dark:bg-gray-800 rounded-2xl shadow-fxl overflow-hidden animate-slide-up">
        <div class="flex items-center justify-between px-5 py-4 border-b border-fluent-n5 dark:border-white/10">
            <h3 class="font-semibold text-sm text-fluent-neutral">Marquer comme payée</h3>
            <button onclick="document.getElementById('pay-modal').classList.add('hidden')" class="text-fluent-n3 hover:text-fluent-neutral">✕</button>
        </div>
        <form method="POST" class="px-5 py-4 space-y-3">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="mark_paid">
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1.5">Date de paiement</label>
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1.5">Mode de paiement</label>
                <select name="payment_method" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                    <?php foreach (['Virement','Chèque','Espèces','CB','PayPal','Mobile Money'] as $m): ?>
                    <option><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 btn-f py-2.5 bg-fluent-green text-white rounded-xl text-sm font-semibold hover:bg-green-700">✓ Confirmer</button>
                <button type="button" onclick="document.getElementById('pay-modal').classList.add('hidden')"
                    class="px-4 border border-fluent-n4 dark:border-white/20 rounded-xl text-sm text-fluent-n2 hover:bg-fluent-n6">Annuler</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
