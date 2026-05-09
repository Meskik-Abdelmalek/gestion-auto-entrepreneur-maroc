<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Devis';
$db = getDB(); $cfg = getConfig();

// Duplicate
if (isset($_GET['duplicate'], $_GET['_csrf'])) {
    if (!hash_equals(csrfToken(), $_GET['_csrf'])) die('CSRF');
    $newId = duplicateQuote((int)$_GET['duplicate']);
    if ($newId) { flash('message','Devis dupliqué !'); header("Location: /quote-edit.php?id=$newId"); exit; }
}

// Export CSV
if (isset($_GET['export']) && $_GET['export']==='csv') exportQuotesCSV((int)($_GET['year']??date('Y')));

// Status change
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['status_change'])) {
    verifyCsrf();
    $id=(int)$_POST['id']; $st=clean($_POST['new_status']??'');
    $allowed=['Brouillon','Envoyé','Accepté','Refusé','Expiré'];
    if ($id && in_array($st,$allowed)) {
        $db->prepare("UPDATE ae_quotes SET status=? WHERE id=?")->execute([$st,$id]);
        flash('message',"Devis mis à jour : $st");
    }
    header('Location: /quotes.php'); exit;
}

// Filters
$status   = clean($_GET['status']   ?? '');
$search   = clean($_GET['q']        ?? '');
$year     = (int)($_GET['year']     ?? (int)date('Y'));
$page     = max(1,(int)($_GET['page']??1));
$perPage  = 20;

$where=['YEAR(q.quote_date)=:yr']; $params=[':yr'=>$year];
if ($status) { $where[]='q.status=:st'; $params[':st']=$status; }
if ($search) { $where[]='(q.client_name LIKE :q OR q.quote_number LIKE :q2)'; $params[':q']="%$search%"; $params[':q2']="%$search%"; }
$ws='WHERE '.implode(' AND ',$where);

$ct=$db->prepare("SELECT COUNT(*) FROM ae_quotes q $ws"); $ct->execute($params); $total=(int)$ct->fetchColumn();
$pages=max(1,(int)ceil($total/$perPage)); $offset=($page-1)*$perPage;
$st=$db->prepare("SELECT q.*, i.invoice_number AS linked_invoice FROM ae_quotes q LEFT JOIN ae_invoices i ON i.id=q.converted_invoice_id $ws ORDER BY q.quote_date DESC, q.id DESC LIMIT $perPage OFFSET $offset");
$st->execute($params); $quotes=$st->fetchAll();

$sum=$db->prepare("SELECT COALESCE(SUM(amount_ttc),0) total, COALESCE(SUM(CASE WHEN status='Accepté' THEN amount_ttc END),0) accepted, COALESCE(SUM(CASE WHEN status='Envoyé' THEN amount_ttc END),0) sent FROM ae_quotes q $ws");
$sum->execute($params); $sums=$sum->fetch();

