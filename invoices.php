<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Factures';
$db = getDB();

// Handle bulk action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    verifyCsrf();
    $ids    = array_map('intval', $_POST['ids'] ?? []);
    $action = clean($_POST['bulk_action']);
    if ($ids && in_array($action, ['mark_paid','mark_cancelled','delete'])) {
        $n = bulkUpdateInvoices($ids, $action);
        flash('message', "$n facture(s) mise(s) à jour !");
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
}

// Duplicate
if (isset($_GET['duplicate']) && isset($_GET['_csrf'])) {
    if (!hash_equals(csrfToken(), $_GET['_csrf'])) die('CSRF');
    $newId = duplicateInvoice((int)$_GET['duplicate']);
    if ($newId) { flash('message', 'Facture dupliquée !'); header("Location: /invoice-edit.php?id=$newId"); exit; }
}

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportInvoicesCSV(['year' => $_GET['year'] ?? null, 'status' => $_GET['status'] ?? null]);
}

// Filters
$status   = clean($_GET['status']   ?? '');
$category = clean($_GET['category'] ?? '');
$search   = clean($_GET['q']        ?? '');
$year     = (int)($_GET['year']     ?? (getConfig()['fiscal_year'] ?? date('Y')));
$sort     = in_array($_GET['sort']??'', ['invoice_date','amount_ttc','client_name','status']) ? $_GET['sort'] : 'invoice_date';
$dir      = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;

$where = ['fiscal_year = :fy'];
$params = [':fy' => $year];
if ($status)   { $where[] = 'status = :st';   $params[':st']   = $status; }
if ($category) { $where[] = 'category = :cat'; $params[':cat']  = $category; }
if ($search)   { $where[] = '(client_name LIKE :q OR invoice_number LIKE :q2)'; $params[':q'] = "%$search%"; $params[':q2'] = "%$search%"; }
$ws = 'WHERE ' . implode(' AND ', $where);

$total  = (int)$db->prepare("SELECT COUNT(*) FROM ae_invoices $ws")->execute($params) && false;
$ct = $db->prepare("SELECT COUNT(*) FROM ae_invoices $ws"); $ct->execute($params); $total = (int)$ct->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$st = $db->prepare("SELECT * FROM ae_invoices $ws ORDER BY $sort $dir LIMIT $perPage OFFSET $offset");
$st->execute($params);
$invoices = $st->fetchAll();

$sumSt = $db->prepare("SELECT COALESCE(SUM(amount_ttc),0) total, COALESCE(SUM(CASE WHEN status='Payé' THEN amount_ttc END),0) paid, COALESCE(SUM(CASE WHEN status='En attente' THEN amount_ttc END),0) pending FROM ae_invoices $ws");
$sumSt->execute($params); $sums = $sumSt->fetch();

$sortUrl = fn($col) => '?' . http_build_query(array_merge($_GET, ['sort'=>$col,'dir'=>($sort===$col&&$dir==='asc')?'desc':'asc','page'=>1]));
$sortIcon = fn($col) => $sort===$col ? ($dir==='asc'?'↑':'↓') : '<span class="opacity-30">↕</span>';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Toolbar -->
<div class="flex flex-col sm:flex-row gap-3 mb-4">
    <!-- Search -->
    <form method="GET" class="flex-1 flex gap-2">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fluent-n3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input name="q" value="<?= h($search) ?>" placeholder="Client, N° facture…"
                class="w-full pl-9 pr-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp placeholder-fluent-n3 shadow-f">
            <?php foreach (['status','category','year','sort','dir'] as $k): if($_GET[$k]??''): ?>
            <input type="hidden" name="<?= $k ?>" value="<?= h($_GET[$k]) ?>">
            <?php endif; endforeach; ?>
        </div>
        <!-- Year -->
        <select name="year" onchange="this.form.submit()" class="px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp text-fluent-n2">
            <?php for ($y=(int)date('Y')+1; $y>=2020; $y--): ?>
            <option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </form>
    <!-- Status filters + export -->
    <div class="flex items-center gap-1.5 flex-wrap">
        <?php foreach ([''=> 'Toutes','Payé'=>'Payées','En attente'=>'En attente','Annulé'=>'Annulées'] as $v=>$l):
            $u = '?' . http_build_query(array_merge($_GET,['status'=>$v,'page'=>1]));
        ?>
        <a href="<?= $u ?>" class="px-3 py-2 text-xs rounded-xl border font-medium transition-colors
            <?= $status===$v ? 'bg-fluent-blue text-white border-fluent-blue' : 'bg-white dark:bg-gray-800 text-fluent-n2 dark:text-gray-300 border-fluent-n4 dark:border-white/20 hover:bg-fluent-n6 dark:hover:bg-white/10' ?>">
            <?= $l ?>
        </a>
        <?php endforeach; ?>
        <!-- Export CSV -->
        <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>"
            class="btn-f px-3 py-2 text-xs rounded-xl border border-fluent-n4 dark:border-white/20 bg-white dark:bg-gray-800 text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6 dark:hover:bg-white/10 flex items-center gap-1.5" data-tip="Exporter CSV">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            CSV
        </a>
    </div>
