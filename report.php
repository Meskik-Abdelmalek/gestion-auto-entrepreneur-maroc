<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Rapport Annuel';
$db = getDB(); $cfg = getConfig();
$fy = (int)($_GET['year'] ?? $cfg['fiscal_year'] ?? date('Y'));

$mn = ['','Janv','Févr','Mars','Avr','Mai','Juin','Juil','Août','Sept','Oct','Nov','Déc'];
$monthly = [];
for ($m=1;$m<=12;$m++) {
    $st=$db->prepare("SELECT COALESCE(SUM(CASE WHEN status='Payé' THEN amount_ttc ELSE 0 END),0) paid, COALESCE(SUM(CASE WHEN status='En attente' THEN amount_ttc ELSE 0 END),0) pend, COALESCE(SUM(CASE WHEN status='Payé' AND category='Service' THEN amount_ttc ELSE 0 END),0) svc, COALESCE(SUM(CASE WHEN status='Payé' AND category='Commerce' THEN amount_ttc ELSE 0 END),0) com, COUNT(CASE WHEN status='Payé' THEN 1 END) cnt FROM ae_invoices WHERE fiscal_year=? AND MONTH(invoice_date)=?");
    $st->execute([$fy,$m]); $monthly[$m]=$st->fetch();
}

$annPaid=array_sum(array_column($monthly,'paid'));
$annSvc=array_sum(array_column($monthly,'svc'));
$annCom=array_sum(array_column($monthly,'com'));
$annPend=array_sum(array_column($monthly,'pend'));
$annIR=$annSvc*($cfg['ir_rate_services']??0.01)+$annCom*($cfg['ir_rate_commerce']??0.005);
$expSt=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM ae_expenses WHERE fiscal_year=?");$expSt->execute([$fy]);$totalExp=(float)$expSt->fetchColumn();
$net=$annPaid-$annIR-$totalExp;

$quarters=['Q1'=>[1,2,3],'Q2'=>[4,5,6],'Q3'=>[7,8,9],'Q4'=>[10,11,12]];
$qData=[];
foreach ($quarters as $q=>$months){$qP=$qS=$qC=0;foreach($months as $m){$qP+=$monthly[$m]['paid'];$qS+=$monthly[$m]['svc'];$qC+=$monthly[$m]['com'];}$qIR=$qS*($cfg['ir_rate_services']??0.01)+$qC*($cfg['ir_rate_commerce']??0.005);$qData[$q]=['paid'=>$qP,'svc'=>$qS,'com'=>$qC,'ir'=>$qIR];}

$topCl=$db->prepare("SELECT client_name,COUNT(*) inv,SUM(amount_ttc) total,MAX(invoice_date) last FROM ae_invoices WHERE status='Payé' AND fiscal_year=? GROUP BY client_name ORDER BY total DESC LIMIT 8");$topCl->execute([$fy]);$topClients=$topCl->fetchAll();

