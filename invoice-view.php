<?php
require_once __DIR__ . '/includes/functions.php';
$db  = getDB();
$cfg = getConfig();
$id  = (int)($_GET['id'] ?? 0);

if (!$id) { header('Location: /invoices.php'); exit; }

$inv = $db->prepare("SELECT * FROM ae_invoices WHERE id=?")->execute([$id]) && false;
$stmt = $db->prepare("SELECT * FROM ae_invoices WHERE id=?");
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { header('Location: /invoices.php'); exit; }

$lines = $db->prepare("SELECT * FROM ae_invoice_lines WHERE invoice_id=? ORDER BY sort_order");
$lines->execute([$id]);
$lines = $lines->fetchAll();

$isPrint = isset($_GET['print']);
$pageName = 'Facture ' . $inv['invoice_number'];

// Handle quick mark paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    verifyCsrf();
    $date = clean($_POST['payment_date'] ?? date('Y-m-d'));
    $mode = clean($_POST['payment_method'] ?? '');
    $db->prepare("UPDATE ae_invoices SET status='Payé', payment_date=?, payment_method=? WHERE id=?")
       ->execute([$date, $mode, $id]);
    $db->prepare("UPDATE ae_reminders SET status='Payé' WHERE invoice_id=?")->execute([$id]);
    flash('message', 'Facture marquée comme payée !');
    header("Location: /invoice-view.php?id=$id"); exit;
}