</div>

<!-- Summary -->
<div class="grid grid-cols-3 gap-3 mb-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-f px-4 py-3 text-center">
        <div class="text-lg font-bold text-fluent-neutral"><?= money($sums['total']) ?></div>
        <div class="text-xs text-fluent-n3">Total (<?= $total ?>)</div>
    </div>
    <div class="bg-fluent-green-lt dark:bg-green-900/30 rounded-xl px-4 py-3 text-center">
        <div class="text-lg font-bold text-fluent-green"><?= money($sums['paid']) ?></div>
        <div class="text-xs text-green-600 dark:text-green-400">Encaissé</div>
    </div>
    <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl px-4 py-3 text-center">
        <div class="text-lg font-bold text-amber-600"><?= money($sums['pending']) ?></div>
        <div class="text-xs text-amber-500">En attente</div>
    </div>
</div>

<!-- Bulk form -->
<form method="POST" id="bulk-form">
<input type="hidden" name="_csrf" value="<?= $csrf ?>">
<input type="hidden" name="bulk_action" id="bulk-action-input" value="">

<!-- Bulk action bar (hidden by default) -->
<div id="bulk-bar" class="hidden mb-3 flex items-center gap-3 px-4 py-3 bg-fluent-blue-lt dark:bg-fluent-blue/20 border border-fluent-blue/30 rounded-xl text-sm">
    <span class="font-semibold text-fluent-blue" id="bulk-count">0 sélectionnée(s)</span>
    <div class="flex gap-2 ml-auto">
        <button type="button" onclick="bulkAction('mark_paid')" class="btn-f px-3 py-1.5 bg-fluent-green text-white rounded-lg text-xs font-semibold hover:bg-green-700">✓ Marquer Payées</button>
        <button type="button" onclick="bulkAction('mark_cancelled')" class="btn-f px-3 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-semibold hover:bg-amber-600">Annuler</button>
        <button type="button" onclick="bulkAction('delete')" class="btn-f px-3 py-1.5 bg-fluent-red text-white rounded-lg text-xs font-semibold hover:bg-red-700">🗑 Supprimer</button>
        <button type="button" onclick="clearSelection()" class="btn-f px-3 py-1.5 bg-white dark:bg-gray-700 border border-fluent-n4 dark:border-white/20 text-fluent-n2 rounded-lg text-xs">Annuler</button>
    </div>
</div>

