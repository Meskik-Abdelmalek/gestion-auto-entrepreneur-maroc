<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/multi_activity.php';
$pageName = 'Dashboard';
$db       = getDB();
$cfg      = getConfig();
$stats    = getDashboardStats();
$overdue  = getOverdueInvoices();
$activity = getActivityFeed(6);
$s        = $stats;
$rev      = $s['revenue'];
$fy       = (int)($s['fy'] ?? date('Y'));
$mn       = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];

// v2.1 — Multi-activity + bank accounts
$actData   = getMultiActivityDashboardData($fy);
$plafonds  = getPlafondStatus($fy);

// v2.1 — Bank accounts with balances
$bankAccts = [];
try {
    $acRows = $db->query("SELECT * FROM ae_bank_accounts WHERE is_active=1 ORDER BY sort_order,id")->fetchAll();
    foreach ($acRows as $ac) {
        $bal = $db->prepare("SELECT COALESCE(SUM(credit),0)-COALESCE(SUM(debit),0) FROM ae_bank_transactions WHERE account_id=?");
        $bal->execute([$ac['id']]);
        $ac['balance'] = (float)$ac['opening_balance'] + (float)$bal->fetchColumn();
        $bankAccts[] = $ac;
    }
} catch (\Throwable) {}
$totalBankBalance = array_sum(array_column($bankAccts, 'balance'));

$maxV   = max(array_map(fn($m) => (float)$m['paid'], $s['monthly'])) ?: 1;
$curMth = (int)date('n');

require_once __DIR__ . '/includes/header.php';

function trendBadge(array $t): string {
    if ($t['pct'] === null) return '';
    $up  = $t['dir'] === 'up';
    $cls = $up ? 'text-fluent-green bg-fluent-green-lt dark:bg-green-900/30' : 'text-fluent-red bg-fluent-red-lt dark:bg-red-900/30';
    return "<span class='text-[10px] font-semibold px-1.5 py-0.5 rounded-full {$cls}'>" . ($up?'↑':'↓') . " {$t['pct']}%</span>";
}
?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- KPI ROW                                                     -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">

    <!-- CA Total -->
    <div class="col-span-2 lg:col-span-1 bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-fluent-blue/5 to-transparent pointer-events-none"></div>
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-fluent-blue-lt dark:bg-fluent-blue/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-fluent-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-fluent-n6 dark:bg-white/10 text-fluent-n3"><?= $fy ?></span>
        </div>
        <div class="text-2xl font-bold text-fluent-neutral leading-none"><?= money($rev['total_paid']) ?></div>
        <div class="text-xs text-fluent-n3 mt-1">CA Encaissé</div>
        <div class="mt-2 flex items-center gap-2 flex-wrap">
            <?= trendBadge($s['revTrend']) ?>
            <span class="text-xs text-fluent-n3">vs mois préc.</span>
        </div>
        <div class="mt-2 flex items-center gap-3 text-xs">
            <span class="text-fluent-green font-medium">✓ <?= $rev['count_paid'] ?> payées</span>
            <span class="text-amber-500">⏳ <?= $rev['count_pending'] ?></span>
        </div>
    </div>

    <!-- Ce mois -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-fluent-blue/20 flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-fluent-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="text-xl font-bold text-fluent-neutral"><?= money($s['thisMonthRev']) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Ce mois (<?= $mn[$curMth] ?>)</div>
        <div class="mt-2"><?= trendBadge($s['revTrend']) ?></div>
    </div>

    <!-- IR dû -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center mb-3">
            <span class="text-amber-600 font-black text-sm">IR</span>
        </div>
        <div class="text-xl font-bold text-fluent-neutral"><?= money($s['ir_due']) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">IR estimé <?= $fy ?></div>
        <div class="text-[11px] text-fluent-green mt-2 font-medium">Payé : <?= money($s['ir_paid']) ?></div>
        <div class="text-[11px] text-fluent-n3">CNSS : <?= money($s['cnss_due']) ?></div>
    </div>

    <!-- Résultat net -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5 relative overflow-hidden">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center mb-3 <?= $s['net']>=0?'bg-fluent-green-lt dark:bg-green-900/30':'bg-fluent-red-lt dark:bg-red-900/30' ?>">
            <svg class="w-5 h-5 <?= $s['net']>=0?'text-fluent-green':'text-fluent-red' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        </div>
        <div class="text-xl font-bold <?= $s['net']>=0?'text-fluent-green':'text-fluent-red' ?>"><?= money($s['net']) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Résultat net</div>
        <div class="text-[11px] text-fluent-n2 mt-2">Projection : <?= money($s['projection']) ?></div>
        <div class="text-[11px] text-fluent-n3"><?= $s['unique_clients'] ?> client(s) actif(s)</div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- v2.1 BANK ACCOUNTS STRIP                                    -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if (!empty($bankAccts)): ?>