$maxV=max(array_map(fn($m)=>(float)$m['paid'],$monthly))?:1;
require_once __DIR__ . '/includes/header.php';
?>
<!-- Controls -->
<div class="flex items-center justify-between mb-5">
    <div class="flex items-center gap-2 overflow-x-auto">
        <?php for ($y=(int)date('Y')+1;$y>=2020;$y--): ?>
        <a href="?year=<?= $y ?>" class="btn-f flex-shrink-0 px-4 py-2 text-sm rounded-xl border transition-colors
            <?= $y===$fy?'bg-fluent-blue text-white border-fluent-blue font-semibold':'bg-white dark:bg-gray-800 border-fluent-n4 dark:border-white/20 text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6 dark:hover:bg-white/10' ?>">
            <?= $y ?>
        </a>
        <?php endfor; ?>
    </div>
    <button onclick="window.print()" class="btn-f flex items-center gap-2 px-4 py-2 text-sm bg-white dark:bg-gray-800 border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 dark:hover:bg-white/10 shadow-f">
        🖨️ Imprimer
    </button>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
    <?php foreach ([['CA Encaissé',money($annPaid),'text-fluent-blue'],['Services',money($annSvc),'text-fluent-neutral'],['Commerce',money($annCom),'text-fluent-neutral'],['IR Estimé',money($annIR),'text-amber-600'],['Résultat Net',money($net),$net>=0?'text-fluent-green':'text-fluent-red']] as [$l,$v,$c]): ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-lg font-bold <?= $c ?> leading-tight"><?= $v ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5"><?= $l ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
    <!-- Full SVG bar chart -->
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <h2 class="font-semibold text-sm text-fluent-neutral mb-4">CA Mensuel <?= $fy ?></h2>
        <svg viewBox="0 0 720 180" xmlns="http://www.w3.org/2000/svg" class="w-full" style="height:160px">
            <?php
            $W=720;$H=180;$pL=40;$pB=28;$pT=16;
            $iW=$W-$pL; $iH=$H-$pB-$pT;
            $bW=$iW/12; $gap=$bW*.2; $abW=$bW-$gap;
            // Y-axis labels
            for ($i=0;$i<=4;$i++):
                $yV=$maxV*$i/4; $y=$pT+$iH-($i/4*$iH);
                ?>
            <line x1="<?= $pL ?>" y1="<?= round($y) ?>" x2="<?= $W ?>" y2="<?= round($y) ?>" stroke="#edebe9" stroke-width="0.5"/>
            <text x="<?= $pL-4 ?>" y="<?= round($y+4) ?>" text-anchor="end" font-size="8" fill="#a19f9d" font-family="Segoe UI,sans-serif"><?= number_format($yV/1000,0) ?>k</text>
            <?php endfor; ?>
            <?php for ($m=1;$m<=12;$m++):
                $v=(float)$monthly[$m]['paid']; $pend=(float)$monthly[$m]['pend'];
                $pct=$maxV>0?$v/$maxV:0; $ppct=$maxV>0?$pend/$maxV:0;
                $x=$pL+($m-1)*$bW+$gap/2;
                $bH=max(3,$pct*$iH); $y=$pT+$iH-$bH;
                $isCur=$m===(int)date('n')&&$fy===(int)date('Y');
                $fill=$isCur?'#0078d4':($v>0?'#bfdbfe':'#edebe9');
            ?>
            <g>
                <?php if ($pend>0): $pH=max(3,$ppct*$iH); $py=$pT+$iH-$pH; ?>
                <rect x="<?= round($x,1) ?>" y="<?= round($py,1) ?>" width="<?= round($abW,1) ?>" height="<?= round($pH,1) ?>" rx="3" fill="#fde68a" opacity=".7"/>
                <?php endif; ?>
                <rect x="<?= round($x,1) ?>" y="<?= round($y,1) ?>" width="<?= round($abW,1) ?>" height="<?= round($bH,1) ?>" rx="3" fill="<?= $fill ?>"/>
                <?php if ($v>0): ?>
                <text x="<?= round($x+$abW/2,1) ?>" y="<?= round($y-3,1) ?>" text-anchor="middle" font-size="7" fill="#a19f9d" font-family="Segoe UI,sans-serif"><?= number_format($v/1000,1) ?>k</text>
                <?php endif; ?>
                <text x="<?= round($x+$abW/2,1) ?>" y="<?= $H-8 ?>" text-anchor="middle" font-size="8"
                    fill="<?= $isCur?'#0078d4':'#a19f9d' ?>" font-weight="<?= $isCur?'700':'400' ?>" font-family="Segoe UI,sans-serif">
                    <?= substr($mn[$m],0,3) ?>
                </text>
            </g>
            <?php endfor; ?>
        </svg>
        <div class="flex items-center gap-4 mt-1 text-xs text-fluent-n3">
            <span class="flex items-center gap-1"><span class="w-3 h-2 rounded bg-fluent-blue inline-block"></span>Encaissé</span>
            <span class="flex items-center gap-1"><span class="w-3 h-2 rounded bg-amber-200 inline-block"></span>En attente</span>
        </div>
    </div>

    <!-- Quarterly summary -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <h2 class="font-semibold text-sm text-fluent-neutral mb-4">Par Trimestre</h2>
        <div class="space-y-3">
            <?php foreach ($qData as $q=>$qd):
                $pct=$annPaid>0?round($qd['paid']/$annPaid*100):0;
            ?>
            <div class="p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-bold text-fluent-neutral"><?= $q ?>-<?= $fy ?></span>
                    <span class="text-sm font-bold text-fluent-blue"><?= money($qd['paid']) ?></span>
                </div>
                <div class="h-1.5 bg-fluent-n5 dark:bg-white/10 rounded-full overflow-hidden mb-1">
                    <div class="h-full bg-fluent-blue rounded-full" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="flex justify-between text-[10px] text-fluent-n3">
                    <span>IR: <span class="text-amber-500 font-medium"><?= money($qd['ir']) ?></span></span>
                    <span><?= $pct ?>% du CA</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Monthly table -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-fluent-n5 dark:border-white/10">
        <h2 class="font-semibold text-sm text-fluent-neutral">Tableau Mensuel Détaillé</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-fluent-n7 dark:bg-white/5 border-b border-fluent-n5 dark:border-white/10">
                    <?php foreach (['MOIS','CA SERVICES','CA COMMERCE','TOTAL ENCAISSÉ','EN ATTENTE','IR DÛ','FACTURES'] as $h): ?>
                    <th class="<?= $h==='MOIS'?'text-left px-5':'text-right px-4' ?> py-3 text-xs font-semibold text-fluent-n3"><?= $h ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-fluent-n6 dark:divide-white/5">
                <?php for ($m=1;$m<=12;$m++):
                    $r=$monthly[$m]; $ir=$r['svc']*($cfg['ir_rate_services']??0.01)+$r['com']*($cfg['ir_rate_commerce']??0.005);
                    $isCur=$m===(int)date('n')&&$fy===(int)date('Y');
                ?>
                <tr class="<?= $r['paid']>0?'hover:bg-fluent-n7 dark:hover:bg-white/5':'' ?> <?= $isCur?'bg-fluent-blue-lt/20 dark:bg-fluent-blue/10':'' ?>">
                    <td class="px-5 py-3 font-medium <?= $isCur?'text-fluent-blue':'text-fluent-neutral' ?>">
                        <?= $mn[$m] ?><?php if($isCur): ?> <span class="text-[10px] bg-fluent-blue text-white px-1.5 py-0.5 rounded-full ml-1">●</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right text-fluent-n2"><?= $r['svc']>0?money($r['svc']):'—' ?></td>
                    <td class="px-4 py-3 text-right text-fluent-n2"><?= $r['com']>0?money($r['com']):'—' ?></td>
                    <td class="px-4 py-3 text-right font-semibold <?= $r['paid']>0?'text-fluent-neutral':'text-fluent-n4' ?>"><?= $r['paid']>0?money($r['paid']):'—' ?></td>
                    <td class="px-4 py-3 text-right <?= $r['pend']>0?'text-amber-600 font-medium':'text-fluent-n4' ?>"><?= $r['pend']>0?money($r['pend']):'—' ?></td>
                    <td class="px-4 py-3 text-right <?= $ir>0?'text-amber-600 font-medium':'text-fluent-n4' ?>"><?= $ir>0?money($ir):'—' ?></td>
                    <td class="px-4 py-3 text-right text-fluent-n3"><?= $r['cnt']>0?$r['cnt']:'—' ?></td>
                </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr class="bg-slate-800 dark:bg-slate-900 text-white">
                    <td class="px-5 py-3 font-bold">TOTAL <?= $fy ?></td>
                    <td class="px-4 py-3 text-right font-bold"><?= money($annSvc) ?></td>
                    <td class="px-4 py-3 text-right font-bold"><?= money($annCom) ?></td>
                    <td class="px-4 py-3 text-right font-bold"><?= money($annPaid) ?></td>
                    <td class="px-4 py-3 text-right font-bold text-amber-300"><?= money($annPend) ?></td>
                    <td class="px-4 py-3 text-right font-bold text-amber-300"><?= money($annIR) ?></td>
                    <td class="px-4 py-3 text-right font-bold"><?= array_sum(array_column($monthly,'cnt')) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Top clients -->
