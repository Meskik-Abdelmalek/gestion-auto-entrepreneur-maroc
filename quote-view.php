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

$isPrint = isset($_GET['print']);
$pageName = 'Devis ' . $q['quote_number'];

$daysLeft = $q['valid_until'] ? (int)ceil((strtotime($q['valid_until'])-time())/86400) : null;
$isExpired = $daysLeft !== null && $daysLeft < 0 && !in_array($q['status'],['Accepté','Refusé']);

if ($isPrint) {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Devis <?= h($q['quote_number']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{navy:'#1B2A4A',blue:'#0078d4'},fontFamily:{sans:['"Segoe UI"','Arial','sans-serif']}}}}</script>
<style>@media print{@page{size:A4;margin:15mm 18mm}body{print-color-adjust:exact;-webkit-print-color-adjust:exact}.no-print{display:none!important}}body{font-family:"Segoe UI",Arial,sans-serif}</style>
</head>
<body class="bg-white text-gray-800 text-sm p-8 max-w-3xl mx-auto">
<div class="no-print flex gap-3 mb-6">
    <button onclick="window.print()" class="px-5 py-2 bg-blue-600 text-white rounded-lg font-semibold text-sm hover:bg-blue-700">🖨️ Imprimer / PDF</button>
    <a href="/quote-view.php?id=<?= $id ?>" class="px-5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">← Retour</a>
</div>
<div class="flex justify-between items-start mb-8">
    <div>
        <div class="text-2xl font-bold text-navy mb-1"><?= h($cfg['owner_name']??'') ?></div>
        <div class="text-xs text-gray-500 space-y-0.5">
            <div><?= h($cfg['address']??'') ?></div>
            <div>ICE: <?= h($cfg['ice']??'') ?> · IF: <?= h($cfg['if_fiscal']??'') ?></div>
            <div>TP: <?= h($cfg['tp']??'') ?> · Tél: <?= h($cfg['cnss_phone']??'') ?></div>
            <?php if ($cfg['email']??''): ?><div><?= h($cfg['email']) ?></div><?php endif; ?>
        </div>
    </div>
    <div class="text-right">
        <div class="text-4xl font-bold text-blue-600 mb-2">DEVIS</div>
        <table class="text-xs ml-auto">
            <tr><td class="text-gray-500 pr-4">N° Devis</td><td class="font-semibold"><?= h($q['quote_number']) ?></td></tr>
            <tr><td class="text-gray-500 pr-4">Date</td><td><?= date('d/m/Y',strtotime($q['quote_date'])) ?></td></tr>
            <tr><td class="text-gray-500 pr-4">Valable jusqu'au</td><td class="font-semibold"><?= $q['valid_until']?date('d/m/Y',strtotime($q['valid_until'])):'—' ?></td></tr>
            <tr><td class="text-gray-500 pr-4">Statut</td><td class="font-semibold text-blue-600"><?= h($q['status']) ?></td></tr>
        </table>
    </div>
</div>
<div class="h-0.5 bg-navy mb-6"></div>
<div class="mb-8">
    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Devis établi pour</div>
    <div class="text-base font-bold text-navy"><?= h($q['client_name']) ?></div>
    <?php if ($q['activity']): ?><div class="text-xs text-gray-500"><?= h($q['activity']) ?></div><?php endif; ?>
    <div class="text-xs text-gray-500"><?= h($q['category']) ?></div>
</div>
<table class="w-full mb-6 text-xs">
    <thead>
        <tr class="bg-navy text-white">
            <th class="text-left px-3 py-2 rounded-tl-lg">#</th>
            <th class="text-left px-3 py-2">Description</th>
            <th class="text-center px-3 py-2">Qté</th>
            <th class="text-right px-3 py-2">Prix Unit.</th>
            <th class="text-right px-3 py-2 rounded-tr-lg">Montant HT</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($lines): foreach ($lines as $i=>$l): ?>
        <tr class="<?= $i%2===0?'bg-gray-50':'bg-white' ?>">
            <td class="px-3 py-2 text-gray-400"><?= $i+1 ?></td>
            <td class="px-3 py-2 font-medium"><?= h($l['description']) ?></td>
            <td class="px-3 py-2 text-center"><?= number_format($l['quantity'],2) ?></td>
            <td class="px-3 py-2 text-right"><?= money($l['unit_price']) ?></td>
            <td class="px-3 py-2 text-right font-semibold"><?= money($l['amount']) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr class="bg-gray-50"><td colspan="5" class="px-3 py-4 text-center text-gray-400">—</td></tr>
        <?php endif; ?>
    </tbody>