if ($isPrint) {
// ─── PRINT LAYOUT ──────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Facture <?= h($inv['invoice_number']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{navy:'#1B2A4A',blue:'#0078d4'}},fontFamily:{sans:['"Segoe UI"','Arial','sans-serif']}}}}</script>
<style>
  @media print {
    @page { size: A4; margin: 15mm 18mm; }
    body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .no-print { display: none !important; }
    .page-break { page-break-before: always; }
  }
  body { font-family: "Segoe UI", Arial, sans-serif; }
</style>
</head>
<body class="bg-white text-gray-800 text-sm p-8 max-w-3xl mx-auto">

<div class="no-print flex gap-3 mb-6">
  <button onclick="window.print()" class="px-5 py-2 bg-blue text-white rounded-lg font-semibold text-sm bg-blue-600 hover:bg-blue-700 print:hidden">🖨️ Imprimer / PDF</button>
  <a href="/invoice-view.php?id=<?= $id ?>" class="px-5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">← Retour</a>
</div>

<!-- Header -->
<div class="flex justify-between items-start mb-8">
  <div>
    <div class="text-2xl font-bold text-navy mb-1"><?= h($cfg['owner_name'] ?? '') ?></div>
    <div class="text-xs text-gray-500 space-y-0.5">
      <div><?= h($cfg['address'] ?? '') ?></div>
      <div>ICE: <?= h($cfg['ice'] ?? '') ?> &nbsp;|&nbsp; IF: <?= h($cfg['if_fiscal'] ?? '') ?></div>
      <div>TP: <?= h($cfg['tp'] ?? '') ?> &nbsp;|&nbsp; Tél: <?= h($cfg['cnss_phone'] ?? '') ?></div>
      <?php if ($cfg['email']): ?><div><?= h($cfg['email']) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="text-right">
    <div class="text-4xl font-bold text-blue-600 mb-2">FACTURE</div>
    <table class="text-xs ml-auto">
      <tr><td class="text-gray-500 pr-4">N° Facture</td><td class="font-semibold"><?= h($inv['invoice_number']) ?></td></tr>
      <tr><td class="text-gray-500 pr-4">Date</td><td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td></tr>
      <tr><td class="text-gray-500 pr-4">Échéance</td><td><?= $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '30 jours' ?></td></tr>
      <tr><td class="text-gray-500 pr-4">Statut</td>
        <td class="font-semibold <?= $inv['status']==='Payé' ? 'text-green-600' : 'text-amber-600' ?>"><?= h($inv['status']) ?></td>
      </tr>
    </table>
  </div>
</div>

<div class="h-0.5 bg-navy mb-6"></div>

<!-- Bill to -->
<div class="mb-8">
  <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Facturé à</div>
  <div class="text-base font-bold text-navy"><?= h($inv['client_name']) ?></div>
  <div class="text-xs text-gray-500"><?= h($inv['category']) ?></div>
</div>

<!-- Lines table -->
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
    <?php if ($lines): foreach ($lines as $i => $l): ?>
    <tr class="<?= $i%2===0 ? 'bg-gray-50' : 'bg-white' ?>">
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

<!-- Totals -->
<div class="flex justify-end mb-8">
  <div class="w-64">
    <div class="flex justify-between py-1.5 text-xs border-b border-gray-200">
      <span class="text-gray-500">Total HT</span>
      <span class="font-medium"><?= money($inv['amount_ht']) ?></span>
    </div>
    <div class="flex justify-between py-1.5 text-xs border-b border-gray-200">
      <span class="text-gray-500">TVA</span>
      <span class="text-gray-400 italic">Exonéré (Auto-Entrepreneur)</span>
    </div>
    <div class="flex justify-between py-2.5 text-sm font-bold bg-navy text-white px-3 rounded-lg mt-1">
      <span>Total TTC</span>
      <span><?= money($inv['amount_ttc']) ?></span>
    </div>
    <?php $ir = $inv['amount_ttc'] * ($cfg['ir_rate_services'] ?? 0.01); ?>
    <div class="text-[10px] text-gray-400 mt-1.5 text-right">
      IR retenu à la source: <?= money($ir) ?> (<?= round(($cfg['ir_rate_services']??0.01)*100,1) ?>%)
    </div>
  </div>
</div>

<?php if ($inv['notes']): ?>
<div class="mb-6 p-3 bg-gray-50 rounded-lg">
  <div class="text-xs font-semibold text-gray-500 mb-1">Notes</div>
  <div class="text-xs text-gray-600"><?= h($inv['notes']) ?></div>
</div>
<?php endif; ?>

<!-- Payment info -->
<div class="border-t border-gray-200 pt-4">
  <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Modalités de paiement</div>
  <div class="text-xs text-gray-600 space-y-0.5">
    <?php if ($cfg['bank_rib']): ?><div>RIB: <?= h($cfg['bank_rib']) ?></div><?php endif; ?>
    <div>Réf. virement: <?= h($inv['invoice_number']) ?></div>
    <div class="text-gray-400 mt-1">Auto-Entrepreneur exonéré de TVA — Art. 91-I-B-1° du CGI marocain</div>
  </div>
</div>

<div class="h-0.5 bg-gray-200 mt-6 mb-3"></div>
<div class="text-center text-[10px] text-gray-400">
  <?= h($cfg['owner_name'] ?? '') ?> &nbsp;·&nbsp; ICE: <?= h($cfg['ice'] ?? '') ?> &nbsp;·&nbsp; Merci pour votre confiance
</div>

<script>
  window.addEventListener('load', () => {
    const noPrint = document.querySelector('.no-print');
    if (!noPrint) return;
    // Auto-open print dialog if ?autoprint
    <?php if (isset($_GET['autoprint'])): ?>window.print();<?php endif; ?>
  });
</script>
</body>
</html>
<?php exit; } // end print

// ─── NORMAL VIEW ───────────────────────────────────────────────────────────
require_once __DIR__ . '/includes/header.php';
$irRate = (float)($cfg['ir_rate_services'] ?? 0.01);
if ($inv['category'] === 'Commerce') $irRate = (float)($cfg['ir_rate_commerce'] ?? 0.005);
$irEstimate = $inv['amount_ttc'] * $irRate;
?>

