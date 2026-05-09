<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Dashboard';
$stats    = getDashboardStats();
$cfg      = getConfig();
$overdue  = getOverdueInvoices();
$activity = getActivityFeed(6);
$s        = $stats;
$rev      = $s['revenue'];
$mn       = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
require_once __DIR__ . '/includes/header.php';

// SVG chart data
$maxV = max(array_map(fn($m) => (float)$m['paid'], $s['monthly'])) ?: 1;

function trendBadge(array $t): string {
    if ($t['pct'] === null) return '';
    $cls   = $t['dir']==='up' ? 'text-fluent-green bg-fluent-green-lt' : 'text-fluent-red bg-fluent-red-lt';
    $arrow = $t['dir']==='up' ? '↑' : '↓';
    return "<span class='text-[10px] font-semibold px-1.5 py-0.5 rounded-full $cls'>$arrow {$t['pct']}%</span>";
}
?>

<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">

    <!-- CA Total -->
    <div class="col-span-2 lg:col-span-1 bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-fluent-blue-lt dark:bg-fluent-blue/20 flex items-center justify-center text-fluent-blue font-bold text-sm">₵</div>
            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-fluent-n6 dark:bg-white/10 text-fluent-n3"><?= $s['fy'] ?></span>
        </div>
        <div class="text-2xl font-bold text-fluent-neutral leading-none"><?= money($rev['total_paid']) ?></div>
        <div class="text-xs text-fluent-n3 mt-1">CA Encaissé total</div>
        <div class="mt-2 flex items-center gap-2 text-xs">
            <?= trendBadge($s['revTrend']) ?>
            <span class="text-fluent-n3">vs mois préc.</span>
        </div>
        <div class="mt-2 flex items-center gap-3 text-xs text-fluent-n2">
            <span class="text-fluent-green font-medium">✓ <?= $rev['count_paid'] ?> payées</span>
            <span class="text-amber-500">⏳ <?= $rev['count_pending'] ?> att.</span>
        </div>
    </div>

    <!-- Ce mois -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-fluent-blue/20 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5 text-fluent-blue w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="text-xl font-bold text-fluent-neutral"><?= money($s['thisMonthRev']) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Ce mois</div>
        <div class="mt-2"><?= trendBadge($s['revTrend']) ?></div>
    </div>

    <!-- IR + CNSS -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center mb-3">
            <span class="text-amber-600 font-bold text-sm">IR</span>
        </div>
        <div class="text-xl font-bold text-fluent-neutral"><?= money($s['ir_due']) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">IR dû (estimé)</div>
        <div class="text-xs text-fluent-green mt-2 font-medium">✓ Payé: <?= money($s['ir_paid']) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">CNSS: <?= money($s['cnss_due']) ?></div>
    </div>

    <!-- Net -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <div class="w-9 h-9 rounded-xl <?= $s['net']>=0?'bg-fluent-green-lt dark:bg-green-900/30':'bg-fluent-red-lt dark:bg-red-900/30' ?> flex items-center justify-center mb-3">
            <svg class="w-5 h-5 <?= $s['net']>=0?'text-fluent-green':'text-fluent-red' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        </div>
        <div class="text-xl font-bold <?= $s['net']>=0?'text-fluent-green':'text-fluent-red' ?>"><?= money($s['net']) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Résultat net</div>
        <div class="text-xs text-fluent-n2 mt-2">Projection: <?= money($s['projection']) ?></div>
        <div class="text-xs text-fluent-n3"><?= $s['unique_clients'] ?> client(s) actif(s)</div>
    </div>
</div>