</table>
<div class="flex justify-end mb-8">
    <div class="w-64">
        <div class="flex justify-between py-1.5 text-xs border-b border-gray-200"><span class="text-gray-500">Total HT</span><span class="font-medium"><?= money($q['amount_ht']) ?></span></div>
        <div class="flex justify-between py-1.5 text-xs border-b border-gray-200"><span class="text-gray-500">TVA</span><span class="text-gray-400 italic">Exonéré (Auto-Entrepreneur)</span></div>
        <div class="flex justify-between py-2.5 text-sm font-bold bg-navy text-white px-3 rounded-lg mt-1"><span>Total TTC</span><span><?= money($q['amount_ttc']) ?></span></div>
    </div>
</div>
<?php if ($q['notes']): ?>
<div class="mb-6 p-3 bg-gray-50 rounded-lg">
    <div class="text-xs font-semibold text-gray-500 mb-1">Conditions & Remarques</div>
    <div class="text-xs text-gray-600"><?= h($q['notes']) ?></div>
</div>
<?php endif; ?>
<?php if ($cfg['quote_footer_text']??''): ?>
<div class="mb-6 p-3 bg-blue-50 rounded-lg border border-blue-100">
    <div class="text-xs text-blue-600"><?= h($cfg['quote_footer_text']) ?></div>
</div>
<?php endif; ?>
<div class="border-t border-gray-200 pt-4">
    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Validation</div>
    <div class="text-xs text-gray-600">Ce devis est valable jusqu'au <?= $q['valid_until']?date('d/m/Y',strtotime($q['valid_until'])):'—' ?>.</div>
    <div class="text-xs text-gray-400 mt-1">Auto-Entrepreneur exonéré de TVA — Art. 91-I-B-1° du CGI marocain</div>
    <div class="mt-4 grid grid-cols-2 gap-8">
        <div class="border border-gray-300 rounded-lg p-4 text-center">
            <div class="text-xs text-gray-500 mb-6">Signature & Cachet client</div>
            <div class="text-xs font-semibold text-gray-700"><?= h($q['client_name']) ?></div>
            <div class="text-[10px] text-gray-400">Bon pour accord — Date: ___/___/______</div>
        </div>
        <div class="border border-gray-300 rounded-lg p-4 text-center">
            <div class="text-xs text-gray-500 mb-6">Signature du prestataire</div>
            <div class="text-xs font-semibold text-gray-700"><?= h($cfg['owner_name']??'') ?></div>
            <div class="text-[10px] text-gray-400">ICE: <?= h($cfg['ice']??'') ?></div>
        </div>
    </div>
</div>
<div class="h-0.5 bg-gray-200 mt-6 mb-3"></div>
<div class="text-center text-[10px] text-gray-400"><?= h($cfg['owner_name']??'') ?> · ICE: <?= h($cfg['ice']??'') ?> · Merci pour votre confiance</div>
</body>
</html>
<?php exit; }

require_once __DIR__ . '/includes/header.php';
?>

