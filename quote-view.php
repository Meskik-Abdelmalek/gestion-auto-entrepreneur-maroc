<?php
require_once __DIR__ . '/includes/functions.php';
$db  = getDB(); $cfg = getConfig();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /quotes.php'); exit; }

$st = $db->prepare("SELECT q.*, i.invoice_number AS linked_invoice FROM ae_quotes q LEFT JOIN ae_invoices i ON i.id=q.converted_invoice_id WHERE q.id=?");
$st->execute([$id]); $q = $st->fetch();
if (!$q) { header('Location: /quotes.php'); exit; }

$lines = $db->prepare("SELECT * FROM ae_quote_lines WHERE quote_id=? ORDER BY sort_order");
$lines->execute([$id]); $lines = $lines->fetchAll();

$pageName = 'Devis ' . $q['quote_number'];
$isPrint  = isset($_GET['print']);
$daysLeft = $q['valid_until'] ? (int)ceil((strtotime($q['valid_until'])-time())/86400) : null;
$isExpired= $daysLeft !== null && $daysLeft < 0 && !in_array($q['status'],['Accepté','Refusé','Converti']);

$clientEmail = '';
if ($q['client_id']) {
    $ce = $db->prepare("SELECT email FROM ae_clients WHERE id=?");
    $ce->execute([$q['client_id']]);
    $clientEmail = $ce->fetchColumn() ?: '';
}

// ── PRINT LAYOUT ──────────────────────────────────────────────
if ($isPrint):
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><title>Devis <?= h($q['quote_number']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{navy:'#1B2A4A',blue:'#0078d4'}}}}</script>
<style>
  @media print{@page{size:A4;margin:14mm 16mm}body{print-color-adjust:exact;-webkit-print-color-adjust:exact}.no-print{display:none!important}}
  body{font-family:"Segoe UI",Arial,sans-serif;}
</style>
</head>
<body class="bg-white text-gray-800 text-sm p-8 max-w-3xl mx-auto">
<div class="no-print flex gap-2 mb-6 p-3 bg-gray-50 rounded-xl border border-gray-200">
    <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold text-sm">🖨️ Imprimer</button>
    <a href="/quote-view.php?id=<?= $id ?>" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600">← Retour</a>
    <a href="/api/pdf.php?type=quote&id=<?= $id ?>" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm">📥 Télécharger PDF</a>
</div>
<div class="flex justify-between items-start mb-8">
    <div>
        <?php if (getLogoPath() && logoExistsOnDisk(getLogoPath())): ?>
        <img src="<?= h(getLogoPath() ?? "") ?>" alt="Logo" style="max-height:60px;max-width:<?= (int)($cfg["logo_width_mm"]??40)*3 ?>px;margin-bottom:8px;display:block;">
        <?php endif; ?>
        <div class="text-2xl font-bold text-navy mb-1"><?= h($cfg['owner_name']??'') ?></div>
        <div class="text-xs text-gray-500 space-y-0.5">
            <div><?= nl2br(h($cfg['address']??'')) ?></div>
            <div>ICE : <?= h($cfg['ice']??'') ?> · IF : <?= h($cfg['if_fiscal']??'') ?></div>
            <div>TP : <?= h($cfg['tp']??'') ?> · Tél : <?= h($cfg['cnss_phone']??'') ?></div>
        </div>
    </div>
    <div class="text-right">
        <div class="text-3xl font-black text-navy tracking-tight">DEVIS</div>
        <div class="font-mono text-blue text-lg font-bold mt-1"><?= h($q['quote_number']) ?></div>
        <?php if ($q['valid_until']): ?>
        <div class="text-xs text-gray-500 mt-1">Valable jusqu'au : <?= date('d/m/Y',strtotime($q['valid_until'])) ?></div>
        <?php endif; ?>
    </div>