<!-- Main grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

    <!-- SVG Bar Chart -->
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-sm text-fluent-neutral">CA Mensuel <?= $s['fy'] ?></h2>
            <a href="/report.php?year=<?= $s['fy'] ?>" class="text-xs text-fluent-blue hover:underline">Rapport complet →</a>
        </div>
        <!-- SVG chart -->
        <svg viewBox="0 0 640 160" xmlns="http://www.w3.org/2000/svg" class="w-full" style="height:140px">
            <?php
            $chartW = 640; $chartH = 140; $padL = 0; $padR = 0; $padT = 12; $padB = 24;
            $innerW = $chartW - $padL - $padR;
            $innerH = $chartH - $padT - $padB;
            $barW   = $innerW / 12;
            $barGap = $barW * 0.22;
            $actualBarW = $barW - $barGap;
            $curM   = (int)date('n');

            for ($m = 1; $m <= 12; $m++):
                $val  = (float)$s['monthly'][$m]['paid'];
                $pend = (float)$s['monthly'][$m]['pending'];
                $pct  = $maxV > 0 ? $val / $maxV : 0;
                $pPct = $maxV > 0 ? $pend / $maxV : 0;
                $x    = $padL + ($m - 1) * $barW + $barGap / 2;
                $barH = max(3, $pct * $innerH);
                $y    = $padT + $innerH - $barH;
                $isCur = $m === $curM;
                $fill  = $isCur ? '#0078d4' : '#bfdbfe';
                if ($val === 0.0 && $pend === 0.0) $fill = '#edebe9';
                ?>
                <g class="group">
                    <rect x="<?= round($x,1) ?>" y="<?= round($padT,1) ?>" width="<?= round($actualBarW,1) ?>" height="<?= round($innerH,1) ?>" rx="4" fill="transparent"/>
                    <?php if ($pend > 0): ?>
                    <rect x="<?= round($x,1) ?>" y="<?= round($padT + $innerH - max(3,$pPct*$innerH),1) ?>" width="<?= round($actualBarW,1) ?>" height="<?= round(max(3,$pPct*$innerH),1) ?>" rx="3" fill="#fde68a" opacity="0.7"/>
                    <?php endif; ?>
                    <rect x="<?= round($x,1) ?>" y="<?= round($y,1) ?>" width="<?= round($actualBarW,1) ?>" height="<?= round($barH,1) ?>" rx="3" fill="<?= $fill ?>"/>
                    <?php if ($val > 0): ?>
                    <text x="<?= round($x + $actualBarW/2, 1) ?>" y="<?= round($y - 3, 1) ?>" text-anchor="middle" font-size="7" fill="#a19f9d" font-family="Segoe UI,sans-serif">
                        <?= number_format($val/1000,0) ?>k
                    </text>
                    <?php endif; ?>
                    <text x="<?= round($x + $actualBarW/2, 1) ?>" y="<?= $chartH - 6 ?>" text-anchor="middle" font-size="8"
                        fill="<?= $isCur ? '#0078d4' : '#a19f9d' ?>"
                        font-weight="<?= $isCur ? '700' : '400' ?>"
                        font-family="Segoe UI,sans-serif">
                        <?= $mn[$m] ?>
                    </text>
                </g>
            <?php endfor; ?>
        </svg>

        <div class="flex items-center gap-4 mt-2 text-xs text-fluent-n3">
            <span class="flex items-center gap-1"><span class="w-3 h-2 rounded bg-fluent-blue inline-block"></span>Encaissé</span>
            <span class="flex items-center gap-1"><span class="w-3 h-2 rounded bg-amber-200 inline-block"></span>En attente</span>
            <span class="ml-auto font-medium text-fluent-n2">Moy: <?= money($rev['total_paid']/12) ?>/mois</span>
        </div>
    </div>

    <!-- Ceiling gauges -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <h2 class="font-semibold text-sm text-fluent-neutral mb-4">Plafonds AE <?= $s['fy'] ?></h2>

        <?php
        $gauges = [
            ['Services','services_paid',$s['ceiling_svc'],$s['svc_pct'],'#0078d4'],
            ['Commerce','commerce_paid',$s['ceiling_com'],$s['com_pct'],'#107c10'],
        ];
        foreach ($gauges as [$label,$key,$ceil,$pct,$color]):
            $pctR = round($pct * 100, 1);
            $barColor = $pct>=0.95?'#a4262c':($pct>=0.75?'#ca5010':$color);
            $remaining = $ceil - $rev[$key];
            $svgPct = min(100, $pctR);
        ?>
        <div class="mb-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-fluent-n2"><?= $label ?></span>
                <span class="text-xs font-bold" style="color:<?= $barColor ?>"><?= $pctR ?>%</span>
            </div>
            <!-- SVG arc gauge -->
            <?php
            $cx=60; $cy=54; $r=44; $sw=10;
            $circumference = 2 * M_PI * $r;
            $dashArray = $circumference;
            $dashOffset = $circumference * (1 - $svgPct / 100);
            $startAngle = -90; // top
            ?>
            <div class="flex items-center gap-4">
                <svg width="80" height="80" viewBox="0 0 120 80">
                    <!-- track -->
                    <circle cx="60" cy="60" r="<?= $r ?>" fill="none" stroke="#edebe9" stroke-width="<?= $sw ?>"
                        stroke-dasharray="<?= round($circumference/2,1) ?> <?= round($circumference/2,1) ?>"
                        stroke-dashoffset="<?= round(-$circumference/4,1) ?>" transform="rotate(-180 60 60)"/>
                    <!-- fill -->
                    <?php if ($svgPct > 0): ?>
                    <circle cx="60" cy="60" r="<?= $r ?>" fill="none"
                        stroke="<?= $barColor ?>" stroke-width="<?= $sw ?>" stroke-linecap="round"
                        stroke-dasharray="<?= round($svgPct/100 * $circumference/2, 1) ?> <?= round($circumference,1) ?>"
                        stroke-dashoffset="<?= round(-$circumference/4,1) ?>" transform="rotate(-180 60 60)"/>
                    <?php endif; ?>
                    <text x="60" y="58" text-anchor="middle" font-size="13" font-weight="700" fill="<?= $barColor ?>" font-family="Segoe UI,sans-serif"><?= $pctR ?>%</text>
                </svg>
                <div class="flex-1 min-w-0">
                    <div class="text-xs text-fluent-n2 font-medium"><?= money($rev[$key]) ?></div>
                    <div class="text-[10px] text-fluent-n3">sur <?= money($ceil) ?></div>
                    <div class="text-[10px] mt-1 font-semibold <?= $pct>=0.95?'text-fluent-red':($pct>=0.75?'text-amber-500':'text-fluent-green') ?>">
                        <?= money(max(0,$remaining)) ?> restant
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Next declaration -->
        <div class="pt-3 border-t border-fluent-n5 dark:border-white/10">
            <div class="text-xs text-fluent-n3 mb-0.5">Prochaine déclaration IR</div>
            <div class="text-sm font-bold text-fluent-blue"><?= nextDeclarationDeadline() ?></div>
            <?php $days = daysUntilDeclaration(); ?>
            <div class="text-xs <?= $days<=15?'text-amber-500 font-semibold':'text-fluent-n3' ?> mt-0.5">
                <?= $days ?> jours restants
            </div>
            <a href="/declarations.php" class="mt-2 inline-block text-xs text-fluent-blue hover:underline">Gérer →</a>
        </div>
    </div>