<!-- Table -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
    <!-- Desktop table -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-fluent-n5 dark:border-white/10 bg-fluent-n7 dark:bg-white/5">
                    <th class="px-4 py-3 w-8">
                        <input type="checkbox" id="select-all" onchange="toggleAll(this)" class="rounded border-fluent-n4">
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-fluent-n3">
                        <a href="<?= $sortUrl('invoice_date') ?>" class="hover:text-fluent-neutral flex items-center gap-1">DATE <?= $sortIcon('invoice_date') ?></a>
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-fluent-n3">
                        <a href="<?= $sortUrl('invoice_date') ?>" class="hover:text-fluent-neutral flex items-center gap-1">N° FACTURE</a>
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-fluent-n3">
                        <a href="<?= $sortUrl('client_name') ?>" class="hover:text-fluent-neutral flex items-center gap-1">CLIENT <?= $sortIcon('client_name') ?></a>
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-fluent-n3">CAT.</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-fluent-n3">
                        <a href="<?= $sortUrl('amount_ttc') ?>" class="hover:text-fluent-neutral flex items-center gap-1 justify-end">MONTANT <?= $sortIcon('amount_ttc') ?></a>
                    </th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-fluent-n3">
                        <a href="<?= $sortUrl('status') ?>" class="hover:text-fluent-neutral flex items-center gap-1 justify-center">STATUT <?= $sortIcon('status') ?></a>
                    </th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-fluent-n3">ACTIONS</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-fluent-n6 dark:divide-white/5">
                <?php if (empty($invoices)): ?>
                <tr><td colspan="8" class="px-5 py-12 text-center text-fluent-n3 text-sm">Aucune facture trouvée</td></tr>
                <?php else: foreach ($invoices as $inv): ?>
                <tr class="check-row hover:bg-fluent-n7 dark:hover:bg-white/5 transition-colors">
                    <td class="px-4 py-3.5">
                        <input type="checkbox" name="ids[]" value="<?= $inv['id'] ?>" class="row-check rounded border-fluent-n4" onchange="updateBulkBar()">
                    </td>
                    <td class="px-4 py-3.5 text-xs text-fluent-n3"><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td>
                    <td class="px-4 py-3.5">
                        <a href="/invoice-view.php?id=<?= $inv['id'] ?>" class="text-fluent-blue font-medium hover:underline text-sm"><?= h($inv['invoice_number']) ?></a>
                    </td>
                    <td class="px-4 py-3.5 font-medium text-fluent-neutral max-w-[160px] truncate"><?= h($inv['client_name']) ?></td>
                    <td class="px-4 py-3.5">
                        <span class="text-xs px-2 py-0.5 rounded-full bg-fluent-blue-lt dark:bg-fluent-blue/20 text-fluent-blue font-medium"><?= h($inv['category']) ?></span>
                    </td>
                    <td class="px-4 py-3.5 text-right font-semibold text-fluent-neutral"><?= money($inv['amount_ttc']) ?></td>
                    <td class="px-4 py-3.5 text-center">
                        <span class="text-xs px-2.5 py-1 rounded-full font-medium
                            <?= $inv['status']==='Payé' ? 'badge-paid' : ($inv['status']==='En attente' ? 'badge-pending' : 'badge-cancelled') ?>">
                            <?= h($inv['status']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1">
                            <a href="/invoice-view.php?id=<?= $inv['id'] ?>" class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-blue rounded-lg hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/20" data-tip="Voir">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <a href="/invoice-edit.php?id=<?= $inv['id'] ?>" class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-blue rounded-lg hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/20" data-tip="Modifier">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <a href="?duplicate=<?= $inv['id'] ?>&_csrf=<?= $csrf ?>" class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-blue rounded-lg hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/20" data-tip="Dupliquer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            </a>
                            <a href="/invoice-view.php?id=<?= $inv['id'] ?>&print=1" target="_blank" class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-blue rounded-lg hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/20" data-tip="Imprimer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile cards -->
    <div class="md:hidden divide-y divide-fluent-n6 dark:divide-white/5">
        <?php if (empty($invoices)): ?>
        <div class="px-5 py-10 text-center text-fluent-n3 text-sm">Aucune facture</div>
        <?php else: foreach ($invoices as $inv): ?>
        <div class="flex items-center gap-3 px-4 py-3.5">
            <input type="checkbox" name="ids[]" value="<?= $inv['id'] ?>" class="row-check rounded border-fluent-n4 flex-shrink-0" onchange="updateBulkBar()">
            <a href="/invoice-view.php?id=<?= $inv['id'] ?>" class="flex items-center gap-3 flex-1 min-w-0">
                <div class="w-9 h-9 rounded-xl flex-shrink-0 flex items-center justify-center font-bold text-sm
                    <?= $inv['status']==='Payé'?'bg-fluent-green-lt text-fluent-green':($inv['status']==='En attente'?'bg-amber-50 text-amber-600':'bg-fluent-red-lt text-fluent-red') ?>">
                    <?= $inv['status']==='Payé'?'✓':($inv['status']==='Annulé'?'✕':'…') ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between gap-2">
                        <span class="font-semibold text-fluent-neutral text-sm truncate"><?= h($inv['client_name']) ?></span>
                        <span class="font-bold text-sm flex-shrink-0 <?= $inv['status']==='Payé'?'text-fluent-green':'' ?>"><?= money($inv['amount_ttc']) ?></span>
                    </div>
                    <div class="flex items-center justify-between mt-0.5">
                        <span class="text-xs text-fluent-n3"><?= h($inv['invoice_number']) ?> · <?= date('d/m/Y',strtotime($inv['invoice_date'])) ?></span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium <?= $inv['status']==='Payé'?'badge-paid':($inv['status']==='En attente'?'badge-pending':'badge-cancelled') ?>"><?= h($inv['status']) ?></span>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="flex items-center justify-between px-5 py-4 border-t border-fluent-n5 dark:border-white/10 bg-fluent-n7 dark:bg-white/5">
        <span class="text-xs text-fluent-n3">Page <?= $page ?>/<?= $pages ?> · <?= $total ?> factures</span>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>"
                class="btn-f px-3 py-1.5 text-xs border border-fluent-n4 dark:border-white/20 rounded-lg text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6 dark:hover:bg-white/10 bg-white dark:bg-gray-800">← Préc.</a>
            <?php endif; if ($page < $pages): ?>
            <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>"
                class="btn-f px-3 py-1.5 text-xs bg-fluent-blue text-white rounded-lg hover:bg-fluent-blue-dk">Suiv. →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</form>

<!-- FAB -->
<a href="/invoice-new.php" class="lg:hidden fixed bottom-20 right-4 z-40 w-14 h-14 bg-fluent-blue rounded-full shadow-fl flex items-center justify-center text-white">
    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
</a>

<script>
function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
    updateBulkBar();
}
function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    const bar = document.getElementById('bulk-bar');
    document.getElementById('bulk-count').textContent = checked + ' sélectionnée(s)';
    bar.classList.toggle('hidden', checked === 0);
    const sa = document.getElementById('select-all');
    if (sa) sa.indeterminate = checked > 0 && checked < document.querySelectorAll('.row-check').length;
}
function clearSelection() {
    document.querySelectorAll('.row-check, #select-all').forEach(c => c.checked = false);
    document.getElementById('bulk-bar').classList.add('hidden');
}
function bulkAction(action) {
    const labels = {mark_paid:'Marquer comme payées',mark_cancelled:'Annuler',delete:'Supprimer définitivement'};
    if (!confirm(labels[action] + ' les factures sélectionnées ?')) return;
    document.getElementById('bulk-action-input').value = action;
    document.getElementById('bulk-form').submit();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
