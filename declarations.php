<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Déclarations IR & CNSS';
$db = getDB(); $cfg = getConfig();
$fy = (int)($_GET['year'] ?? $cfg['fiscal_year'] ?? date('Y'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $q=$_POST['quarter']??''; $amount=(float)($_POST['amount']??0); $type=clean($_POST['type']??'IR');
    $date=clean($_POST['date']??date('Y-m-d')); $ref=clean($_POST['reference']??'');
    if ($q && $amount>0) {
        $db->prepare("INSERT INTO ae_tax_payments (payment_type,quarter,fiscal_year,amount,payment_date,reference) VALUES (?,?,?,?,?,?)")
           ->execute([$type,$q,$fy,$amount,$date,$ref]);
        flash('message',"Paiement $type $q enregistré !");
    }
    header("Location: /declarations.php?year=$fy"); exit;
}

$decls = getDeclarations($fy);
$years = range((int)date('Y')+1, 2020);
$totalCA=$totalIR=$totalPaid=$totalBal=0;
foreach ($decls as $d) { $totalCA+=$d['ca_total'];$totalIR+=$d['ir_total'];$totalPaid+=$d['ir_paid'];$totalBal+=$d['ir_balance']; }
$csPaid=(float)$db->prepare("SELECT COALESCE(SUM(amount),0) FROM ae_tax_payments WHERE payment_type='CNSS' AND fiscal_year=?")->execute([$fy])&&false;
$cs=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM ae_tax_payments WHERE payment_type='CNSS' AND fiscal_year=?");$cs->execute([$fy]);$cnss_paid=(float)$cs->fetchColumn();
$cnss_due=($cfg['cnss_monthly']??100)*12;
$histStmt=$db->prepare("SELECT * FROM ae_tax_payments WHERE fiscal_year=? ORDER BY payment_date DESC");$histStmt->execute([$fy]);$history=$histStmt->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>
<!-- Year tabs -->
<div class="flex items-center gap-2 mb-5 overflow-x-auto pb-1">
    <?php foreach ($years as $y): ?>
    <a href="?year=<?= $y ?>" class="btn-f flex-shrink-0 px-4 py-2 text-sm rounded-xl border transition-colors <?= $y===$fy?'bg-fluent-blue text-white border-fluent-blue font-semibold':'bg-white dark:bg-gray-800 border-fluent-n4 dark:border-white/20 text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6 dark:hover:bg-white/10' ?>"><?= $y ?></a>
    <?php endforeach; ?>
</div>

<!-- Summary KPIs -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <?php foreach ([['CA Total',money($totalCA),'text-fluent-neutral'],['IR Dû',money($totalIR),'text-amber-600'],['IR Payé',money($totalPaid),'text-fluent-green'],['IR Solde',money($totalBal),$totalBal>0?'text-fluent-red':'text-fluent-green']] as [$l,$v,$c]): ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-xl font-bold <?= $c ?>"><?= $v ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5"><?= $l ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-3">
        <?php
        $qDates=['Q1'=>'1 Jan – 31 Mar','Q2'=>'1 Apr – 30 Jun','Q3'=>'1 Jul – 30 Sep','Q4'=>'1 Oct – 31 Dec'];
        foreach ($decls as $d):
            [$qLabel,$qYear]=explode('-',$d['quarter']);
            $isPaid=$d['status']==='paid'; $isEmpty=$d['status']==='empty';
            $isCurrentQ='Q'.ceil((int)date('n')/3)===$qLabel&&$fy===(int)date('Y');
        ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden <?= $isCurrentQ?'ring-2 ring-fluent-blue/30':'' ?>">
            <div class="flex items-center gap-4 px-5 py-4">
                <div class="w-12 h-12 rounded-xl flex-shrink-0 flex items-center justify-center font-bold text-sm
                    <?= $isPaid?'bg-fluent-green-lt text-fluent-green dark:bg-green-900/30':($isEmpty?'bg-fluent-n6 dark:bg-white/10 text-fluent-n3':'bg-amber-50 dark:bg-amber-900/20 text-amber-600') ?>">
                    <?= $isPaid?'✓':$qLabel ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-fluent-neutral"><?= h($d['quarter']) ?></span>
                        <span class="text-xs text-fluent-n3"><?= $qDates[$qLabel] ?></span>
                        <?php if ($isCurrentQ): ?><span class="text-[10px] bg-fluent-blue text-white px-1.5 py-0.5 rounded-full font-semibold">En cours</span><?php endif; ?>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $isPaid?'badge-paid':($isEmpty?'bg-fluent-n6 dark:bg-white/10 text-fluent-n3':'badge-pending') ?>">
                            <?= $isPaid?'✓ Payé':($isEmpty?'Pas de CA':'⏳ À déclarer') ?>
                        </span>
                    </div>
                    <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-0.5 text-xs">
                        <span class="text-fluent-n3">Services: <strong class="text-fluent-neutral"><?= money($d['ca_services']) ?></strong></span>
                        <span class="text-fluent-n3">Commerce: <strong class="text-fluent-neutral"><?= money($d['ca_commerce']) ?></strong></span>
                        <span class="text-fluent-n3">IR dû: <strong class="text-amber-600"><?= money($d['ir_total']) ?></strong></span>
                        <span class="text-fluent-n3">Solde: <strong class="<?= $d['ir_balance']>0?'text-fluent-red':'text-fluent-green' ?>"><?= money($d['ir_balance']) ?></strong></span>
                    </div>
                    <!-- Progress bar -->
                    <?php if (!$isEmpty && $d['ir_total']>0): $pp=min(100,round($d['ir_paid']/$d['ir_total']*100)); ?>
                    <div class="mt-2 h-1.5 bg-fluent-n5 dark:bg-white/10 rounded-full overflow-hidden">
                        <div class="h-full <?= $isPaid?'bg-fluent-green':'bg-amber-400' ?> rounded-full transition-all" style="width:<?= $pp ?>%"></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!$isEmpty && !$isPaid): ?>
                <button onclick="openModal('<?= h($d['quarter']) ?>',<?= $d['ir_balance'] ?>,'IR')"
                    class="btn-f flex-shrink-0 px-4 py-2 text-xs bg-fluent-blue text-white rounded-xl font-semibold shadow-f hover:bg-fluent-blue-dk">
                    Payer IR
                </button>
                <?php elseif ($isPaid): ?>
                <div class="flex-shrink-0 w-9 h-9 bg-fluent-green-lt dark:bg-green-900/30 rounded-full flex items-center justify-center text-fluent-green text-lg">✓</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- CNSS -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-sm text-fluent-neutral flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-600 font-bold text-xs">C</span>
                    CNSS <?= $fy ?>
                </h2>
                <button onclick="openModal('CNSS-<?= $fy ?>',<?= max(0,$cnss_due-$cnss_paid) ?>,'CNSS')"
                    class="btn-f px-3 py-1.5 text-xs bg-purple-600 text-white rounded-xl font-semibold hover:bg-purple-700">
                    Enregistrer paiement
                </button>
            </div>
            <div class="grid grid-cols-3 gap-4 mb-3">
                <?php foreach ([['Annuel dû',money($cnss_due),'text-fluent-neutral'],['Payé',money($cnss_paid),'text-fluent-green'],['Solde',money(max(0,$cnss_due-$cnss_paid)),($cnss_due-$cnss_paid)>0?'text-fluent-red':'text-fluent-green']] as [$l,$v,$c]): ?>
                <div class="text-center">
                    <div class="text-base font-bold <?= $c ?>"><?= $v ?></div>
                    <div class="text-xs text-fluent-n3"><?= $l ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="h-2 bg-fluent-n5 dark:bg-white/10 rounded-full overflow-hidden">
                <?php $pp=min(100,$cnss_due>0?round($cnss_paid/$cnss_due*100):0); ?>
                <div class="h-full bg-purple-500 rounded-full" style="width:<?= $pp ?>%"></div>
            </div>
            <div class="text-xs text-fluent-n3 mt-1"><?= money($cfg['cnss_monthly']??100) ?>/mois · <?= $pp ?>% payé</div>
        </div>
    </div>

    <!-- Payment history -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
        <div class="px-5 py-4 border-b border-fluent-n5 dark:border-white/10">
            <h2 class="font-semibold text-sm text-fluent-neutral">Historique des Paiements</h2>
        </div>
        <?php if (empty($history)): ?>
        <div class="px-5 py-8 text-center text-xs text-fluent-n3">Aucun paiement enregistré</div>
        <?php else: ?>
        <div class="divide-y divide-fluent-n6 dark:divide-white/5 max-h-96 overflow-y-auto">
            <?php foreach ($history as $h): ?>
            <div class="px-4 py-3 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0
                    <?= $h['payment_type']==='IR'?'bg-amber-50 text-amber-600 dark:bg-amber-900/30':'bg-purple-100 text-purple-600 dark:bg-purple-900/30' ?>">
                    <?= $h['payment_type'] ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-semibold text-fluent-neutral"><?= h($h['quarter']??$h['fiscal_year']) ?></div>
                    <div class="text-[10px] text-fluent-n3"><?= $h['payment_date']?date('d/m/Y',strtotime($h['payment_date'])):'—' ?><?= $h['reference']?' · '.h($h['reference']):'' ?></div>
                </div>
                <div class="text-sm font-bold text-fluent-green">+<?= money($h['amount']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <!-- DGI links -->
        <div class="px-5 py-4 border-t border-fluent-n5 dark:border-white/10 space-y-2">
            <p class="text-xs font-semibold text-fluent-n2 mb-2">Portails de déclaration</p>
            <?php foreach ([['Simpl-AE','https://simpl.tax.gov.ma'],['Damancom','https://damancom.ma'],['DGI Maroc','https://portail.tax.gov.ma']] as [$name,$url]): ?>
            <a href="<?= $url ?>" target="_blank" rel="noopener"
                class="btn-f flex items-center justify-between px-3 py-2 bg-fluent-n7 dark:bg-white/5 rounded-xl hover:bg-fluent-blue-lt dark:hover:bg-fluent-blue/10 transition-colors">
                <span class="text-xs font-medium text-fluent-n2"><?= $name ?></span>
                <svg class="w-3.5 h-3.5 text-fluent-n3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="pay-modal" class="fixed inset-0 z-50 hidden flex items-end sm:items-center justify-center p-4 bg-black/50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-fl w-full max-w-sm">
        <div class="px-5 py-4 border-b border-fluent-n5 dark:border-white/10 flex items-center justify-between">
            <h3 class="font-semibold text-fluent-neutral text-sm" id="modal-title">Enregistrer le paiement</h3>
            <button onclick="closeModal()" class="text-fluent-n3 hover:text-fluent-neutral text-xl leading-none">&times;</button>
        </div>
        <form method="POST" class="px-5 py-4 space-y-3">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="type" id="modal-type">
            <input type="hidden" name="quarter" id="modal-quarter">
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Montant (MAD) *</label>
                <input type="number" name="amount" id="modal-amount" step="0.01" min="0" required
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 font-bold text-fluent-blue text-center text-lg">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Date</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                </div>
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Réf. Simpl-AE</label>
                    <input type="text" name="reference" placeholder="REF-XXXXX" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
                </div>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="button" onclick="closeModal()" class="flex-1 py-2.5 border border-fluent-n4 dark:border-white/20 rounded-xl text-sm text-fluent-n2 hover:bg-fluent-n6 dark:hover:bg-white/10">Annuler</button>
                <button type="submit" class="flex-1 py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk">Confirmer</button>
            </div>
        </form>
    </div>
</div>
<script>
function openModal(q,a,t){document.getElementById('modal-quarter').value=q;document.getElementById('modal-amount').value=a.toFixed(2);document.getElementById('modal-type').value=t;document.getElementById('modal-title').textContent='Payer '+t+' — '+q;document.getElementById('pay-modal').classList.remove('hidden');}
function closeModal(){document.getElementById('pay-modal').classList.add('hidden');}
document.getElementById('pay-modal').addEventListener('click',function(e){if(e.target===this)closeModal();});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