</div>

<!-- Bottom row: recent invoices + activity + overdue -->
<div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

    <!-- Recent invoices -->
    <div class="lg:col-span-3 bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-fluent-n5 dark:border-white/10">
            <h2 class="font-semibold text-sm text-fluent-neutral">Dernières Factures</h2>
            <a href="/invoices.php" class="text-xs text-fluent-blue hover:underline">Voir tout →</a>
        </div>
        <?php
        $recent = getDB()->query("SELECT * FROM ae_invoices ORDER BY created_at DESC LIMIT 7")->fetchAll();
        if (empty($recent)): ?>
        <div class="px-5 py-10 text-center">
            <div class="text-4xl mb-2">📄</div>
            <div class="text-sm text-fluent-n3 mb-3">Aucune facture pour l'instant</div>
            <a href="/invoice-new.php" class="text-xs text-fluent-blue font-medium hover:underline">Créer votre première facture →</a>
        </div>
        <?php else: ?>
        <div class="divide-y divide-fluent-n6 dark:divide-white/5">
            <?php foreach ($recent as $inv): ?>
            <a href="/invoice-view.php?id=<?= $inv['id'] ?>"
                class="flex items-center gap-3 px-5 py-3 hover:bg-fluent-n7 dark:hover:bg-white/5 transition-colors">
                <div class="w-8 h-8 rounded-xl flex-shrink-0 flex items-center justify-center text-xs font-bold
                    <?= $inv['status']==='Payé' ? 'bg-fluent-green-lt text-fluent-green' : ($inv['status']==='En attente' ? 'bg-amber-50 text-amber-600' : 'bg-fluent-red-lt text-fluent-red') ?>">
                    <?= $inv['status']==='Payé' ? '✓' : ($inv['status']==='Annulé' ? '✕' : '…') ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-fluent-neutral truncate"><?= h($inv['client_name']) ?></div>
                    <div class="text-xs text-fluent-n3"><?= h($inv['invoice_number']) ?> · <?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="text-sm font-semibold text-fluent-neutral"><?= money($inv['amount_ttc']) ?></div>
                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full
                        <?= $inv['status']==='Payé' ? 'badge-paid' : ($inv['status']==='En attente' ? 'badge-pending' : 'badge-cancelled') ?>">
                        <?= h($inv['status']) ?>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right column: overdue + activity -->
    <div class="lg:col-span-2 space-y-4">

        <!-- Overdue -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-fluent-n5 dark:border-white/10">
                <h2 class="font-semibold text-sm text-fluent-neutral">Relances Urgentes</h2>
                <?php if (count($overdue)): ?>
                <span class="text-xs bg-fluent-red text-white px-2 py-0.5 rounded-full font-bold"><?= count($overdue) ?></span>
                <?php endif; ?>
            </div>
            <?php if (empty($overdue)): ?>
            <div class="px-5 py-6 text-center">
                <div class="text-2xl mb-1">✅</div>
                <div class="text-xs text-fluent-n3">Aucun impayé en retard</div>
            </div>
            <?php else: ?>
            <div class="divide-y divide-fluent-n6 dark:divide-white/5 max-h-56 overflow-y-auto">
                <?php foreach (array_slice($overdue, 0, 5) as $inv):
                    $d = (int)$inv['days_overdue'];
                    $cls = $d>60?'bg-fluent-red-lt text-fluent-red':($d>30?'bg-fluent-orange-lt text-fluent-orange':($d>15?'bg-amber-50 text-amber-600':'bg-fluent-green-lt text-fluent-green'));
                ?>
                <a href="/invoice-view.php?id=<?= $inv['id'] ?>" class="flex items-center gap-3 px-4 py-2.5 hover:bg-fluent-n7 dark:hover:bg-white/5 transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold text-fluent-neutral truncate"><?= h($inv['client_name']) ?></div>
                        <div class="text-[10px] text-fluent-n3"><?= h($inv['invoice_number']) ?></div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="text-xs font-semibold text-fluent-neutral"><?= money($inv['amount_ttc']) ?></div>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold <?= $cls ?>"><?= $d ?>j</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="px-5 py-2 border-t border-fluent-n5 dark:border-white/10">
                <a href="/reminders.php" class="text-xs text-fluent-blue hover:underline">Toutes les relances →</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Activity feed -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
            <div class="px-5 py-3.5 border-b border-fluent-n5 dark:border-white/10">
                <h2 class="font-semibold text-sm text-fluent-neutral">Activité Récente</h2>
            </div>
            <?php if (empty($activity)): ?>
            <div class="px-5 py-6 text-center text-xs text-fluent-n3">Aucune activité</div>
            <?php else: ?>
            <div class="divide-y divide-fluent-n6 dark:divide-white/5">
                <?php foreach ($activity as $a): ?>
                <div class="flex items-center gap-3 px-4 py-2.5">
                    <span class="text-base leading-none flex-shrink-0"><?= $a['t']==='invoice'?'📄':'💸' ?></span>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-medium text-fluent-neutral truncate">
                            <?= $a['t']==='invoice' ? h($a['client_name']) : h($a['client_name']) ?>
                        </div>
                        <div class="text-[10px] text-fluent-n3"><?= h($a['ref']) ?></div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="text-xs font-semibold <?= $a['t']==='expense'?'text-fluent-red':'text-fluent-neutral' ?>">
                            <?= $a['t']==='expense'?'-':'' ?><?= money($a['amount_ttc']) ?>
                        </div>
                        <div class="text-[10px] text-fluent-n3"><?= timeAgo($a['created_at']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
