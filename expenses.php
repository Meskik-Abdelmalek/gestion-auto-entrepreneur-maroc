<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Dépenses';
$db = getDB(); $cfg = getConfig();
$fy = (int)($_GET['year'] ?? $cfg['fiscal_year'] ?? date('Y'));
if (isset($_GET['export']) && $_GET['export']==='csv') exportExpensesCSV($fy);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if ($_POST['action']==='delete') {
        $db->prepare("DELETE FROM ae_expenses WHERE id=?")->execute([(int)$_POST['id']]);
        flash('message','Dépense supprimée.','error');
        header("Location: /expenses.php?year=$fy"); exit;
    }
    $amount = (float)($_POST['amount']??0);
    if ($amount > 0) {
        $db->prepare("INSERT INTO ae_expenses (expense_date,supplier,expense_number,description,category,amount,payment_method,has_receipt,fiscal_year,notes) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([clean($_POST['expense_date']??date('Y-m-d')),clean($_POST['supplier']??''),clean($_POST['expense_number']??''),
               clean($_POST['description']??''),clean($_POST['category']??'Autres'),$amount,
               clean($_POST['payment_method']??''),isset($_POST['has_receipt'])?1:0,$fy,clean($_POST['notes']??'')]);
        flash('message','Dépense ajoutée !');
    }
    header("Location: /expenses.php?year=$fy"); exit;
}