</div>
<hr class="border-navy border-2 mb-6">
<div class="grid grid-cols-3 gap-6 mb-6 text-xs">
    <div>
        <div class="text-gray-400 uppercase text-[10px] tracking-wider mb-1">Client</div>
        <div class="font-bold text-navy"><?= h($q['client_name']) ?></div>
    </div>
    <div>
        <div class="text-gray-400 uppercase text-[10px] tracking-wider mb-1">Date</div>
        <div class="font-bold"><?= $q['quote_date'] ? date('d/m/Y',strtotime($q['quote_date'])) : '—' ?></div>
    </div>
    <div class="text-right">
        <div class="text-gray-400 uppercase text-[10px] tracking-wider mb-1">Montant TTC</div>
        <div class="font-black text-navy text-xl"><?= money($q['amount_ttc']) ?></div>
    </div>
</div>
<table class="w-full text-xs mb-6" style="border-collapse:collapse">
    <thead>
        <tr style="background:#1B2A4A;color:#fff;">
            <th class="px-3 py-2 text-left">Désignation</th>
            <th class="px-3 py-2 text-center w-16">Qté</th>
            <th class="px-3 py-2 text-right w-24">Prix unit.</th>
            <th class="px-3 py-2 text-right w-28">Montant</th>
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
            <td colspan="3" class="px-3 py-3 text-right font-black text-sm">TOTAL TTC</td>
            <td class="px-3 py-3 text-right font-black text-sm"><?= money($q['amount_ttc']) ?></td>
        </tr>
    </tfoot>
</table>
<?php if ($q['notes']): ?>
<div class="bg-gray-50 border border-gray-200 rounded-xl p-3 mb-4 text-xs text-gray-600"><strong>Notes :</strong> <?= h($q['notes']) ?></div>
<?php endif; ?>
<?php if ($cfg['quote_footer_text']??''): ?>
<div class="bg-gray-50 border border-gray-200 rounded-xl p-3 mb-4 text-xs text-gray-600"><?= h($cfg['quote_footer_text']) ?></div>
<?php endif; ?>
<div class="border-t border-gray-200 pt-3 text-center text-gray-400 text-[10px]">
    <?= h($cfg['owner_name']??'') ?> &nbsp;·&nbsp; ICE : <?= h($cfg['ice']??'') ?> &nbsp;·&nbsp; Auto-Entrepreneur exonéré de TVA
