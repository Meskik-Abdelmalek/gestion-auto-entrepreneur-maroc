<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Relances';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_paid') {
        $id=$_POST['id']??0;
        $db->prepare("UPDATE ae_invoices SET status='Payé',payment_date=CURDATE() WHERE id=?")->execute([$id]);
        $db->prepare("UPDATE ae_reminders SET status='Payé' WHERE invoice_id=?")->execute([$id]);
        flash('message','Facture marquée comme payée !');
        header('Location: /reminders.php'); exit;
    }
    $id=$_POST['id']??0; $status=clean($_POST['status']??'En attente');
    $r1=clean($_POST['r1']??''); $r2=clean($_POST['r2']??''); $notes=clean($_POST['notes']??'');
    $check=$db->prepare("SELECT id FROM ae_reminders WHERE invoice_id=?"); $check->execute([$id]);
    if ($check->fetchColumn()) {
        $db->prepare("UPDATE ae_reminders SET status=?,reminder_1_date=?,reminder_2_date=?,notes=? WHERE invoice_id=?")
           ->execute([$status,$r1?:null,$r2?:null,$notes,$id]);
    } else {
        $db->prepare("INSERT INTO ae_reminders (invoice_id,status,reminder_1_date,reminder_2_date,notes) VALUES (?,?,?,?,?)")
           ->execute([$id,$status,$r1?:null,$r2?:null,$notes]);
    }
    flash('message','Relance mise à jour !');
    header('Location: /reminders.php'); exit;
}

$overdue = getOverdueInvoices();
$totalUnpaid = array_sum(array_column($overdue,'amount_ttc'));
$critical = count(array_filter($overdue,fn($i)=>(int)$i['days_overdue']>60));
$urgent   = count(array_filter($overdue,fn($i)=>(int)$i['days_overdue']>30&&(int)$i['days_overdue']<=60));

require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center col-span-2 lg:col-span-1">
        <div class="text-2xl font-bold text-fluent-red"><?= money($totalUnpaid) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5"><?= count($overdue) ?> factures impayées</div>
    </div>
    <div class="bg-fluent-red-lt dark:bg-red-900/30 rounded-2xl p-4 text-center">
        <div class="text-xl font-bold text-fluent-red"><?= $critical ?></div>
        <div class="text-xs text-fluent-red/70">🔴 Critiques (+60j)</div>
    </div>
    <div class="bg-fluent-orange-lt dark:bg-orange-900/20 rounded-2xl p-4 text-center">
        <div class="text-xl font-bold text-fluent-orange"><?= $urgent ?></div>
        <div class="text-xs text-fluent-orange/70">🟠 Urgents (30-60j)</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-xl font-bold text-fluent-neutral"><?= count($overdue)-$critical-$urgent ?></div>
        <div class="text-xs text-fluent-n3">🟡 Récents</div>
    </div>
</div>

<?php if (empty($overdue)): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-16 text-center">
    <div class="text-6xl mb-3">✅</div>
    <div class="text-lg font-semibold text-fluent-neutral">Aucune facture en retard</div>
    <div class="text-sm text-fluent-n3 mt-1">Tous vos paiements sont à jour. Continuez comme ça !</div>
</div>
<?php else: ?>
<div class="space-y-3">
    <?php foreach ($overdue as $inv):
        $days = (int)$inv['days_overdue'];
        [$urgColor,$urgBg,$urgEmoji,$urgLabel] = $days>60
            ? ['fluent-red','fluent-red-lt dark:bg-red-900/30','🔴','Critique']
            : ($days>30 ? ['fluent-orange','fluent-orange-lt dark:bg-orange-900/20','🟠','Urgent']
            : ($days>15 ? ['amber-600','amber-50 dark:bg-amber-900/20','🟡','Attention']
            : ['fluent-green','fluent-green-lt dark:bg-green-900/20','🟢','OK']));
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
        <!-- Header row -->
        <div class="flex items-center gap-3 px-5 py-4 border-b border-fluent-n5 dark:border-white/10">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl flex-shrink-0 bg-<?= $urgBg ?>">
                <?= $urgEmoji ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="/invoice-view.php?id=<?= $inv['id'] ?>" class="font-semibold text-fluent-neutral hover:text-fluent-blue transition-colors">
                        <?= h($inv['client_name']) ?>
                    </a>
                    <span class="text-xs text-fluent-n3"><?= h($inv['invoice_number']) ?></span>
                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold bg-<?= $urgBg ?> text-<?= $urgColor ?>"><?= $days ?>j · <?= $urgLabel ?></span>
                </div>
                <div class="text-xs text-fluent-n3 mt-0.5">
                    Émise le <?= date('d/m/Y',strtotime($inv['invoice_date'])) ?> ·
                    <span class="font-semibold text-fluent-neutral"><?= money($inv['amount_ttc']) ?></span>
                    · <?= h($inv['category']) ?>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="/invoice-view.php?id=<?= $inv['id'] ?>&print=1" target="_blank"
                    class="btn-f p-2 text-fluent-n3 hover:text-fluent-blue rounded-lg hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/20" title="Imprimer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                </a>
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="id" value="<?= $inv['id'] ?>">
                    <button type="submit" onclick="return confirm('Marquer comme payée ?')"
                        class="btn-f px-3 py-2 text-xs bg-fluent-green text-white rounded-xl font-semibold hover:bg-green-700 shadow-f">
                        ✓ Payé
                    </button>
                </form>
            </div>
        </div>
        <!-- Tracker -->
        <form method="POST" class="px-5 py-3 bg-fluent-n7 dark:bg-white/5">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="id" value="<?= $inv['id'] ?>">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <?php foreach ([['r1','1ère Relance','reminder_1_date'],['r2','2ème Relance','reminder_2_date']] as [$n,$l,$key]): ?>
                <div>
                    <label class="block text-[10px] font-medium text-fluent-n2 mb-1"><?= $l ?></label>
                    <input type="date" name="<?= $n ?>" value="<?= h($inv[$key]??'') ?>"
                        class="w-full px-2.5 py-2 text-xs border border-fluent-n4 dark:border-white/20 rounded-lg inp bg-white dark:bg-gray-700">
                </div>
                <?php endforeach; ?>
                <div>
                    <label class="block text-[10px] font-medium text-fluent-n2 mb-1">Statut</label>
                    <select name="status" class="w-full px-2.5 py-2 text-xs border border-fluent-n4 dark:border-white/20 rounded-lg inp bg-white dark:bg-gray-700">
                        <?php foreach (['En attente','Relancé 1x','Relancé 2x','Litige','Abandonné'] as $st): ?>
                        <option <?= ($inv['reminder_status']??'')===$st?'selected':'' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="btn-f w-full px-3 py-2 text-xs bg-fluent-blue text-white rounded-lg font-medium hover:bg-fluent-blue-dk">
                        Sauvegarder
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