<?php if ($topClients): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
    <div class="px-5 py-4 border-b border-fluent-n5 dark:border-white/10">
        <h2 class="font-semibold text-sm text-fluent-neutral">Top <?= count($topClients) ?> Clients <?= $fy ?></h2>
    </div>
    <div class="divide-y divide-fluent-n6 dark:divide-white/5">
        <?php foreach ($topClients as $i=>$cl):
            $pct=$annPaid>0?round($cl['total']/$annPaid*100,1):0;
        ?>
        <div class="flex items-center gap-3 px-5 py-3.5">
            <div class="w-7 h-7 rounded-lg bg-fluent-blue-lt dark:bg-fluent-blue/20 flex items-center justify-center text-xs font-bold text-fluent-blue flex-shrink-0"><?= $i+1 ?></div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-fluent-neutral truncate"><?= h($cl['client_name']) ?></div>
                <div class="flex items-center gap-2 mt-1">
                    <div class="flex-1 h-1 bg-fluent-n5 dark:bg-white/10 rounded-full overflow-hidden">
                        <div class="h-full bg-fluent-blue rounded-full" style="width:<?= min(100,$pct*2) ?>%"></div>
                    </div>
                    <span class="text-xs text-fluent-n3 flex-shrink-0"><?= $cl['inv'] ?> fact. · <?= date('d/m/Y',strtotime($cl['last'])) ?></span>
                </div>
            </div>
            <div class="text-right flex-shrink-0">
                <div class="text-sm font-bold text-fluent-neutral"><?= money($cl['total']) ?></div>
                <div class="text-xs text-fluent-n3"><?= $pct ?>% du CA</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<style>
@media print {
    .lg\:pl-64 { padding-left:0!important; }
    aside,nav,header,.shadow-f { display:none!important; box-shadow:none!important; }
    main { margin:0!important; padding:0!important; }
}
</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