$cat_filter = clean($_GET['cat'] ?? '');
$search     = clean($_GET['q']   ?? '');
$where = ['fiscal_year=?']; $params = [$fy];
if ($cat_filter) { $where[] = 'category=?'; $params[] = $cat_filter; }
if ($search)     { $where[] = '(description LIKE ? OR supplier LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$ws = 'WHERE '.implode(' AND ',$where);

$exps = $db->prepare("SELECT * FROM ae_expenses $ws ORDER BY expense_date DESC");
$exps->execute($params); $expenses = $exps->fetchAll();

// Totals
$totalExp  = array_sum(array_column($expenses, 'amount'));
$revStmt   = $db->prepare("SELECT COALESCE(SUM(amount_ttc),0) FROM ae_invoices WHERE status='Payé' AND fiscal_year=?");
$revStmt->execute([$fy]); $totalRev = (float)$revStmt->fetchColumn();
$netResult = $totalRev - $totalExp;
$thisMonth = array_sum(array_map(fn($e) => date('Ym',strtotime($e['expense_date']))==date('Ym') ? $e['amount'] : 0, $expenses));

// By category (all, for chart)
$allCatStmt = $db->prepare("SELECT category, SUM(amount) amt, COUNT(*) cnt FROM ae_expenses WHERE fiscal_year=? GROUP BY category ORDER BY amt DESC");
$allCatStmt->execute([$fy]); $byCat = $allCatStmt->fetchAll();
$totalAllExp = array_sum(array_column($byCat,'amt')) ?: 1;

// Monthly trend
$monStmt = $db->prepare("SELECT MONTH(expense_date) m, SUM(amount) amt FROM ae_expenses WHERE fiscal_year=? GROUP BY m ORDER BY m");
$monStmt->execute([$fy]); $monthly = array_fill(1,12,0);
foreach ($monStmt->fetchAll() as $r) $monthly[(int)$r['m']] = (float)$r['amt'];
$maxMon = max(array_values($monthly)) ?: 1;

$cats = ['Fournitures','Logiciel/Abonnement','Transport','Formation','Marketing','Matériel','Téléphonie','Autres'];
$catColors = ['Fournitures'=>'#0078d4','Logiciel/Abonnement'=>'#553c9a','Transport'=>'#ca5010','Formation'=>'#107c10','Marketing'=>'#a4262c','Matériel'=>'#835b00','Téléphonie'=>'#234e52','Autres'=>'#605e5c'];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Summary KPIs -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center col-span-2 lg:col-span-1">
        <div class="text-2xl font-bold text-fluent-red"><?= money($totalExp) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Total Dépenses <?= $fy ?></div>
        <div class="text-xs text-amber-500 mt-1 font-medium">Ce mois: <?= money($thisMonth) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-xl font-bold text-fluent-green"><?= money($totalRev) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Revenus <?= $fy ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-xl font-bold <?= $netResult>=0?'text-fluent-green':'text-fluent-red' ?>"><?= money($netResult) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Résultat Net</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-xl font-bold text-fluent-neutral"><?= count($expenses) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Dépenses enregistrées</div>
        <div class="text-xs text-fluent-n3 mt-0.5">Moy: <?= money(count($expenses)>0?$totalExp/count($expenses):0) ?></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Left: Add form + chart -->
    <div class="space-y-4">
        <!-- Add form -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <h2 class="font-semibold text-sm text-fluent-neutral mb-4">Nouvelle Dépense</h2>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <div class="grid grid-cols-2 gap-2">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-fluent-n2 mb-1">Date *</label>
                        <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required
                            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-fluent-n2 mb-1">Description *</label>
                        <input name="description" placeholder="Objet de la dépense" required
                            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-fluent-n2 mb-1">Fournisseur</label>
                        <input name="supplier" placeholder="Nom"
                            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-fluent-n2 mb-1">N° Dépense</label>
                        <input name="expense_number" placeholder="DEP-001"
                            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-fluent-n2 mb-1">Catégorie</label>
                        <select name="category" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                            <?php foreach ($cats as $c): ?><option><?= $c ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-fluent-n2 mb-1">Montant (MAD) *</label>
                        <input type="number" name="amount" placeholder="0.00" step="0.01" min="0" required
                            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-right font-semibold placeholder-fluent-n3">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-fluent-n2 mb-1">Mode de Paiement</label>
                        <select name="payment_method" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                            <option value="">—</option>
                            <?php foreach (['Virement','Chèque','Espèces','CB','PayPal'] as $m): ?><option><?= $m ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="has_receipt" class="w-4 h-4 text-fluent-blue rounded">
                    <span class="text-xs text-fluent-n2">Justificatif disponible</span>
                </label>
                <button type="submit" class="btn-f w-full py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk shadow-f">
                    Ajouter la Dépense
                </button>
            </form>
        </div>

        <!-- Donut chart by category -->
        <?php if ($byCat): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <h3 class="font-semibold text-sm text-fluent-neutral mb-4">Répartition par Catégorie</h3>
            <?php
            $svgSize = 120; $cx = 60; $cy = 60; $r = 44; $sw = 16;
            $circumference = 2 * M_PI * $r;
            $offset = 0; $slices = [];
            foreach ($byCat as $cat) {
                $pct   = $cat['amt'] / $totalAllExp;
                $len   = $pct * $circumference;
                $color = $catColors[$cat['category']] ?? '#a19f9d';
                $slices[] = compact('pct','len','offset','color','cat');
                $offset += $len;
            }
            ?>
            <div class="flex items-center gap-4">
                <svg width="<?= $svgSize ?>" height="<?= $svgSize ?>" viewBox="0 0 <?= $svgSize ?> <?= $svgSize ?>" style="flex-shrink:0">
                    <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="#f3f2f1" stroke-width="<?= $sw ?>"/>
                    <?php foreach ($slices as $sl): ?>
                    <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none"
                        stroke="<?= $sl['color'] ?>" stroke-width="<?= $sw ?>"
                        stroke-dasharray="<?= round($sl['len'],2) ?> <?= round($circumference,2) ?>"
                        stroke-dashoffset="<?= round(-$sl['offset'] + $circumference/4, 2) ?>"
                        stroke-linecap="round"/>
                    <?php endforeach; ?>
                    <text x="<?= $cx ?>" y="<?= $cy+5 ?>" text-anchor="middle" font-size="10" font-weight="700" fill="#323130" font-family="Segoe UI,sans-serif">
                        <?= count($byCat) ?> cat.
                    </text>
                </svg>
                <div class="flex-1 space-y-1.5 min-w-0">
                    <?php foreach ($byCat as $cat):
                        $pct = round($cat['amt']/$totalAllExp*100,0);
                        $color = $catColors[$cat['category']] ?? '#a19f9d';
                    ?>
                    <a href="?year=<?= $fy ?>&cat=<?= urlencode($cat['category']) ?>"
                        class="flex items-center gap-2 hover:bg-fluent-n7 dark:hover:bg-white/5 rounded-lg px-1 py-0.5 transition-colors group">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:<?= $color ?>"></span>
                        <span class="text-[10px] text-fluent-n2 flex-1 truncate group-hover:text-fluent-blue"><?= h($cat['category']) ?></span>
                        <span class="text-[10px] font-semibold text-fluent-n3"><?= $pct ?>%</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: List -->
    <div class="lg:col-span-2 space-y-4">
        <!-- Toolbar -->
        <div class="flex items-center gap-2 flex-wrap">
            <form class="flex-1 relative min-w-40">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fluent-n3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input name="q" value="<?= h($search) ?>" placeholder="Rechercher…" class="w-full pl-9 pr-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp placeholder-fluent-n3 shadow-f">
                <input type="hidden" name="year" value="<?= $fy ?>">
                <?php if ($cat_filter): ?><input type="hidden" name="cat" value="<?= h($cat_filter) ?>"><?php endif; ?>
            </form>
            <?php if ($cat_filter): ?>
            <a href="?year=<?= $fy ?>" class="px-3 py-2 text-xs bg-fluent-blue text-white rounded-xl font-medium flex items-center gap-1">
                <?= h($cat_filter) ?> <span>✕</span>
            </a>
            <?php endif; ?>
            <a href="?year=<?= $fy ?>&export=csv" class="btn-f px-3 py-2.5 text-xs border border-fluent-n4 dark:border-white/20 bg-white dark:bg-gray-800 text-fluent-n2 dark:text-gray-300 rounded-xl hover:bg-fluent-n6 dark:hover:bg-white/10 flex items-center gap-1.5 shadow-f">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                CSV
            </a>
            <!-- Year selector -->
            <select onchange="window.location.href='?year='+this.value" class="px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp text-fluent-n2">
                <?php for ($y=(int)date('Y')+1; $y>=2020; $y--): ?>
                <option value="<?= $y ?>" <?= $y===$fy?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <!-- Monthly mini-chart -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f px-5 py-4">
            <div class="flex items-end gap-1 h-12">
                <?php $mn=['','J','F','M','A','M','J','J','A','S','O','N','D'];
                for ($m=1;$m<=12;$m++):
                    $v=(float)$monthly[$m]; $p=$maxMon>0?$v/$maxMon:0; $isCur=$m===(int)date('n')&&$fy===(int)date('Y');
                ?>
                <div class="flex-1 flex flex-col items-center gap-0.5 group" title="<?= $mn[$m] ?>: <?= money($v) ?>">
                    <div class="w-full rounded-t transition-all <?= $isCur?'bg-fluent-red':($v>0?'bg-fluent-red/30 group-hover:bg-fluent-red/60':'bg-fluent-n5 dark:bg-white/10') ?>"
                        style="height:<?= max(3,round($p*44)) ?>px"></div>
                    <span class="text-[8px] <?= $isCur?'text-fluent-red font-bold':'text-fluent-n3' ?>"><?= $mn[$m] ?></span>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Expense list -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-fluent-n5 dark:border-white/10">
                <h3 class="font-semibold text-sm text-fluent-neutral"><?= count($expenses) ?> dépenses<?= $cat_filter ? ' · '.$cat_filter : '' ?></h3>
                <span class="text-sm font-bold text-fluent-red"><?= money($totalExp) ?></span>
            </div>
            <?php if (empty($expenses)): ?>
            <div class="px-5 py-10 text-center text-fluent-n3 text-sm">Aucune dépense enregistrée</div>
            <?php else: ?>
            <div class="divide-y divide-fluent-n6 dark:divide-white/5">
                <?php foreach ($expenses as $exp):
                    $color = $catColors[$exp['category']] ?? '#a19f9d';
                    $needsReceipt = !$exp['has_receipt'] && $exp['amount'] > 500;
                ?>
                <div class="flex items-center gap-3 px-5 py-3.5 group hover:bg-fluent-n7 dark:hover:bg-white/5 transition-colors">
                    <div class="w-8 h-8 rounded-lg flex-shrink-0 flex items-center justify-center" style="background:<?= $color ?>22">
                        <span style="color:<?= $color ?>" class="text-xs font-bold"><?= strtoupper(mb_substr($exp['category'],0,2)) ?></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-fluent-neutral truncate"><?= h($exp['description']) ?></div>
                        <div class="text-xs text-fluent-n3 flex items-center gap-1.5 mt-0.5">
                            <span><?= date('d/m/Y',strtotime($exp['expense_date'])) ?></span>
                            <?php if ($exp['supplier']): ?><span>· <?= h($exp['supplier']) ?></span><?php endif; ?>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium" style="background:<?= $color ?>22;color:<?= $color ?>"><?= h($exp['category']) ?></span>
                            <?php if ($needsReceipt): ?>
                            <span class="text-amber-500 font-semibold">⚠️ Justificatif</span>
                            <?php elseif ($exp['has_receipt']): ?>
                            <span class="text-fluent-green text-[10px]">✓ Justificatif</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="text-sm font-bold text-fluent-red">-<?= money($exp['amount']) ?></div>
                        <?php if ($exp['payment_method']): ?>
                        <div class="text-xs text-fluent-n3"><?= h($exp['payment_method']) ?></div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" class="opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $exp['id'] ?>">
                        <button type="submit" onclick="return confirmDelete()" class="btn-f p-1.5 text-fluent-n4 hover:text-fluent-red rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
