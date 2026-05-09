<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Rapprochement Bancaire';
$db = getDB(); $cfg = getConfig();
$fy = (int)($_GET['year'] ?? $cfg['fiscal_year'] ?? date('Y'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'add';

    if ($action === 'delete') {
        $db->prepare("DELETE FROM ae_bank_transactions WHERE id=?")->execute([(int)$_POST['id']]);
        flash('message','Transaction supprimée.','error');
        header("Location: /bank.php?year=$fy"); exit;
    }
    if ($action === 'reconcile') {
        $tid = (int)$_POST['tid']; $iid = (int)$_POST['iid'] ?: null;
        $db->prepare("UPDATE ae_bank_transactions SET invoice_id=?,reconciled=? WHERE id=?")->execute([$iid,$iid?1:0,$tid]);
        flash('message',$iid?'Transaction rapprochée !':'Rapprochement annulé.');
        header("Location: /bank.php?year=$fy"); exit;
    }
    // add
    $date  = clean($_POST['date']   ?? date('Y-m-d'));
    $desc  = clean($_POST['desc']   ?? '');
    $cr    = (float)($_POST['credit'] ?? 0);
    $db_   = (float)($_POST['debit']  ?? 0);
    $notes = clean($_POST['notes']  ?? '');
    if ($desc || $cr || $db_) {
        $db->prepare("INSERT INTO ae_bank_transactions (transaction_date,description,credit,debit,fiscal_year,notes) VALUES (?,?,?,?,?,?)")
           ->execute([$date,$desc,$cr,$db_,$fy,$notes]);
        flash('message','Transaction ajoutée !');
    }
    header("Location: /bank.php?year=$fy"); exit;
}

// Fetch transactions with running balance
$txSt = $db->prepare("SELECT t.*,i.invoice_number,i.client_name,i.amount_ttc AS inv_amt
    FROM ae_bank_transactions t
    LEFT JOIN ae_invoices i ON i.id=t.invoice_id
    WHERE t.fiscal_year=? ORDER BY t.transaction_date ASC, t.id ASC");
$txSt->execute([$fy]);
$txAll = $txSt->fetchAll();

// Build running balance
$running = 0;
foreach ($txAll as &$tx) { $running += $tx['credit'] - $tx['debit']; $tx['balance'] = $running; }
unset($tx);
$txAll = array_reverse($txAll); // newest first for display

$totalCredit = array_sum(array_column($txAll,'credit'));
$totalDebit  = array_sum(array_column($txAll,'debit'));
$balance     = $totalCredit - $totalDebit;
$reconciled  = count(array_filter($txAll, fn($t)=>$t['reconciled']));
$unreconc    = count($txAll) - $reconciled;

// Ledger total for comparison
$ledSt=$db->prepare("SELECT COALESCE(SUM(amount_ttc),0) FROM ae_invoices WHERE status='Payé' AND fiscal_year=?");
$ledSt->execute([$fy]); $ledger=(float)$ledSt->fetchColumn();
$gap = $totalCredit - $ledger;

// Pending invoices for dropdown
$pendSt=$db->prepare("SELECT id,invoice_number,client_name,amount_ttc FROM ae_invoices WHERE status='En attente' AND fiscal_year=? ORDER BY invoice_date DESC");
$pendSt->execute([$fy]); $pending=$pendSt->fetchAll();

// All invoices for reconcile modal
$allInvSt=$db->prepare("SELECT id,invoice_number,client_name,amount_ttc FROM ae_invoices WHERE fiscal_year=? ORDER BY invoice_date DESC");
$allInvSt->execute([$fy]); $allInv=$allInvSt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-xl font-bold text-fluent-green"><?= money($totalCredit) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Total Crédits</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-xl font-bold text-fluent-red"><?= money($totalDebit) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Total Débits</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-xl font-bold <?= $balance>=0?'text-fluent-neutral':'text-fluent-red' ?>"><?= money($balance) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Solde Actuel</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-xl font-bold <?= abs($gap)<1?'text-fluent-green':'text-amber-600' ?>"><?= money($gap) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Écart vs LEDGER</div>
        <div class="text-[10px] text-fluent-n3">LEDGER: <?= money($ledger) ?></div>
    </div>
</div>

<!-- Reconciliation progress -->
<?php if (count($txAll)): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f px-5 py-4 mb-5">
    <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-fluent-n2">Avancement Rapprochement</span>
        <span class="text-xs text-fluent-n3"><?= $reconciled ?>/<?= count($txAll) ?> transactions</span>
    </div>
    <?php $rPct = count($txAll)>0?round($reconciled/count($txAll)*100):0; ?>
    <div class="h-2 bg-fluent-n5 dark:bg-white/10 rounded-full overflow-hidden">
        <div class="h-full bg-fluent-green rounded-full transition-all duration-700" style="width:<?= $rPct ?>%"></div>
    </div>
    <div class="flex items-center justify-between mt-1.5 text-xs">
        <span class="text-fluent-green font-medium">✓ <?= $reconciled ?> rapprochées</span>
        <?php if ($unreconc > 0): ?>
        <span class="text-amber-500 font-medium">⏳ <?= $unreconc ?> à rapprocher</span>
        <?php else: ?>
        <span class="text-fluent-green font-medium">✅ Tout est rapproché !</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <!-- Add form -->
    <div class="space-y-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <h2 class="font-semibold text-sm text-fluent-neutral mb-4">Nouvelle Transaction</h2>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Date *</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required
                        class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                </div>
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Description *</label>
                    <input name="desc" placeholder="Libellé du relevé" required
                        class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-fluent-green mb-1">Crédit (MAD)</label>
                        <input type="number" name="credit" placeholder="0.00" min="0" step="0.01"
                            class="w-full px-3 py-2.5 text-sm border border-green-200 dark:border-green-800 rounded-xl inp bg-white dark:bg-gray-700 text-right font-semibold text-fluent-green placeholder-fluent-n3">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-fluent-red mb-1">Débit (MAD)</label>
                        <input type="number" name="debit" placeholder="0.00" min="0" step="0.01"
                            class="w-full px-3 py-2.5 text-sm border border-red-200 dark:border-red-800 rounded-xl inp bg-white dark:bg-gray-700 text-right font-semibold text-fluent-red placeholder-fluent-n3">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Notes</label>
                    <input name="notes" placeholder="Référence, notes…"
                        class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
                </div>
                <!-- Year -->
                <select name="year" class="hidden"><option value="<?= $fy ?>" selected></option></select>
                <button type="submit" class="btn-f w-full py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk shadow-f">
                    Ajouter
                </button>
            </form>
        </div>

        <!-- Pending invoices reminder -->
        <?php if ($pending): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4">
            <h3 class="font-semibold text-xs text-fluent-n2 uppercase tracking-wider mb-3">Factures en Attente</h3>
            <div class="space-y-2 max-h-48 overflow-y-auto">
                <?php foreach ($pending as $p): ?>
                <div class="flex items-center justify-between px-2 py-1.5 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-xs">
                    <div class="min-w-0">
                        <div class="font-semibold text-amber-700 dark:text-amber-400 truncate"><?= h($p['invoice_number']) ?></div>
                        <div class="text-amber-600/70 dark:text-amber-500 truncate"><?= h($p['client_name']) ?></div>
                    </div>
                    <span class="font-bold text-amber-700 dark:text-amber-400 flex-shrink-0 ml-2"><?= money($p['amount_ttc']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Year selector -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4">
            <h3 class="font-semibold text-xs text-fluent-n2 uppercase tracking-wider mb-3">Exercice</h3>
            <div class="flex flex-wrap gap-2">
                <?php for ($y=(int)date('Y')+1;$y>=2020;$y--): ?>
                <a href="?year=<?= $y ?>" class="btn-f px-3 py-1.5 text-xs rounded-lg border transition-colors
                    <?= $y===$fy?'bg-fluent-blue text-white border-fluent-blue':'bg-fluent-n7 dark:bg-white/5 border-fluent-n4 dark:border-white/10 text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6' ?>">
                    <?= $y ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Transaction list -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-fluent-n5 dark:border-white/10">
                <h2 class="font-semibold text-sm text-fluent-neutral"><?= count($txAll) ?> transactions — <?= $fy ?></h2>
                <div class="flex items-center gap-2 text-xs text-fluent-n3">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-fluent-green inline-block"></span>Rapproché</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>Non rapproché</span>
                </div>
            </div>

            <?php if (empty($txAll)): ?>
            <div class="px-5 py-16 text-center">
                <div class="text-5xl mb-3">🏦</div>
                <div class="text-sm text-fluent-n3">Aucune transaction enregistrée</div>
                <div class="text-xs text-fluent-n3 mt-1">Saisissez vos mouvements bancaires ci-contre</div>
            </div>
            <?php else: ?>

            <!-- Desktop table -->
            <div class="hidden md:block overflow-x-auto max-h-[600px] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-fluent-n7 dark:bg-gray-900 border-b border-fluent-n5 dark:border-white/10">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-fluent-n3">DATE</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-fluent-n3">DESCRIPTION</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-fluent-green">CRÉDIT</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-fluent-red">DÉBIT</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-fluent-n3">SOLDE</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-fluent-n3">RAPPROCH.</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-fluent-n6 dark:divide-white/5">
                        <?php foreach ($txAll as $tx):
                            $isRec = (bool)$tx['reconciled'];
                            $gap   = $tx['invoice_id'] ? abs($tx['credit'] - (float)$tx['inv_amt']) : null;
                        ?>
                        <tr class="hover:bg-fluent-n7 dark:hover:bg-white/5 transition-colors group">
                            <td class="px-4 py-3 text-xs text-fluent-n3 whitespace-nowrap"><?= date('d/m/Y',strtotime($tx['transaction_date'])) ?></td>
                            <td class="px-4 py-3 max-w-[200px]">
                                <div class="text-sm font-medium text-fluent-neutral truncate"><?= h($tx['description']) ?></div>
                                <?php if ($tx['notes']): ?><div class="text-xs text-fluent-n3 truncate"><?= h($tx['notes']) ?></div><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold <?= $tx['credit']>0?'text-fluent-green':'text-fluent-n4' ?>">
                                <?= $tx['credit']>0 ? '+'.money($tx['credit']) : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold <?= $tx['debit']>0?'text-fluent-red':'text-fluent-n4' ?>">
                                <?= $tx['debit']>0 ? '-'.money($tx['debit']) : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-right text-xs font-mono <?= $tx['balance']>=0?'text-fluent-neutral':'text-fluent-red' ?>">
                                <?= money($tx['balance']) ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($isRec): ?>
                                <div class="flex flex-col items-center gap-0.5">
                                    <span class="text-[10px] badge-paid px-2 py-0.5 rounded-full font-semibold">✓ Rapproché</span>
                                    <?php if ($tx['invoice_number']): ?>
                                    <a href="/invoice-view.php?id=<?= $tx['invoice_id'] ?>" class="text-[9px] text-fluent-blue hover:underline"><?= h($tx['invoice_number']) ?></a>
                                    <?php endif; ?>
                                    <?php if ($gap !== null && $gap > 0.01): ?>
                                    <span class="text-[9px] text-amber-500 font-medium">Écart: <?= money($gap) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php elseif ($tx['credit'] > 0): ?>
                                <button onclick="openRecModal(<?= $tx['id'] ?>, <?= $tx['credit'] ?>)"
                                    class="btn-f text-[10px] px-2 py-1 bg-fluent-blue-lt dark:bg-fluent-blue/20 text-fluent-blue rounded-lg font-semibold hover:bg-blue-100 dark:hover:bg-fluent-blue/30">
                                    Rapprocher →
                                </button>
                                <?php else: ?>
                                <span class="text-xs text-fluent-n4">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" class="opacity-0 group-hover:opacity-100 transition-opacity">
                                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $tx['id'] ?>">
                                    <button type="submit" onclick="return confirmDelete()" class="btn-f p-1.5 text-fluent-n4 hover:text-fluent-red rounded-lg">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="md:hidden divide-y divide-fluent-n6 dark:divide-white/5 max-h-[500px] overflow-y-auto">
                <?php foreach ($txAll as $tx): $isRec=(bool)$tx['reconciled']; ?>
                <div class="px-4 py-3.5">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-fluent-neutral truncate"><?= h($tx['description']) ?></div>
                            <div class="text-xs text-fluent-n3"><?= date('d/m/Y',strtotime($tx['transaction_date'])) ?></div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <?php if ($tx['credit']>0): ?><div class="text-sm font-bold text-fluent-green">+<?= money($tx['credit']) ?></div><?php endif; ?>
                            <?php if ($tx['debit']>0): ?><div class="text-sm font-bold text-fluent-red">-<?= money($tx['debit']) ?></div><?php endif; ?>
                            <div class="text-[10px] text-fluent-n3">Solde: <?= money($tx['balance']) ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($isRec): ?>
                        <span class="text-[10px] badge-paid px-2 py-0.5 rounded-full font-semibold">✓ Rapproché</span>
                        <?php elseif ($tx['credit']>0): ?>
                        <button onclick="openRecModal(<?= $tx['id'] ?>, <?= $tx['credit'] ?>)"
                            class="btn-f text-[10px] px-2 py-1 bg-fluent-blue-lt dark:bg-fluent-blue/20 text-fluent-blue rounded-lg font-semibold">
                            Rapprocher →
                        </button>
                        <?php endif; ?>
                        <form method="POST" class="ml-auto">
                            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $tx['id'] ?>">
                            <button type="submit" onclick="return confirmDelete()" class="btn-f p-1.5 text-fluent-n4 hover:text-fluent-red rounded-lg">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer totals -->
            <div class="grid grid-cols-2 gap-px bg-fluent-n5 dark:bg-white/10 border-t border-fluent-n5 dark:border-white/10">
                <div class="bg-fluent-green-lt dark:bg-green-900/30 px-5 py-3 text-center">
                    <div class="text-xs text-fluent-green/70">Total Crédits</div>
                    <div class="text-base font-bold text-fluent-green"><?= money($totalCredit) ?></div>
                </div>
                <div class="bg-fluent-red-lt dark:bg-red-900/30 px-5 py-3 text-center">
                    <div class="text-xs text-fluent-red/70">Total Débits</div>
                    <div class="text-base font-bold text-fluent-red"><?= money($totalDebit) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Reconcile Modal -->
<div id="rec-modal" class="fixed inset-0 z-50 hidden flex items-end sm:items-center justify-center p-4 bg-black/50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-fl w-full max-w-md">
        <div class="px-5 py-4 border-b border-fluent-n5 dark:border-white/10 flex items-center justify-between">
            <h3 class="font-semibold text-fluent-neutral text-sm">Rapprocher la Transaction</h3>
            <button onclick="closeRecModal()" class="text-fluent-n3 hover:text-fluent-neutral text-xl leading-none">&times;</button>
        </div>
        <form method="POST" class="px-5 py-4 space-y-3">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="reconcile">
            <input type="hidden" name="tid" id="rec-tid">
            <div class="bg-fluent-blue-lt dark:bg-fluent-blue/20 rounded-xl px-4 py-2.5 text-sm font-semibold text-fluent-blue text-center" id="rec-amount"></div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Associer à la facture</label>
                <select name="iid" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                    <option value="">— Sélectionner une facture —</option>
                    <?php foreach ($allInv as $ai): ?>
                    <option value="<?= $ai['id'] ?>"><?= h($ai['invoice_number']) ?> — <?= h($ai['client_name']) ?> (<?= money($ai['amount_ttc']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="button" onclick="closeRecModal()" class="flex-1 py-2.5 border border-fluent-n4 dark:border-white/20 rounded-xl text-sm text-fluent-n2 hover:bg-fluent-n6 dark:hover:bg-white/10">Annuler</button>
                <button type="submit" class="flex-1 py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk">Rapprocher</button>
            </div>
        </form>
    </div>
</div>
<script>
function openRecModal(id, amount) {
    document.getElementById('rec-tid').value = id;
    document.getElementById('rec-amount').textContent = 'Montant: ' + formatMAD(amount);
    document.getElementById('rec-modal').classList.remove('hidden');
}
function closeRecModal() { document.getElementById('rec-modal').classList.add('hidden'); }
document.getElementById('rec-modal').addEventListener('click', e => { if (e.target === document.getElementById('rec-modal')) closeRecModal(); });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
