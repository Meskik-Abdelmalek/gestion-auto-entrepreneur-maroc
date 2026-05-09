<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Clients';
$db = getDB();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'add';
    if ($action === 'delete') {
        $db->prepare("DELETE FROM ae_clients WHERE id=?")->execute([(int)$_POST['id']]);
        flash('message','Client supprimé.','error');
        header('Location: /clients.php'); exit;
    }
    if ($action === 'edit') {
        $db->prepare("UPDATE ae_clients SET name=?,email=?,phone=?,address=?,ice=?,city=?,category=?,notes=? WHERE id=?")
           ->execute([clean($_POST['name']??''),clean($_POST['email']??''),clean($_POST['phone']??''),
               clean($_POST['address']??''),clean($_POST['ice']??''),clean($_POST['city']??''),
               clean($_POST['category']??'Service'),clean($_POST['notes']??''),(int)$_POST['id']]);
        flash('message','Client mis à jour !');
        header('Location: /clients.php'); exit;
    }
    // add
    $name = clean($_POST['name']??'');
    if ($name) {
        $db->prepare("INSERT INTO ae_clients (name,email,phone,address,ice,city,category,notes) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$name,clean($_POST['email']??''),clean($_POST['phone']??''),clean($_POST['address']??''),
               clean($_POST['ice']??''),clean($_POST['city']??''),clean($_POST['category']??'Service'),clean($_POST['notes']??'')]);
        flash('message','Client ajouté !');
    }
    header('Location: /clients.php'); exit;
}

$search = clean($_GET['q'] ?? '');
$viewId = (int)($_GET['view'] ?? 0);