</div>
<script><?php if (isset($_GET['autoprint'])): ?>window.addEventListener('load',()=>window.print());<?php endif; ?></script>
</body></html>
<?php exit; endif; // end print ?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- ── Action bar ───────────────────────────────────────────── -->
<div class="flex flex-wrap items-center gap-2 mb-5">
    <a href="/quotes.php" class="btn-f flex items-center gap-1.5 px-3 py-2 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 bg-white dark:bg-gray-800 shadow-f">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Retour
    </a>
    <a href="/api/pdf.php?type=quote&id=<?= $id ?>" target="_blank"
        class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-white dark:bg-gray-800 border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 shadow-f">
        📥 Télécharger PDF
    </a>
    <button onclick="openEmailModal('quote',<?= $id ?>,'<?= addslashes(h($clientEmail)) ?>','<?= addslashes(h($q['client_name'])) ?>')"
        class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-white dark:bg-gray-800 border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-blue-lt hover:text-fluent-blue hover:border-fluent-blue shadow-f">
        📧 Envoyer par email
    </button>
    <a href="/quote-view.php?id=<?= $id ?>&print=1" target="_blank"
        class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-white dark:bg-gray-800 border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 shadow-f">
        🖨️ Imprimer
    </a>
    <a href="/quote-edit.php?id=<?= $id ?>"
        class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-white dark:bg-gray-800 border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 shadow-f">
        ✏️ Modifier
    </a>
    <?php if (!$q['converted_invoice_id'] && $q['status']!=='Refusé'): ?>
    <a href="/api/quotes.php?action=convert&id=<?= $id ?>&_csrf=<?= $csrf ?>"
        onclick="return confirm('Convertir ce devis en facture ?')"
        class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-fluent-blue text-white rounded-xl font-semibold shadow-f hover:bg-fluent-blue-dk ml-auto">
        ⚡ Convertir en facture
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <!-- Quote preview card -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-[#1B2A4A] to-[#0d1f38] px-6 py-5 text-white">
                <div class="flex items-start justify-between">
                    <div>
                        <?php if (getLogoPath() && logoExistsOnDisk(getLogoPath())): ?>
                        <img src="<?= h(getLogoPath() ?? "") ?>" alt="Logo" class="h-10 mb-2 object-contain">
                        <?php endif; ?>
                        <div class="text-lg font-bold"><?= h($cfg['owner_name']??'') ?></div>
                        <div class="text-xs text-white/60 mt-0.5"><?= h($cfg['address']??'') ?></div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-black tracking-wider opacity-80">DEVIS</div>
                        <div class="font-mono text-fluent-blue text-lg font-bold"><?= h($q['quote_number']) ?></div>
                        <?php
                        $statusCfg = [
                            'Brouillon' =>['bg-gray-500','Brouillon'],
                            'Envoyé'    =>['bg-blue-500','Envoyé'],
                            'Accepté'   =>['bg-green-500','✓ Accepté'],
                            'Refusé'    =>['bg-red-600','✕ Refusé'],
                            'Expiré'    =>['bg-orange-500','Expiré'],
                        ];
                        $displayStatus = $isExpired ? 'Expiré' : $q['status'];
                        [$sc,$sl] = $statusCfg[$displayStatus] ?? ['bg-gray-500',$displayStatus];
                        ?>
                        <span class="inline-block mt-1 px-3 py-0.5 rounded-full text-xs font-bold text-white <?= $sc ?>"><?= $sl ?></span>
                    </div>
                </div>
            </div>

            <!-- Meta -->
            <div class="grid grid-cols-3 divide-x divide-fluent-n5 dark:divide-white/10 border-b border-fluent-n5 dark:border-white/10">
                <?php foreach ([
                    ['Client', h($q['client_name'])],
                    ['Date', $q['quote_date'] ? date('d/m/Y',strtotime($q['quote_date'])) : '—'],
                    ['Montant', '<span class="font-bold text-fluent-blue">'.money($q['amount_ttc']).'</span>'],
                ] as [$label,$val]): ?>
                <div class="px-4 py-3">
                    <div class="text-[10px] text-fluent-n3 uppercase tracking-wider mb-0.5"><?= $label ?></div>
                    <div class="text-sm font-semibold text-fluent-neutral"><?= $val ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Validity banner -->
            <?php if ($daysLeft !== null): ?>
            <div class="px-5 py-2.5 <?= $daysLeft < 0 ? 'bg-fluent-red-lt dark:bg-red-900/20 text-fluent-red' : ($daysLeft<=7 ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-700' : 'bg-fluent-n7 dark:bg-white/5 text-fluent-n2') ?> text-xs font-medium border-b border-fluent-n5 dark:border-white/10">
                <?php if ($daysLeft < 0): ?>
                ⚠️ Ce devis a expiré il y a <?= abs($daysLeft) ?> jour(s)
                <?php elseif ($daysLeft === 0): ?>
                ⚡ Ce devis expire aujourd'hui !
                <?php else: ?>
                ✓ Valable <?= $daysLeft ?> jour(s) — jusqu'au <?= date('d/m/Y',strtotime($q['valid_until'])) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

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
                        <span class="font-medium text-fluent-neutral"><?= money($q['amount_ht']) ?></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-fluent-n4 italic">TVA (exonéré AE)</span>
                        <span class="text-fluent-n4 italic">Art. 91-I-B-1°</span>
                    </div>
                    <div class="flex justify-between items-center py-2.5 px-4 bg-[#1B2A4A] dark:bg-[#0d1f38] rounded-xl text-white">
                        <span class="font-bold text-sm">Total TTC</span>
                        <span class="font-black text-lg"><?= money($q['amount_ttc']) ?></span>
                    </div>
                </div>
            </div>

            <?php if ($q['notes']||($cfg['quote_footer_text']??'')): ?>
            <div class="px-5 pb-5 space-y-2">
                <?php if ($q['notes']): ?>
                <div class="p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl text-xs text-fluent-n2"><strong>Notes :</strong> <?= h($q['notes']) ?></div>
                <?php endif; ?>
                <?php if ($cfg['quote_footer_text']??''): ?>
                <div class="p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl text-xs text-fluent-n2"><?= h($cfg['quote_footer_text']) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right panel -->
    <div class="space-y-3">
        <!-- Status -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <div class="text-xs font-semibold text-fluent-n3 uppercase tracking-wider mb-3">Statut</div>
            <?php if ($q['converted_invoice_id']): ?>
            <div class="flex items-center gap-3 p-3 bg-fluent-green-lt dark:bg-green-900/20 rounded-xl">
                <div class="text-2xl">✅</div>
                <div>
                    <div class="text-sm font-semibold text-fluent-green">Converti en facture</div>
                    <a href="/invoice-view.php?id=<?= $q['converted_invoice_id'] ?>" class="text-xs text-fluent-blue hover:underline">
                        <?= h($q['linked_invoice']??'Voir la facture') ?> →
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ([
                    ['Brouillon','gray'],['Envoyé','blue'],['Accepté','green'],['Refusé','red']
                ] as [$st,$color]): ?>
                <form method="POST" action="/api/quotes.php">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="status" value="<?= $st ?>">
                    <button type="submit" class="btn-f w-full px-3 py-2 text-xs rounded-xl border text-left
                        <?= $q['status']===$st?'border-fluent-blue bg-fluent-blue-lt text-fluent-blue font-semibold':'border-fluent-n4 dark:border-white/20 text-fluent-n2 hover:bg-fluent-n6 dark:hover:bg-white/5' ?>">
                        <?= $q['status']===$st?'● ':'○ ' ?><?= $st ?>
                    </button>
                </form>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <div class="text-xs font-semibold text-fluent-n3 uppercase tracking-wider mb-3">Informations</div>
            <div class="space-y-2.5 text-xs">
                <?php foreach ([
                    ['Activité', $q['activity_label']??$q['activity']??$q['category']],
                    ['Émis le', $q['quote_date'] ? date('d/m/Y',strtotime($q['quote_date'])) : '—'],
                    ['Valide jusqu\'au', $q['valid_until'] ? date('d/m/Y',strtotime($q['valid_until'])) : '—'],
                ] as [$k,$v]): ?>
                <div class="flex justify-between">
                    <span class="text-fluent-n3"><?= $k ?></span>
                    <span class="text-fluent-neutral font-medium"><?= h($v) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Actions v2.1 -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <div class="text-xs font-semibold text-fluent-n3 uppercase tracking-wider mb-3">Actions</div>
            <div class="space-y-2">
                <a href="/api/pdf.php?type=quote&id=<?= $id ?>" target="_blank"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-blue-lt hover:text-fluent-blue hover:border-fluent-blue">
                    📥 Télécharger PDF
                </a>
                <button onclick="openEmailModal('quote',<?= $id ?>,'<?= addslashes(h($clientEmail)) ?>','<?= addslashes(h($q['client_name'])) ?>')"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-blue-lt hover:text-fluent-blue hover:border-fluent-blue text-left">
                    📧 Envoyer par email
                </button>
                <a href="/quote-edit.php?id=<?= $id ?>"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6">
                    ✏️ Modifier le devis
                </a>
                <?php if (!$q['converted_invoice_id']): ?>
                <a href="/api/quotes.php?action=convert&id=<?= $id ?>&_csrf=<?= $csrf ?>"
                    onclick="return confirm('Convertir en facture ?')"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold hover:bg-fluent-blue-dk shadow-f">
                    ⚡ Convertir en facture
                </a>
                <?php else: ?>
                <a href="/invoice-view.php?id=<?= $q['converted_invoice_id'] ?>"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm bg-fluent-green text-white rounded-xl font-semibold hover:bg-green-700 shadow-f">
                    ✅ Voir la facture liée
                </a>
                <?php endif; ?>
                <a href="/api/quotes.php?action=delete&id=<?= $id ?>&_csrf=<?= $csrf ?>"
                    onclick="return confirmDelete('Supprimer ce devis ?')"
                    class="btn-f flex items-center gap-2.5 w-full px-3 py-2.5 text-sm border border-red-200 dark:border-red-900/50 rounded-xl text-fluent-red hover:bg-fluent-red-lt">
                    🗑️ Supprimer
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