// Status badge classes
function quoteBadge(string $s): string {
    return match($s) {
        'Brouillon' => 'badge-draft',
        'Envoyé'    => 'badge-sent',
        'Accepté'   => 'badge-accepted',
        'Refusé'    => 'badge-refused',
        'Expiré'    => 'badge-expired',
        default     => 'bg-fluent-n6 text-fluent-n3'
    };
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Toolbar -->
<div class="flex flex-col sm:flex-row gap-3 mb-4">
    <form method="GET" class="flex-1 flex gap-2">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fluent-n3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input name="q" value="<?= h($search) ?>" placeholder="Client, N° devis…"
                class="w-full pl-9 pr-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp placeholder-fluent-n3 shadow-f">
            <?php foreach (['status','year'] as $k): if($_GET[$k]??''): ?>
            <input type="hidden" name="<?= $k ?>" value="<?= h($_GET[$k]) ?>">
            <?php endif; endforeach; ?>
        </div>
        <select name="year" onchange="this.form.submit()" class="px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp text-fluent-n2">
            <?php for ($y=(int)date('Y')+1;$y>=2020;$y--): ?><option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
        </select>
    </form>
    <div class="flex items-center gap-1.5 flex-wrap">
        <?php foreach ([''=> 'Tous','Brouillon'=>'Brouillon','Envoyé'=>'Envoyé','Accepté'=>'Accepté','Refusé'=>'Refusé','Expiré'=>'Expiré'] as $v=>$l): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['status'=>$v,'page'=>1])) ?>"
            class="px-3 py-2 text-xs rounded-xl border font-medium transition-colors
            <?= $status===$v?'bg-fluent-blue text-white border-fluent-blue':'bg-white dark:bg-gray-800 text-fluent-n2 dark:text-gray-300 border-fluent-n4 dark:border-white/20 hover:bg-fluent-n6 dark:hover:bg-white/10' ?>">
            <?= $l ?>
        </a>
        <?php endforeach; ?>
        <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>"
            class="btn-f px-3 py-2 text-xs border border-fluent-n4 dark:border-white/20 bg-white dark:bg-gray-800 text-fluent-n2 rounded-xl hover:bg-fluent-n6 dark:hover:bg-white/10 flex items-center gap-1.5 shadow-f">
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
    <div class="bg-fluent-blue-lt dark:bg-fluent-blue/20 rounded-xl px-4 py-3 text-center">
        <div class="text-lg font-bold text-fluent-blue"><?= money($sums['sent']) ?></div>
        <div class="text-xs text-fluent-blue/70">Envoyés</div>
    </div>
    <div class="bg-fluent-green-lt dark:bg-green-900/30 rounded-xl px-4 py-3 text-center">
        <div class="text-lg font-bold text-fluent-green"><?= money($sums['accepted']) ?></div>
        <div class="text-xs text-green-600 dark:text-green-400">Acceptés</div>
    </div>
</div>