<!-- Action bar -->
<div class="flex flex-wrap items-center gap-2 mb-5">
    <a href="/quotes.php" class="btn-f flex items-center gap-1.5 px-3 py-2 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6 dark:hover:bg-white/10 bg-white dark:bg-gray-800">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>Retour
    </a>
    <a href="/quote-view.php?id=<?= $id ?>&print=1" target="_blank" class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 text-fluent-n2 hover:bg-fluent-n6 dark:hover:bg-white/10 shadow-f">🖨️ Imprimer / PDF</a>
    <a href="/quote-edit.php?id=<?= $id ?>" class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 text-fluent-n2 hover:bg-fluent-n6 dark:hover:bg-white/10 shadow-f">✏️ Modifier</a>
    <?php if (!$q['converted_invoice_id']): ?>
    <a href="/api/quotes.php?action=convert&id=<?= $id ?>&_csrf=<?= $csrf ?>"
        onclick="return confirm('Convertir ce devis en facture ?')"
        class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-fluent-green text-white rounded-xl font-semibold shadow-f hover:bg-green-700 ml-auto">
        ⚡ Convertir en Facture
    </a>
    <?php else: ?>
    <a href="/invoice-view.php?id=<?= (int)$q['converted_invoice_id'] ?>" class="btn-f flex items-center gap-1.5 px-4 py-2 text-sm bg-fluent-green-lt dark:bg-green-900/30 text-fluent-green rounded-xl font-semibold ml-auto">
        ✓ Facture: <?= h($q['linked_invoice']) ?>
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Preview -->
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
        <div class="bg-gradient-to-r from-slate-700 to-slate-600 px-6 py-5 text-white">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-xl font-bold"><?= h($cfg['owner_name']??'Mon Entreprise') ?></div>
                    <div class="text-xs text-slate-300 mt-1"><?= h($cfg['address']??'') ?></div>
                    <div class="text-xs text-slate-400 mt-0.5">ICE: <?= h($cfg['ice']??'') ?></div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-white/90">DEVIS</div>
                    <div class="text-sm font-mono font-semibold text-blue-300 mt-1"><?= h($q['quote_number']) ?></div>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 border-b border-fluent-n5 dark:border-white/10 grid grid-cols-2 sm:grid-cols-4 gap-4">
            <?php foreach ([['Date',date('d/m/Y',strtotime($q['quote_date']))],['Validité',$q['valid_until']?date('d/m/Y',strtotime($q['valid_until'])):'—'],['Client',$q['client_name']],['Catégorie',$q['category']]] as [$l,$v]): ?>
            <div><div class="text-xs text-fluent-n3 font-medium"><?= $l ?></div><div class="text-sm font-semibold text-fluent-neutral mt-0.5"><?= h($v) ?></div></div>
            <?php endforeach; ?>
        </div>
        <?php if ($q['activity']): ?>
        <div class="px-6 py-2 bg-fluent-blue-lt dark:bg-fluent-blue/10 border-b border-fluent-n5 dark:border-white/10">
            <span class="text-xs text-fluent-blue font-medium">🎯 Activité: <?= h($q['activity']) ?></span>
        </div>
        <?php endif; ?>
        <div class="px-6 py-4">
            <?php if ($lines): ?>
            <table class="w-full text-sm mb-4">
                <thead><tr class="border-b border-fluent-n5 dark:border-white/10">
                    <th class="text-left py-2 text-xs font-semibold text-fluent-n3">#</th>
                    <th class="text-left py-2 text-xs font-semibold text-fluent-n3">DESCRIPTION</th>
                    <th class="text-center py-2 text-xs font-semibold text-fluent-n3">QTÉ</th>
                    <th class="text-right py-2 text-xs font-semibold text-fluent-n3">PRIX</th>
                    <th class="text-right py-2 text-xs font-semibold text-fluent-n3">MONTANT</th>
                </tr></thead>
                <tbody class="divide-y divide-fluent-n6 dark:divide-white/5">
                    <?php foreach ($lines as $i=>$l): ?>
                    <tr>
                        <td class="py-3 text-xs text-fluent-n3"><?= $i+1 ?></td>
                        <td class="py-3 font-medium text-fluent-neutral"><?= h($l['description']) ?></td>
                        <td class="py-3 text-center text-fluent-n2"><?= number_format((float)$l['quantity'],2) ?></td>
                        <td class="py-3 text-right text-fluent-n2"><?= money($l['unit_price']) ?></td>
                        <td class="py-3 text-right font-semibold text-fluent-neutral"><?= money($l['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <div class="flex justify-end">
                <div class="w-56 space-y-1">
                    <div class="flex justify-between text-xs py-1 border-b border-fluent-n5 dark:border-white/10"><span class="text-fluent-n3">Sous-total HT</span><span class="font-medium"><?= money($q['amount_ht']) ?></span></div>
                    <div class="flex justify-between text-xs py-1 border-b border-fluent-n5 dark:border-white/10"><span class="text-fluent-n3">TVA</span><span class="text-fluent-n3 italic text-[10px]">Exonéré AE</span></div>
                    <div class="flex justify-between py-2.5 px-3 bg-slate-800 text-white rounded-xl mt-2"><span class="font-semibold text-sm">Total TTC</span><span class="font-bold text-sm"><?= money($q['amount_ttc']) ?></span></div>
                </div>
            </div>
        </div>
        <?php if ($q['notes']): ?>
        <div class="px-6 py-3 bg-fluent-n7 dark:bg-white/5 border-t border-fluent-n5 dark:border-white/10">
            <div class="text-xs font-medium text-fluent-n3 mb-1">Conditions & Notes</div>
            <div class="text-sm text-fluent-n2"><?= h($q['notes']) ?></div>
        </div>
        <?php endif; ?>
        <div class="px-6 py-3 border-t border-fluent-n5 dark:border-white/10 text-[10px] text-fluent-n3 text-center">
            Auto-Entrepreneur exonéré de TVA · Art. 91-I-B-1° du CGI marocain · Devis valable jusqu'au <?= $q['valid_until']?date('d/m/Y',strtotime($q['valid_until'])):'—' ?>
        </div>
    </div>

    <!-- Right panel -->
    <div class="space-y-3">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <div class="text-xs font-semibold text-fluent-n3 uppercase tracking-wider mb-3">Statut</div>
            <div class="flex items-center gap-3">
                <div class="text-3xl">
                    <?= match($q['status']) { 'Accepté'=>'✅','Refusé'=>'❌','Envoyé'=>'📤','Expiré'=>'⌛',default=>'📋' } ?>
                </div>
                <div>
                    <div class="font-bold text-fluent-neutral"><?= h($q['status']) ?></div>
                    <?php if ($daysLeft !== null && !in_array($q['status'],['Accepté','Refusé'])): ?>
                    <div class="text-xs <?= $isExpired?'text-fluent-red':($daysLeft<=7?'text-amber-500':'text-fluent-n3') ?> mt-0.5">
                        <?= $isExpired ? '⚠️ Expiré' : "⏱️ Valable $daysLeft jour(s)" ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Quick status change -->
            <form method="POST" class="mt-3">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <input type="hidden" name="status_change" value="1">
                <input type="hidden" name="id" value="<?= $id ?>">
                <select name="new_status" onchange="this.form.submit()"
                    class="w-full px-3 py-2 text-xs border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                    <?php foreach (['Brouillon','Envoyé','Accepté','Refusé','Expiré'] as $st): ?>
                    <option value="<?= $st ?>" <?= $q['status']===$st?'selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <div class="text-xs font-semibold text-fluent-n3 uppercase tracking-wider mb-3">Résumé</div>
            <div class="space-y-2 text-sm">
                <?php foreach ([['Montant HT',money($q['amount_ht'])],['Total TTC',money($q['amount_ttc']),true],['Date',date('d/m/Y',strtotime($q['quote_date']))],['Validité',$q['valid_until']?date('d/m/Y',strtotime($q['valid_until'])):'—']] as $item): ?>
                <div class="flex justify-between items-center <?= ($item[2]??false)?'border-t border-fluent-n5 dark:border-white/10 pt-2 font-semibold':'' ?>">
                    <span class="text-fluent-n3"><?= $item[0] ?></span><span><?= $item[1] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <div class="text-xs font-semibold text-fluent-n3 uppercase tracking-wider mb-3">Actions</div>
            <div class="space-y-2">
                <?php if (!$q['converted_invoice_id']): ?>
                <a href="/api/quotes.php?action=convert&id=<?= $id ?>&_csrf=<?= $csrf ?>" onclick="return confirm('Convertir en facture ?')"
                    class="btn-f flex items-center gap-2 w-full px-3 py-2.5 text-sm bg-fluent-green text-white rounded-xl font-semibold hover:bg-green-700">⚡ Convertir en Facture</a>
                <?php else: ?>
                <a href="/invoice-view.php?id=<?= (int)$q['converted_invoice_id'] ?>" class="btn-f flex items-center gap-2 w-full px-3 py-2.5 text-sm bg-fluent-green-lt dark:bg-green-900/30 text-fluent-green rounded-xl font-semibold">✓ Voir la Facture Liée</a>
                <?php endif; ?>
                <a href="/quote-view.php?id=<?= $id ?>&print=1" target="_blank" class="btn-f flex items-center gap-2 w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6 dark:hover:bg-white/10">🖨️ Imprimer / PDF</a>
                <a href="/quote-edit.php?id=<?= $id ?>" class="btn-f flex items-center gap-2 w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6 dark:hover:bg-white/10">✏️ Modifier le Devis</a>
                <a href="?duplicate=<?= $id ?>&_csrf=<?= $csrf ?>" class="btn-f flex items-center gap-2 w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6 dark:hover:bg-white/10">📋 Dupliquer</a>
                <a href="/api/quotes.php?action=delete&id=<?= $id ?>&_csrf=<?= $csrf ?>"
                    onclick="return confirmDelete('Supprimer définitivement ce devis ?')"
                    class="btn-f flex items-center gap-2 w-full px-3 py-2.5 text-sm border border-red-200 dark:border-red-800 rounded-xl text-fluent-red hover:bg-fluent-red-lt dark:hover:bg-red-900/30">🗑️ Supprimer</a>
            </div>
        </div>
    </div>
</div>

<script>
// Quick status change from quotes list
document.querySelector('[name="status_change"]')?.closest('form')?.addEventListener('submit', function(e) {
    const sel = this.querySelector('[name="new_status"]');
    if (sel.value === 'Accepté' && !confirm('Marquer ce devis comme Accepté ? Vous pourrez ensuite le convertir en facture.')) e.preventDefault();
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