<div class="mb-4">
    <div class="flex items-center justify-between mb-2">
        <h2 class="text-xs font-semibold text-fluent-n2 uppercase tracking-wider">Comptes & Soldes</h2>
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-fluent-neutral">Total : <?= money($totalBankBalance) ?></span>
            <a href="/bank-accounts.php" class="text-xs text-fluent-blue hover:underline">Gérer →</a>
        </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-<?= min(count($bankAccts)+1, 5) ?> gap-3">
        <?php foreach ($bankAccts as $ac): ?>
        <a href="/bank.php?account=<?= $ac['id'] ?>"
            class="btn-f bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 flex items-center gap-3 hover:shadow-fm relative overflow-hidden group">
            <div class="absolute top-0 left-0 right-0 h-0.5 rounded-t-2xl" style="background:<?= h($ac['color']) ?>"></div>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-lg flex-shrink-0" style="background:<?= h($ac['color']) ?>22">
                <?= $ac['type']==='cash'?'💵':($ac['type']==='ewallet'?'📱':'🏦') ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold text-fluent-neutral truncate"><?= h($ac['name']) ?></div>
                <div class="text-sm font-bold <?= $ac['balance']>=0?'text-fluent-neutral':'text-fluent-red' ?> mt-0.5"><?= money($ac['balance']) ?></div>
            </div>
        </a>
        <?php endforeach; ?>
        <a href="/bank-accounts.php" class="btn-f bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 flex items-center gap-3 hover:shadow-fm border-2 border-dashed border-fluent-n4 dark:border-white/20 group">
            <div class="w-9 h-9 rounded-xl bg-fluent-n6 dark:bg-white/10 flex items-center justify-center text-fluent-n3 flex-shrink-0 group-hover:bg-fluent-blue-lt group-hover:text-fluent-blue transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </div>
            <div>
                <div class="text-xs font-medium text-fluent-n3 group-hover:text-fluent-blue">Ajouter un compte</div>
                <div class="text-[10px] text-fluent-n4">banque, wallet, caisse</div>
            </div>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MAIN GRID                                                    -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

    <!-- Bar Chart -->
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-sm text-fluent-neutral">CA Mensuel <?= $fy ?></h2>
            <a href="/report.php?year=<?= $fy ?>" class="btn-f text-xs text-fluent-blue hover:underline px-2 py-1 rounded-lg hover:bg-fluent-blue-lt">Rapport →</a>
        </div>
        <svg viewBox="0 0 640 160" xmlns="http://www.w3.org/2000/svg" class="w-full" style="height:140px">
            <?php
            $cW=$cH=0; $cW=640; $cH=140; $pL=0; $pR=0; $pT=12; $pB=24;
            $iW=$cW-$pL-$pR; $iH=$cH-$pT-$pB;
            $bW=$iW/12; $bG=$bW*.2; $aBW=$bW-$bG;
            for ($m=1;$m<=12;$m++):
                $val=(float)$s['monthly'][$m]['paid'];
                $pend=(float)$s['monthly'][$m]['pending'];
                $pct=$maxV>0?$val/$maxV:0;
                $pPct=$maxV>0?min($pend/$maxV,1-$pct):0;
                $x=$pL+($m-1)*$bW+$bG/2;
                $barH=max(3,$pct*$iH);
                $pBarH=max(0,$pPct*$iH);
                $y=$pT+$iH-$barH;
                $isCur=$m===$curMth;
                $fill=$isCur?'#0078d4':'#c8e4f8';
                $dFill=$isCur?'#5eb3f8':'#1a3550';
            ?>
            <?php if ($isCur): ?>
            <rect x="<?= $x-2 ?>" y="<?= $pT-4 ?>" width="<?= $aBW+4 ?>" height="<?= $iH+4 ?>" rx="6" fill="#eff6fc" opacity=".6"/>
            <?php endif; ?>
            <?php if ($pBarH > 0): ?>
            <rect x="<?= $x ?>" y="<?= $y-$pBarH ?>" width="<?= $aBW ?>" height="<?= $pBarH ?>" rx="3" fill="#fff4ce"/>
            <?php endif; ?>
            <rect x="<?= $x ?>" y="<?= $y ?>" width="<?= $aBW ?>" height="<?= $barH ?>" rx="3"
                fill="<?= $fill ?>"
                class="dark:fill-[<?= $dFill ?>]"/>
            <?php if ($val > 0): ?>
            <text x="<?= $x+$aBW/2 ?>" y="<?= $y-3 ?>" text-anchor="middle" font-size="7" fill="#605e5c"><?= number_format($val/1000,1) ?>k</text>
            <?php endif; ?>
            <text x="<?= $x+$aBW/2 ?>" y="<?= $pT+$iH+14 ?>" text-anchor="middle" font-size="8"
                fill="<?= $isCur?'#0078d4':'#a19f9d' ?>" font-weight="<?= $isCur?'700':'400' ?>">
                <?= $mn[$m] ?>
            </text>
            <?php endfor; ?>
        </svg>
        <div class="flex items-center gap-4 mt-2 text-xs text-fluent-n3">
            <div class="flex items-center gap-1.5"><div class="w-3 h-2 rounded bg-fluent-blue"></div> Encaissé</div>
            <div class="flex items-center gap-1.5"><div class="w-3 h-2 rounded bg-amber-200"></div> En attente</div>
        </div>
    </div>

    <!-- Plafond + quick stats -->
    <div class="space-y-3">
        <!-- Plafond bars -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <h3 class="font-semibold text-sm text-fluent-neutral mb-3">Plafonds AE</h3>
            <?php foreach ([['Service',$plafonds['Service'],'#0078d4'],['Commerce',$plafonds['Commerce'],'#107c10']] as [$cat,$pl,$color]):
                $alertCls = $pl['alert']==='red'?'text-fluent-red':($pl['alert']==='orange'?'text-amber-600':($pl['alert']==='yellow'?'text-amber-500':'text-fluent-n3'));
                $barColor = $pl['alert']==='red'?'#d13438':($pl['alert']==='orange'?'#f7630c':$color);
                $pct = min(100, round($pl['pct']*100));
            ?>
            <div class="mb-4 last:mb-0">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-medium text-fluent-n2"><?= $cat ?></span>
                    <span class="text-xs font-bold <?= $alertCls ?>"><?= $pct ?>%</span>
                </div>
                <div class="h-2 bg-fluent-n5 dark:bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-700" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
                </div>
                <div class="flex justify-between mt-1 text-[10px] text-fluent-n3">
                    <span><?= money($pl['paid']) ?></span>
                    <span><?= money($pl['remaining']) ?> restant</span>
                </div>
                <?php if ($pl['alert'] !== 'normal'): ?>
                <div class="mt-1.5 text-[10px] px-2 py-1 rounded-lg font-medium
                    <?= $pl['alert']==='red'?'bg-fluent-red-lt text-fluent-red':($pl['alert']==='orange'?'bg-amber-50 text-amber-700':'bg-amber-50 text-amber-600') ?>">
                    <?= $pl['alert']==='red'?'🚨 Plafond critique !':($pl['alert']==='orange'?'⚠️ Approche du plafond':'⚡ Surveiller') ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Devis stats -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-sm text-fluent-neutral">Devis <?= $fy ?></h3>
                <a href="/quotes.php" class="text-xs text-fluent-blue hover:underline">Voir →</a>
            </div>
            <?php
            $qs = $s['quoteSt'];
            $qItems = [['Brouillons','draft',$qs['draft']??0,'#a19f9d'],['Envoyés','sent',$qs['sent']??0,'#0078d4'],['Acceptés','accepted',$qs['accepted']??0,'#107c10']];
            ?>
            <div class="space-y-2">
                <?php foreach ($qItems as [$label,$cls,$count,$color]): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full" style="background:<?= $color ?>"></div>
                        <span class="text-xs text-fluent-n2"><?= $label ?></span>
                    </div>
                    <span class="text-xs font-bold text-fluent-neutral"><?= $count ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (($qs['accepted_val']??0) > 0): ?>
                <div class="pt-2 mt-1 border-t border-fluent-n5 dark:border-white/10 flex items-center justify-between">
                    <span class="text-xs text-fluent-n3">Valeur acceptée</span>
                    <span class="text-xs font-bold text-fluent-green"><?= money($qs['accepted_val']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- v2.1 — MULTI-ACTIVITY BREAKDOWN                             -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if (!empty($actData['activities'])): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-sm text-fluent-neutral flex items-center gap-2">
                <span class="w-6 h-6 bg-fluent-purple rounded-lg flex items-center justify-center text-white text-[10px] font-bold">AE</span>
                Revenus par Activité
            </h2>
            <span class="text-[10px] font-bold text-white bg-fluent-purple px-2 py-0.5 rounded">v2.1</span>
        </div>
        <div class="space-y-3">
            <?php foreach ($actData['activities'] as $act):
                $total = $act['paid'] + $act['pending'];
                $paidPct = $total > 0 ? round($act['paid']/$total*100) : 0;
            ?>
            <div class="p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <div class="text-xs font-semibold text-fluent-neutral"><?= h($act['label']) ?></div>
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-medium bg-fluent-blue-lt text-fluent-blue"><?= $act['category'] ?></span>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold text-fluent-neutral"><?= money($act['paid']) ?></div>
                        <div class="text-[10px] text-fluent-n3">IR : <?= money($act['ir']) ?></div>
                    </div>
                </div>
                <?php if ($act['pending'] > 0): ?>
                <div class="h-1.5 bg-fluent-n5 dark:bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full bg-fluent-blue rounded-full" style="width:<?= $paidPct ?>%"></div>
                </div>
                <div class="flex justify-between mt-1 text-[10px] text-fluent-n3">
                    <span>Payé : <?= money($act['paid']) ?></span>
                    <span>Att. : <?= money($act['pending']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-3 pt-3 border-t border-fluent-n5 dark:border-white/10 flex justify-between items-center">
            <span class="text-xs text-fluent-n3">IR Total estimé</span>
            <span class="font-bold text-sm text-fluent-neutral"><?= money($actData['total_ir']) ?></span>
        </div>
    </div>

    <!-- Overdue invoices -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-sm text-fluent-neutral flex items-center gap-2">
                <?php if ($overdue): ?>
                <span class="w-5 h-5 rounded-full bg-fluent-red flex items-center justify-center text-[10px] font-bold text-white"><?= count($overdue) ?></span>
                <?php endif; ?>
                Impayés en retard
            </h2>
            <?php if ($overdue): ?>
            <a href="/reminders.php" class="text-xs text-fluent-red hover:underline font-medium">Relancer →</a>
            <?php endif; ?>
        </div>
        <?php if (empty($overdue)): ?>
        <div class="py-8 text-center">
            <div class="text-4xl mb-2">✅</div>
            <div class="text-sm font-medium text-fluent-neutral">Aucun impayé !</div>
            <div class="text-xs text-fluent-n3 mt-1">Toutes vos factures sont à jour</div>
        </div>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach (array_slice($overdue,0,5) as $ov):
                $days = (int)(date_diff(date_create($ov['invoice_date']),date_create())->days);
            ?>
            <div class="flex items-center gap-3 p-2.5 bg-red-50 dark:bg-red-900/10 rounded-xl">
                <div class="w-8 h-8 rounded-lg bg-fluent-red-lt flex items-center justify-center flex-shrink-0">
                    <span class="text-fluent-red text-xs font-bold"><?= $days ?>j</span>
                </div>
                <div class="flex-1 min-w-0">
                    <a href="/invoice-view.php?id=<?= $ov['id'] ?>" class="text-xs font-semibold text-fluent-neutral hover:text-fluent-blue truncate block"><?= h($ov['client_name']) ?></a>
                    <div class="text-[10px] text-fluent-n3"><?= h($ov['invoice_number']) ?></div>
                </div>
                <div class="text-xs font-bold text-fluent-red flex-shrink-0"><?= money($ov['amount_ttc']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- ACTIVITY FEED + QUICK ACTIONS                               -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Activity feed -->
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <h2 class="font-semibold text-sm text-fluent-neutral mb-4">Activité récente</h2>
        <div class="space-y-0.5">
            <?php if (empty($activity)): ?>
            <div class="py-8 text-center text-xs text-fluent-n3">Aucune activité récente</div>
            <?php else: foreach ($activity as $ev):
                $icons = ['invoice_paid'=>'✅','invoice_created'=>'📄','quote_created'=>'📋','expense_added'=>'💸','client_added'=>'👤'];
                $ico   = $icons[$ev['type']] ?? '•';
            ?>
            <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-fluent-n7 dark:hover:bg-white/5 transition-colors">
                <span class="text-base flex-shrink-0 w-6 text-center"><?= $ico ?></span>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-medium text-fluent-neutral truncate"><?= h($ev['title']) ?></div>
                    <div class="text-[10px] text-fluent-n3"><?= h($ev['subtitle']??'') ?></div>
                </div>
                <div class="text-[10px] text-fluent-n4 flex-shrink-0"><?= h($ev['date']??'') ?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Quick actions v2.1 -->
    <div class="space-y-3">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <h3 class="font-semibold text-sm text-fluent-neutral mb-3">Actions rapides</h3>
            <div class="space-y-2">
                <?php
                $quickActions = [
                    ['/invoice-new.php',  '📄', 'Nouvelle facture',   'bg-fluent-blue text-white hover:bg-fluent-blue-dk', true],
                    ['/quote-new.php',    '📋', 'Nouveau devis',      'bg-white dark:bg-gray-700 border border-fluent-n4 dark:border-white/20 text-fluent-n2 hover:bg-fluent-n6', false],
                    ['/bank-import.php',  '📥', 'Importer relevé',   'bg-white dark:bg-gray-700 border border-fluent-n4 dark:border-white/20 text-fluent-n2 hover:bg-fluent-n6', false],
                    ['/bank-accounts.php','🏦', 'Comptes bancaires', 'bg-white dark:bg-gray-700 border border-fluent-n4 dark:border-white/20 text-fluent-n2 hover:bg-fluent-n6', false],
                    ['/declarations.php', '📊', 'Déclarations IR',   'bg-white dark:bg-gray-700 border border-fluent-n4 dark:border-white/20 text-fluent-n2 hover:bg-fluent-n6', false],
                    ['/settings.php',     '⚙️', 'Paramètres v2.1',  'bg-white dark:bg-gray-700 border border-fluent-n4 dark:border-white/20 text-fluent-n2 hover:bg-fluent-n6', false],
                ];
                foreach ($quickActions as [$url,$ico,$lbl,$cls,$primary]):
                ?>
                <a href="<?= $url ?>" class="btn-f flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium <?= $cls ?> shadow-f">
                    <span class="text-base w-5 text-center"><?= $ico ?></span>
                    <span><?= $lbl ?></span>
                    <?php if ($primary): ?><span class="ml-auto text-xs opacity-70">→</span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- v2.1 badge -->
        <div class="bg-gradient-to-br from-fluent-blue to-blue-700 rounded-2xl p-4 text-white">
            <div class="text-xs font-bold mb-1 opacity-80">🆕 Moroccan AE v2.1</div>
            <div class="text-sm font-semibold mb-2">Nouvelles fonctionnalités actives</div>
            <ul class="space-y-1 text-xs opacity-90">
                <li>🖼️ Logo sur factures/devis</li>
                <li>📧 Envoi email SMTP</li>
                <li>🏦 Multi-comptes bancaires</li>
                <li>📥 Import CSV 5 banques</li>
                <li>📄 PDF serveur (Dompdf)</li>
            </ul>
            <a href="/settings.php#logo" class="mt-3 inline-block text-xs bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg">Configurer →</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