<!-- Table -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-fluent-n7 dark:bg-white/5 border-b border-fluent-n5 dark:border-white/10">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-fluent-n3">N° DEVIS</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-fluent-n3">CLIENT</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-fluent-n3">DATE</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-fluent-n3">VALIDITÉ</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-fluent-n3">MONTANT</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-fluent-n3">STATUT</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-fluent-n3">ACTIONS</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-fluent-n6 dark:divide-white/5">
                <?php if (empty($quotes)): ?>
                <tr><td colspan="7" class="px-5 py-12 text-center text-fluent-n3">
                    <div class="text-3xl mb-2">📋</div>
                    <div>Aucun devis trouvé</div>
                    <a href="/quote-new.php" class="text-xs text-fluent-blue hover:underline mt-1 inline-block">Créer votre premier devis →</a>
                </td></tr>
                <?php else: foreach ($quotes as $q):
                    $expired = $q['valid_until'] && $q['valid_until'] < date('Y-m-d') && !in_array($q['status'],['Accepté','Refusé']);
                    $daysLeft = $q['valid_until'] ? (int)ceil((strtotime($q['valid_until'])-time())/86400) : null;
                ?>
                <tr class="hover:bg-fluent-n7 dark:hover:bg-white/5 transition-colors">
                    <td class="px-5 py-3.5">
                        <a href="/quote-view.php?id=<?= $q['id'] ?>" class="text-fluent-blue font-medium hover:underline"><?= h($q['quote_number']) ?></a>
                        <?php if ($q['linked_invoice']): ?>
                        <div class="text-[10px] text-fluent-green mt-0.5">→ <?= h($q['linked_invoice']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3.5 font-medium text-fluent-neutral max-w-[160px] truncate"><?= h($q['client_name']) ?></td>
                    <td class="px-4 py-3.5 text-xs text-fluent-n3"><?= date('d/m/Y',strtotime($q['quote_date'])) ?></td>
                    <td class="px-4 py-3.5 text-xs">
                        <?php if ($q['valid_until']): ?>
                        <span class="<?= $expired?'text-fluent-red font-medium':($daysLeft<=7?'text-amber-500 font-medium':'text-fluent-n3') ?>">
                            <?= date('d/m/Y',strtotime($q['valid_until'])) ?>
                            <?php if ($daysLeft !== null && !in_array($q['status'],['Accepté','Refusé'])): ?>
                            <span class="text-[10px]">(<?= $expired?'expiré':"$daysLeft j" ?>)</span>
                            <?php endif; ?>
                        </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-4 py-3.5 text-right font-semibold text-fluent-neutral"><?= money($q['amount_ttc']) ?></td>
                    <td class="px-4 py-3.5 text-center">
                        <form method="POST" class="inline">
                            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="status_change" value="1">
                            <input type="hidden" name="id" value="<?= $q['id'] ?>">
                            <select name="new_status" onchange="this.form.submit()"
                                class="text-xs px-2 py-1 rounded-full font-medium border-0 cursor-pointer <?= quoteBadge($q['status']) ?> bg-transparent">
                                <?php foreach (['Brouillon','Envoyé','Accepté','Refusé','Expiré'] as $st): ?>
                                <option value="<?= $st ?>" <?= $q['status']===$st?'selected':'' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1">
                            <a href="/quote-view.php?id=<?= $q['id'] ?>" class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-blue rounded-lg hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/20" title="Voir">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <a href="/quote-edit.php?id=<?= $q['id'] ?>" class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-blue rounded-lg hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/20" title="Modifier">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <?php if (!$q['converted_invoice_id']): ?>
                            <a href="/api/quotes.php?action=convert&id=<?= $q['id'] ?>&_csrf=<?= $csrf ?>"
                                onclick="return confirm('Convertir ce devis en facture ?')"
                                class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-green rounded-lg hover:bg-fluent-green-lt dark:hover:bg-green-900/30" title="Convertir en facture">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="12" y2="12"/><line x1="15" y1="15" x2="12" y2="12"/></svg>
                            </a>
                            <?php endif; ?>
                            <a href="?duplicate=<?= $q['id'] ?>&_csrf=<?= $csrf ?>" class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-blue rounded-lg hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/20" title="Dupliquer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            </a>
                            <a href="/quote-view.php?id=<?= $q['id'] ?>&print=1" target="_blank" class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-blue rounded-lg hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/20" title="Imprimer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            </a>
                            <a href="/api/quotes.php?action=delete&id=<?= $q['id'] ?>&_csrf=<?= $csrf ?>"
                                onclick="return confirmDelete('Supprimer le devis <?= h($q['quote_number']) ?> ?')"
                                class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-red rounded-lg hover:bg-fluent-red-lt dark:hover:bg-red-900/30" title="Supprimer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
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
        <?php if (empty($quotes)): ?>
        <div class="px-5 py-10 text-center text-fluent-n3 text-sm">Aucun devis</div>
        <?php else: foreach ($quotes as $q): ?>
        <a href="/quote-view.php?id=<?= $q['id'] ?>" class="flex items-center gap-3 px-4 py-4 hover:bg-fluent-n7 dark:hover:bg-white/5">
            <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center font-bold text-sm <?= quoteBadge($q['status']) ?>">
                DEV
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-semibold text-fluent-neutral text-sm truncate"><?= h($q['client_name']) ?></span>
                    <span class="font-bold text-sm flex-shrink-0"><?= money($q['amount_ttc']) ?></span>
                </div>
                <div class="flex items-center justify-between mt-0.5">
                    <span class="text-xs text-fluent-n3"><?= h($q['quote_number']) ?> · <?= date('d/m/Y',strtotime($q['quote_date'])) ?></span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium <?= quoteBadge($q['status']) ?>"><?= h($q['status']) ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="flex items-center justify-between px-5 py-4 border-t border-fluent-n5 dark:border-white/10 bg-fluent-n7 dark:bg-white/5">
        <span class="text-xs text-fluent-n3">Page <?= $page ?>/<?= $pages ?> · <?= $total ?> devis</span>
        <div class="flex gap-2">
            <?php if ($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="btn-f px-3 py-1.5 text-xs border border-fluent-n4 dark:border-white/20 rounded-lg text-fluent-n2 bg-white dark:bg-gray-800">← Préc.</a><?php endif; ?>
            <?php if ($page<$pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="btn-f px-3 py-1.5 text-xs bg-fluent-blue text-white rounded-lg">Suiv. →</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- FAB -->
<a href="/quote-new.php" class="lg:hidden fixed bottom-20 right-4 z-40 w-14 h-14 bg-fluent-blue rounded-full shadow-fl flex items-center justify-center text-white">
    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
</a>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