<!-- Action bar -->
<div class="flex flex-wrap items-center gap-2 mb-5">
    <a href="/invoices.php" class="fluent-btn flex items-center gap-1.5 px-3 py-2 text-sm border border-fluent-neutral-4 rounded-xl text-fluent-neutral-2 hover:bg-fluent-neutral-6 bg-white">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Retour
    </a>
    <a href="/invoice-view.php?id=<?= $id ?>&print=1" target="_blank"
        class="fluent-btn flex items-center gap-1.5 px-4 py-2 text-sm bg-white border border-fluent-neutral-4 rounded-xl text-fluent-neutral-2 hover:bg-fluent-neutral-6 shadow-fluent">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimer / PDF
    </a>
    <a href="/invoice-view.php?id=<?= $id ?>&print=1&autoprint=1" target="_blank"
        class="fluent-btn flex items-center gap-1.5 px-4 py-2 text-sm bg-white border border-fluent-neutral-4 rounded-xl text-fluent-neutral-2 hover:bg-fluent-neutral-6 shadow-fluent">
        📥 Télécharger PDF
    </a>
    <a href="/invoice-edit.php?id=<?= $id ?>"
        class="fluent-btn flex items-center gap-1.5 px-4 py-2 text-sm bg-white border border-fluent-neutral-4 rounded-xl text-fluent-neutral-2 hover:bg-fluent-neutral-6 shadow-fluent">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Modifier
    </a>
    <?php if ($inv['status'] !== 'Payé'): ?>
    <button onclick="document.getElementById('pay-modal').classList.remove('hidden')"
        class="fluent-btn flex items-center gap-1.5 px-4 py-2 text-sm bg-fluent-green text-white rounded-xl font-semibold shadow-fluent hover:bg-green-700 ml-auto">
        ✓ Marquer Payée
    </button>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Invoice preview card -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-fluent overflow-hidden">
            <!-- Invoice header -->
            <div class="bg-gradient-to-r from-slate-800 to-slate-700 px-6 py-5 text-white">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-xl font-bold"><?= h($cfg['owner_name'] ?? 'Mon Entreprise') ?></div>
                        <div class="text-xs text-slate-300 mt-1"><?= h($cfg['address'] ?? '') ?></div>
                        <div class="text-xs text-slate-400 mt-0.5">ICE: <?= h($cfg['ice'] ?? '') ?> · IF: <?= h($cfg['if_fiscal'] ?? '') ?></div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-white/90">FACTURE</div>
                        <div class="text-sm font-mono font-semibold text-blue-300 mt-1"><?= h($inv['invoice_number']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Invoice meta -->
            <div class="px-6 py-4 border-b border-fluent-neutral-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
                <?php
                $meta = [
                    ['Date', date('d/m/Y', strtotime($inv['invoice_date']))],
                    ['Échéance', $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '30 jours net'],
                    ['Client', $inv['client_name']],
                    ['Catégorie', $inv['category']],
                ];
                foreach ($meta as [$label,$val]): ?>
                <div>
                    <div class="text-xs text-fluent-neutral-3 font-medium"><?= $label ?></div>
                    <div class="text-sm font-semibold text-fluent-neutral mt-0.5"><?= h($val) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Lines -->
            <div class="px-6 py-4">
                <?php if ($lines): ?>
                <table class="w-full text-sm mb-4">
                    <thead>
                        <tr class="border-b border-fluent-neutral-5">
                            <th class="text-left py-2 text-xs font-semibold text-fluent-neutral-3">#</th>
                            <th class="text-left py-2 text-xs font-semibold text-fluent-neutral-3">DESCRIPTION</th>
                            <th class="text-center py-2 text-xs font-semibold text-fluent-neutral-3">QTÉ</th>
                            <th class="text-right py-2 text-xs font-semibold text-fluent-neutral-3">PRIX UNIT.</th>
                            <th class="text-right py-2 text-xs font-semibold text-fluent-neutral-3">MONTANT</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-fluent-neutral-6">
                        <?php foreach ($lines as $i => $l): ?>
                        <tr>
                            <td class="py-3 text-xs text-fluent-neutral-3"><?= $i+1 ?></td>
                            <td class="py-3 font-medium text-fluent-neutral"><?= h($l['description']) ?></td>
                            <td class="py-3 text-center text-fluent-neutral-2"><?= number_format((float)$l['quantity'], 2) ?></td>
                            <td class="py-3 text-right text-fluent-neutral-2"><?= money($l['unit_price']) ?></td>
                            <td class="py-3 text-right font-semibold text-fluent-neutral"><?= money($l['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="py-6 text-center text-fluent-neutral-3 text-sm">Aucune ligne de prestation</div>
                <?php endif; ?>

                <!-- Totals -->
                <div class="flex justify-end">
                    <div class="w-56 space-y-1">
                        <div class="flex justify-between text-xs py-1 border-b border-fluent-neutral-5">
                            <span class="text-fluent-neutral-3">Sous-total HT</span>
                            <span class="font-medium"><?= money($inv['amount_ht']) ?></span>
                        </div>
                        <div class="flex justify-between text-xs py-1 border-b border-fluent-neutral-5">
                            <span class="text-fluent-neutral-3">TVA</span>
                            <span class="text-fluent-neutral-3 italic text-[10px]">Exonéré</span>
                        </div>
                        <div class="flex justify-between py-2 px-3 bg-slate-800 text-white rounded-xl mt-2">
                            <span class="font-semibold text-sm">Total TTC</span>
                            <span class="font-bold text-sm"><?= money($inv['amount_ttc']) ?></span>
                        </div>
                        <div class="text-[10px] text-fluent-neutral-3 text-right pt-1">
                            IR estimé: <?= money($irEstimate) ?> (<?= round($irRate*100,1) ?>%)
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($inv['notes']): ?>
            <div class="px-6 py-3 bg-fluent-neutral-7 border-t border-fluent-neutral-5">
                <div class="text-xs font-medium text-fluent-neutral-3 mb-1">Notes</div>
                <div class="text-sm text-fluent-neutral-2"><?= h($inv['notes']) ?></div>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="px-6 py-3 border-t border-fluent-neutral-5 text-[10px] text-fluent-neutral-3 text-center">
                Auto-Entrepreneur — Exonéré de TVA · Art. 91-I-B-1° du CGI marocain
            </div>
        </div>
    </div>

    <!-- Right panel: status + actions -->
    <div class="space-y-3">
        <!-- Status card -->
        <div class="bg-white rounded-2xl shadow-fluent p-5">
            <div class="text-xs font-semibold text-fluent-neutral-3 uppercase tracking-wider mb-3">Statut du paiement</div>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl
                    <?= $inv['status']==='Payé' ? 'bg-fluent-green-lt' : ($inv['status']==='Annulé' ? 'bg-fluent-red-lt' : 'bg-amber-50') ?>">
                    <?= $inv['status']==='Payé' ? '✅' : ($inv['status']==='Annulé' ? '❌' : '⏳') ?>
                </div>
                <div>
                    <div class="font-semibold text-fluent-neutral"><?= h($inv['status']) ?></div>
                    <?php if ($inv['payment_date']): ?>
                    <div class="text-xs text-fluent-neutral-3 mt-0.5">Le <?= date('d/m/Y', strtotime($inv['payment_date'])) ?></div>
                    <?php endif; ?>
                    <?php if ($inv['payment_method']): ?>
                    <div class="text-xs text-fluent-blue font-medium"><?= h($inv['payment_method']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($inv['status'] === 'En attente'):
                $days = (int)(date_diff(date_create($inv['invoice_date']), date_create())->days);
                $urgent = $days > 30;
            ?>
            <div class="text-xs <?= $urgent ? 'text-fluent-red bg-fluent-red-lt' : 'text-amber-600 bg-amber-50' ?> px-3 py-2 rounded-lg font-medium">
                <?= $urgent ? '⚠️' : '⏱️' ?> <?= $days ?> jours depuis l'émission
            </div>
            <?php endif; ?>
        </div>

        <!-- Invoice summary -->
        <div class="bg-white rounded-2xl shadow-fluent p-5">
            <div class="text-xs font-semibold text-fluent-neutral-3 uppercase tracking-wider mb-3">Résumé</div>
            <div class="space-y-2 text-sm">
                <?php $items = [
                    ['Montant HT', money($inv['amount_ht'])],
                    ['Montant TTC', money($inv['amount_ttc']), true],
                    ['IR estimé ('.round($irRate*100,1).'%)', money($irEstimate)],
                    ['Trimestre', $inv['quarter']],
                    ['Exercice', $inv['fiscal_year']],
                ]; foreach ($items as $item): ?>
                <div class="flex justify-between items-center <?= ($item[2]??false) ? 'border-t border-fluent-neutral-5 pt-2 font-semibold' : '' ?>">
                    <span class="text-fluent-neutral-3"><?= $item[0] ?></span>
                    <span class="text-fluent-neutral"><?= $item[1] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="bg-white rounded-2xl shadow-fluent p-5">
            <div class="text-xs font-semibold text-fluent-neutral-3 uppercase tracking-wider mb-3">Actions</div>
            <div class="space-y-2">
                <a href="/invoice-view.php?id=<?= $id ?>&print=1" target="_blank"
                    class="fluent-btn flex items-center gap-2 w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl text-fluent-neutral-2 hover:bg-fluent-neutral-6">
                    🖨️ Imprimer / Exporter PDF
                </a>
                <a href="/invoice-edit.php?id=<?= $id ?>"
                    class="fluent-btn flex items-center gap-2 w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl text-fluent-neutral-2 hover:bg-fluent-neutral-6">
                    ✏️ Modifier la facture
                </a>
                <a href="/invoices.php?status=En+attente"
                    class="fluent-btn flex items-center gap-2 w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl text-fluent-neutral-2 hover:bg-fluent-neutral-6">
                    📬 Voir les relances
                </a>
                <a href="/api/invoices.php?action=delete&id=<?= $id ?>&_csrf=<?= $csrf ?>"
                    onclick="return confirmDelete('Supprimer définitivement cette facture ?')"
                    class="fluent-btn flex items-center gap-2 w-full px-3 py-2.5 text-sm border border-red-200 rounded-xl text-fluent-red hover:bg-fluent-red-lt">
                    🗑️ Supprimer la facture
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Mark Paid Modal -->
<?php if ($inv['status'] !== 'Payé'): ?>
<div id="pay-modal" class="fixed inset-0 z-50 hidden flex items-end sm:items-center justify-center p-4 bg-black/40">
    <div class="bg-white rounded-2xl shadow-fluent-lg w-full max-w-md">
        <div class="px-5 py-4 border-b border-fluent-neutral-5 flex items-center justify-between">
            <h3 class="font-semibold text-fluent-neutral text-sm">Enregistrer le paiement</h3>
            <button onclick="document.getElementById('pay-modal').classList.add('hidden')" class="text-fluent-neutral-3">✕</button>
        </div>
        <form method="POST" class="px-5 py-4 space-y-3">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="mark_paid">
            <div class="bg-fluent-blue-lt rounded-xl px-4 py-3 text-sm font-semibold text-fluent-blue text-center mb-2">
                Montant: <?= money($inv['amount_ttc']) ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-neutral-2 mb-1">Date de paiement</label>
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required
                    class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-neutral-2 mb-1">Mode de paiement</label>
                <select name="payment_method" class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white">
                    <option value="">— Sélectionner —</option>
                    <?php foreach (['Virement','Chèque','Espèces','CB','PayPal','Mobile Money'] as $m): ?>
                    <option><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="button" onclick="document.getElementById('pay-modal').classList.add('hidden')"
                    class="flex-1 py-2.5 border border-fluent-neutral-4 rounded-xl text-sm text-fluent-neutral-2 hover:bg-fluent-neutral-6">Annuler</button>
                <button type="submit" class="flex-1 py-2.5 bg-fluent-green text-white rounded-xl text-sm font-semibold hover:bg-green-700">
                    ✓ Confirmer Paiement
                </button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('pay-modal').addEventListener('click', function(e){
    if(e.target===this) this.classList.add('hidden');
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