$stmt = $db->prepare("SELECT c.*,
    COUNT(DISTINCT i.id) AS invoice_count,
    COALESCE(SUM(CASE WHEN i.status='Payé' THEN i.amount_ttc END),0) AS total_revenue,
    COALESCE(SUM(CASE WHEN i.status='En attente' THEN i.amount_ttc END),0) AS pending_revenue,
    MAX(i.invoice_date) AS last_invoice_date
    FROM ae_clients c
    LEFT JOIN ae_invoices i ON i.client_name=c.name
    " . ($search ? "WHERE c.name LIKE :q OR c.city LIKE :q2 OR c.email LIKE :q3" : "") . "
    GROUP BY c.id ORDER BY total_revenue DESC");
$search ? $stmt->execute([':q'=>"%$search%",':q2'=>"%$search%",':q3'=>"%$search%"]) : $stmt->execute();
$clients = $stmt->fetchAll();

$totalClients = count($clients);
$totalRevenue = array_sum(array_column($clients,'total_revenue'));
$topClient    = $clients[0] ?? null;

// If viewing a client detail
$viewClient = null; $clientInvoices = [];
if ($viewId) {
    $vs = $db->prepare("SELECT * FROM ae_clients WHERE id=?"); $vs->execute([$viewId]); $viewClient = $vs->fetch();
    if ($viewClient) {
        $ci = $db->prepare("SELECT * FROM ae_invoices WHERE client_name=? ORDER BY invoice_date DESC LIMIT 20");
        $ci->execute([$viewClient['name']]); $clientInvoices = $ci->fetchAll();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($viewClient): ?>
<!-- ── Client Detail Panel ── -->
<div class="mb-4">
    <a href="/clients.php" class="inline-flex items-center gap-1.5 text-sm text-fluent-n2 hover:text-fluent-blue transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Tous les clients
    </a>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-f p-6">
        <div class="flex items-start gap-4 mb-5">
            <div class="w-14 h-14 rounded-2xl bg-fluent-blue flex items-center justify-center text-white text-xl font-bold flex-shrink-0 shadow-f">
                <?= strtoupper(mb_substr($viewClient['name'],0,2)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold text-fluent-neutral"><?= h($viewClient['name']) ?></h1>
                <div class="text-sm text-fluent-n3 mt-0.5"><?= h($viewClient['city']??'') ?><?= $viewClient['email'] ? ' · '.h($viewClient['email']) : '' ?></div>
                <div class="flex items-center gap-2 mt-2">
                    <span class="text-xs px-2 py-0.5 rounded-full bg-fluent-blue-lt text-fluent-blue font-medium"><?= h($viewClient['category']) ?></span>
                    <?php if ($viewClient['ice']): ?>
                    <span class="text-xs text-fluent-n3">ICE: <?= h($viewClient['ice']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="/invoice-new.php?client=<?= urlencode($viewClient['name']) ?>"
                    class="btn-f flex items-center gap-1.5 px-4 py-2 bg-fluent-blue text-white rounded-xl text-sm font-semibold shadow-f hover:bg-fluent-blue-dk">
                    + Facture
                </a>
            </div>
        </div>
        <?php if ($viewClient['address']): ?>
        <div class="text-xs text-fluent-n3 mb-4 bg-fluent-n7 dark:bg-white/5 rounded-xl px-4 py-2.5">📍 <?= h($viewClient['address']) ?></div>
        <?php endif; ?>
        <!-- Invoice history -->
        <h3 class="font-semibold text-sm text-fluent-neutral mb-3">Historique des Factures</h3>
        <?php if (empty($clientInvoices)): ?>
        <div class="py-6 text-center text-sm text-fluent-n3">Aucune facture pour ce client</div>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($clientInvoices as $inv): ?>
            <a href="/invoice-view.php?id=<?= $inv['id'] ?>"
                class="flex items-center gap-3 px-4 py-3 bg-fluent-n7 dark:bg-white/5 rounded-xl hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/10 transition-colors">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0
                    <?= $inv['status']==='Payé'?'bg-fluent-green-lt text-fluent-green':($inv['status']==='En attente'?'bg-amber-50 text-amber-600':'bg-fluent-red-lt text-fluent-red') ?>">
                    <?= $inv['status']==='Payé'?'✓':($inv['status']==='Annulé'?'✕':'…') ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-fluent-neutral"><?= h($inv['invoice_number']) ?></div>
                    <div class="text-xs text-fluent-n3"><?= date('d/m/Y',strtotime($inv['invoice_date'])) ?> · <?= h($inv['category']) ?></div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-semibold text-fluent-neutral"><?= money($inv['amount_ttc']) ?></div>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full <?= $inv['status']==='Payé'?'badge-paid':($inv['status']==='En attente'?'badge-pending':'badge-cancelled') ?>"><?= h($inv['status']) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <!-- Stats sidebar -->
    <div class="space-y-3">
        <?php
        $clientStats = $db->prepare("SELECT COUNT(*) inv, COALESCE(SUM(CASE WHEN status='Payé' THEN amount_ttc END),0) rev, COALESCE(SUM(CASE WHEN status='En attente' THEN amount_ttc END),0) pend FROM ae_invoices WHERE client_name=?");
        $clientStats->execute([$viewClient['name']]); $cs = $clientStats->fetch();
        $kpis = [['Revenus Encaissés',money($cs['rev']),'text-fluent-green','bg-fluent-green-lt dark:bg-green-900/30'],['En Attente',money($cs['pend']),'text-amber-600','bg-amber-50 dark:bg-amber-900/20'],['Factures',number_format($cs['inv']),'text-fluent-blue','bg-fluent-blue-lt dark:bg-fluent-blue/20']];
        foreach ($kpis as [$l,$v,$tc,$bg]): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
            <div class="text-xl font-bold <?= $tc ?>"><?= $v ?></div>
            <div class="text-xs text-fluent-n3 mt-0.5"><?= $l ?></div>
        </div>
        <?php endforeach; ?>
        <!-- Edit form -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4">
            <h3 class="font-semibold text-sm text-fluent-neutral mb-3">Modifier</h3>
            <form method="POST" class="space-y-2">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $viewClient['id'] ?>">
                <?php foreach ([['name','Nom','text'],['email','Email','email'],['phone','Tél','text'],['city','Ville','text'],['ice','ICE','text']] as [$n,$l,$t]): ?>
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-0.5"><?= $l ?></label>
                    <input type="<?= $t ?>" name="<?= $n ?>" value="<?= h($viewClient[$n]??'') ?>"
                        class="w-full px-2.5 py-2 text-xs border border-fluent-n4 dark:border-white/20 rounded-lg inp bg-white dark:bg-gray-700">
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn-f w-full py-2 bg-fluent-blue text-white rounded-xl text-xs font-semibold hover:bg-fluent-blue-dk mt-1">Sauvegarder</button>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ── Client List ── -->

<!-- Stats row -->
<div class="grid grid-cols-3 gap-3 mb-5">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-2xl font-bold text-fluent-neutral"><?= $totalClients ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Clients Total</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-xl font-bold text-fluent-green"><?= money($totalRevenue) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Revenus Total</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-base font-bold text-fluent-neutral truncate"><?= h($topClient['name'] ?? '—') ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Top Client</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Add form -->
    <div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5 sticky top-20">
            <h2 class="font-semibold text-fluent-neutral text-sm mb-4 flex items-center gap-2">
                <span class="w-6 h-6 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs font-bold">+</span>
                Nouveau Client
            </h2>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <?php
                $fields = [['name','Nom *','text','Nom ou raison sociale'],['email','Email','email','contact@example.com'],['phone','Téléphone','text','+212 6XX XXX XXX'],['city','Ville','text','Casablanca'],['ice','ICE','text','003938XXXXXXXXX']];
                foreach ($fields as [$n,$l,$t,$ph]): ?>
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1"><?= $l ?></label>
                    <input type="<?= $t ?>" name="<?= $n ?>" placeholder="<?= $ph ?>"
                        class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
                </div>
                <?php endforeach; ?>
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Adresse</label>
                    <textarea name="address" rows="2" placeholder="Rue, Ville, Maroc"
                        class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 resize-none placeholder-fluent-n3"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Catégorie</label>
                    <select name="category" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                        <option>Service</option><option>Commerce</option><option>Industrie</option>
                    </select>
                </div>
                <button type="submit" class="btn-f w-full py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk shadow-f">
                    Ajouter le Client
                </button>
            </form>
        </div>
    </div>

    <!-- List -->
    <div class="lg:col-span-2">
        <!-- Search -->
        <form class="mb-4">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fluent-n3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input name="q" value="<?= h($search) ?>" placeholder="Rechercher client, ville…"
                    class="w-full pl-9 pr-4 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp shadow-f placeholder-fluent-n3">
            </div>
        </form>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
            <?php if (empty($clients)): ?>
            <div class="px-5 py-12 text-center">
                <div class="text-4xl mb-2">👥</div>
                <div class="text-sm text-fluent-n3">Aucun client trouvé</div>
            </div>
            <?php else: ?>
            <div class="divide-y divide-fluent-n6 dark:divide-white/5">
                <?php foreach ($clients as $cl):
                    $pct = $totalRevenue > 0 ? ($cl['total_revenue'] / $totalRevenue) * 100 : 0;
                ?>
                <div class="flex items-center gap-3 px-5 py-4 hover:bg-fluent-n7 dark:hover:bg-white/5 group transition-colors">
                    <a href="/clients.php?view=<?= $cl['id'] ?>"
                        class="w-10 h-10 rounded-xl bg-gradient-to-br from-fluent-blue to-blue-700 flex-shrink-0 flex items-center justify-center font-bold text-white text-sm shadow-f">
                        <?= strtoupper(mb_substr($cl['name'],0,2)) ?>
                    </a>
                    <a href="/clients.php?view=<?= $cl['id'] ?>" class="flex-1 min-w-0">
                        <div class="font-semibold text-fluent-neutral text-sm"><?= h($cl['name']) ?></div>
                        <div class="text-xs text-fluent-n3 mt-0.5 flex items-center gap-2">
                            <?php if ($cl['city']): ?><span><?= h($cl['city']) ?></span><?php endif; ?>
                            <?php if ($cl['phone']): ?><span>· <?= h($cl['phone']) ?></span><?php endif; ?>
                            <span class="text-fluent-blue font-medium"><?= $cl['invoice_count'] ?> facture(s)</span>
                        </div>
                        <!-- Mini revenue bar -->
                        <?php if ($pct > 0): ?>
                        <div class="mt-1.5 h-1 bg-fluent-n5 dark:bg-white/10 rounded-full overflow-hidden w-32">
                            <div class="h-full bg-fluent-blue rounded-full" style="width:<?= round($pct) ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </a>
                    <div class="text-right flex-shrink-0">
                        <div class="text-sm font-bold text-fluent-green"><?= money($cl['total_revenue']) ?></div>
                        <?php if ($cl['pending_revenue'] > 0): ?>
                        <div class="text-xs text-amber-500"><?= money($cl['pending_revenue']) ?> att.</div>
                        <?php endif; ?>
                        <?php if ($cl['last_invoice_date']): ?>
                        <div class="text-[10px] text-fluent-n3"><?= date('d/m/Y',strtotime($cl['last_invoice_date'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <!-- Actions (visible on hover) -->
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <a href="/invoice-new.php?client=<?= urlencode($cl['name']) ?>"
                            class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-blue rounded-lg hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/20" title="Nouvelle facture">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </a>
                        <form method="POST" class="flex-shrink-0">
                            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $cl['id'] ?>">
                            <button type="submit" onclick="return confirmDelete('Supprimer <?= h(addslashes($cl['name'])) ?> ?')"
                                class="btn-f p-1.5 text-fluent-n3 hover:text-fluent-red rounded-lg hover:bg-fluent-red-lt dark:hover:bg-red-900/30">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
