<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Mouvements Bancaires';
$db  = getDB();
$cfg = getConfig();
$fy  = (int)($_GET['year']    ?? $cfg['fiscal_year'] ?? date('Y'));
$aid = (int)($_GET['account'] ?? 0); // 0 = all accounts

// ── Load bank accounts ────────────────────────────────────────
$accounts = $db->query("SELECT * FROM ae_bank_accounts WHERE is_active=1 ORDER BY sort_order,id")->fetchAll();
if (!$aid && count($accounts)) $aid = (int)$accounts[0]['id'];

// ── Transaction types definition ─────────────────────────────
$txTypes = [
    'declared'  => ['label'=>'Déclaré',     'color'=>'text-fluent-green',  'bg'=>'bg-green-50 dark:bg-green-900/20',   'border'=>'border-green-200 dark:border-green-800',   'icon'=>'✅', 'desc'=>'Lié à une facture — revenu déclaré AE'],
    'hors_facture' => ['label'=>'Hors facture',    'color'=>'text-amber-700',     'bg'=>'bg-amber-50 dark:bg-amber-900/20',   'border'=>'border-amber-200 dark:border-amber-700',   'icon'=>'💵', 'desc'=>'Prestation sans facture émise'],
    'transfer'  => ['label'=>'Virement',    'color'=>'text-fluent-blue',   'bg'=>'bg-blue-50 dark:bg-blue-900/20',     'border'=>'border-blue-200 dark:border-blue-800',     'icon'=>'↔️', 'desc'=>'Virement entre vos comptes'],
    'personal'  => ['label'=>'Personnel',   'color'=>'text-purple-600',    'bg'=>'bg-purple-50 dark:bg-purple-900/20', 'border'=>'border-purple-200 dark:border-purple-800', 'icon'=>'👤', 'desc'=>'Dépôt/retrait personnel — non imposable'],
    'expense'   => ['label'=>'Dépense',     'color'=>'text-fluent-red',    'bg'=>'bg-red-50 dark:bg-red-900/20',       'border'=>'border-red-200 dark:border-red-800',       'icon'=>'💸', 'desc'=>'Charge, frais bancaires, abonnement'],
    'other'     => ['label'=>'À classifier','color'=>'text-fluent-n3',     'bg'=>'bg-fluent-n6 dark:bg-white/10',      'border'=>'border-fluent-n4 dark:border-white/20',    'icon'=>'❓', 'desc'=>'Non encore classifié'],
];

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'add';
    $redir  = "/bank.php?year=$fy&account=$aid";

    if ($action === 'delete') {
        $db->prepare("DELETE FROM ae_bank_transactions WHERE id=?")->execute([(int)$_POST['id']]);
        flash('message', 'Transaction supprimée.', 'error');
        header("Location: $redir"); exit;
    }

    if ($action === 'reconcile') {
        $tid  = (int)$_POST['tid'];
        $iid  = (int)($_POST['iid'] ?? 0) ?: null;
        $type = $iid ? 'declared' : clean($_POST['new_type'] ?? 'other');
        $db->prepare("UPDATE ae_bank_transactions SET invoice_id=?,reconciled=?,transaction_type=? WHERE id=?")
           ->execute([$iid, $iid ? 1 : 0, $type, $tid]);
        if ($iid) {
            $invSt = $db->prepare("SELECT status FROM ae_invoices WHERE id=?");
            $invSt->execute([$iid]);
            if ($invSt->fetchColumn() === 'En attente') {
                $db->prepare("UPDATE ae_invoices SET status='Payé',payment_date=CURDATE(),payment_method='Virement' WHERE id=?")->execute([$iid]);
                flash('message', 'Rapproché et facture marquée Payée !');
            } else {
                flash('message', 'Transaction rapprochée.');
            }
        } else {
            flash('message', 'Classification enregistrée.');
        }
        header("Location: $redir"); exit;
    }

    if ($action === 'retype') {
        $tid  = (int)$_POST['tid'];
        $type = clean($_POST['transaction_type'] ?? 'other');
        $db->prepare("UPDATE ae_bank_transactions SET transaction_type=? WHERE id=?")->execute([$type, $tid]);
        flash('message', 'Classification mise à jour.');
        header("Location: $redir"); exit;
    }

    // add
    $date   = clean($_POST['date']       ?? date('Y-m-d'));
    $desc   = clean($_POST['desc']       ?? '');
    $cr     = (float)($_POST['credit']   ?? 0);
    $dr     = (float)($_POST['debit']    ?? 0);
    $notes  = clean($_POST['notes']      ?? '');
    $accId  = (int)($_POST['account_id'] ?? $aid ?: 1);
    $txType = clean($_POST['tx_type']    ?? '');

    // Smart default type if user didn't pick explicitly
    if (!$txType || $txType === 'other') {
        if ($cr > 0 && $dr === 0.0)      $txType = 'hors_facture'; // unlinked credit = informal until proven otherwise
        elseif ($dr > 0 && $cr === 0.0)  $txType = 'expense';
        else                              $txType = 'other';
    }

    if ($desc || $cr || $dr) {
        $db->prepare("INSERT INTO ae_bank_transactions
            (account_id,transaction_date,description,credit,debit,fiscal_year,notes,transaction_type)
            VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$accId, $date, $desc, $cr, $dr, $fy, $notes, $txType]);
        flash('message', 'Transaction ajoutée !');
    }
    header("Location: $redir"); exit;
}

// ── Fetch transactions ─────────────────────────────────────────
$whereAcc = $aid ? "AND t.account_id = $aid" : "";
$txSt = $db->prepare("
    SELECT t.*, i.invoice_number, i.client_name AS inv_client, i.amount_ttc AS inv_amt,
           a.name AS account_name, a.color AS account_color, a.type AS account_type
    FROM ae_bank_transactions t
    LEFT JOIN ae_invoices i ON i.id = t.invoice_id
    LEFT JOIN ae_bank_accounts a ON a.id = t.account_id
    WHERE t.fiscal_year = ? $whereAcc
    ORDER BY t.transaction_date ASC, t.id ASC");
$txSt->execute([$fy]);
$txAll = $txSt->fetchAll();

// Running balance + auto-fix type
$running = 0;
foreach ($txAll as &$tx) {
    $running += (float)$tx['credit'] - (float)$tx['debit'];
    $tx['balance'] = $running;
    if ($tx['invoice_id'] && ($tx['transaction_type'] ?? 'other') !== 'declared') {
        $tx['transaction_type'] = 'declared';
    }
    if (!isset($tx['transaction_type']) || !$tx['transaction_type']) {
        $tx['transaction_type'] = 'other';
    }
}
unset($tx);
$txAll = array_reverse($txAll); // newest first

// ── Compute KPIs ──────────────────────────────────────────────
$totalCredit     = array_sum(array_column($txAll, 'credit'));
$totalDebit      = array_sum(array_column($txAll, 'debit'));
$balance         = $totalCredit - $totalDebit;
$reconciled      = count(array_filter($txAll, fn($t) => $t['reconciled']));

$declaredCredits = 0; $horsFacture = 0; $uncategorized = 0;
$horsFacCount   = 0; $otherCount = 0;
foreach ($txAll as $tx) {
    if ((float)$tx['credit'] <= 0) continue;
    $tt = $tx['transaction_type'] ?? 'other';
    if ($tt === 'declared')                         { $declaredCredits += (float)$tx['credit']; }
    elseif ($tt === 'hors_facture')                     { $horsFacture += (float)$tx['credit']; $horsFacCount++; }
    elseif (in_array($tt, ['transfer','personal'])) { /* neutral */ }
    else                                            { $uncategorized   += (float)$tx['credit']; $otherCount++; }
}

$ledSt = $db->prepare("SELECT COALESCE(SUM(amount_ttc),0) FROM ae_invoices WHERE status='Payé' AND fiscal_year=?");
$ledSt->execute([$fy]);
$ledger      = (float)$ledSt->fetchColumn();
$ledgerGap   = $totalCredit - ($horsFacture + $uncategorized) - $ledger; // should be ~0
$irRate      = (float)($cfg['ir_rate_services'] ?? 0.01);
$horsFacIR  = $horsFacture * $irRate;

// Pending invoices
$pendSt = $db->prepare("SELECT id,invoice_number,client_name,amount_ttc FROM ae_invoices WHERE status='En attente' AND fiscal_year=? ORDER BY invoice_date DESC");
$pendSt->execute([$fy]);
$pending = $pendSt->fetchAll();

// All invoices for reconcile modal
$allInvSt = $db->prepare("SELECT id,invoice_number,client_name,amount_ttc,status FROM ae_invoices WHERE fiscal_year=? ORDER BY invoice_date DESC");
$allInvSt->execute([$fy]);
$allInv = $allInvSt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Account selector tabs ─────────────────────────────────── -->
<div class="flex items-center gap-2 mb-4 overflow-x-auto pb-1 flex-nowrap">
    <a href="/bank.php?year=<?= $fy ?>&account=0"
        class="btn-f flex-shrink-0 flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border transition-colors
        <?= !$aid ? 'bg-fluent-blue text-white border-fluent-blue shadow-f' : 'bg-white dark:bg-gray-800 border-fluent-n4 dark:border-white/20 text-fluent-n2 hover:bg-fluent-n6' ?>">
        🏦 Tous les comptes
    </a>
    <?php foreach ($accounts as $acc):
        $isActive = $aid === (int)$acc['id'];
        $bSt = $db->prepare("SELECT COALESCE(SUM(credit),0)-COALESCE(SUM(debit),0) FROM ae_bank_transactions WHERE account_id=?");
        $bSt->execute([$acc['id']]);
        $bal = (float)$acc['opening_balance'] + (float)$bSt->fetchColumn();
    ?>
    <a href="/bank.php?year=<?= $fy ?>&account=<?= $acc['id'] ?>"
        class="btn-f flex-shrink-0 flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border transition-colors
        <?= $isActive ? 'text-white shadow-f border-transparent' : 'bg-white dark:bg-gray-800 border-fluent-n4 dark:border-white/20 text-fluent-n2 hover:bg-fluent-n6' ?>"
        <?= $isActive ? "style=\"background:{$acc['color']};border-color:{$acc['color']}\"" : '' ?>>
        <span><?= $acc['type']==='cash'?'💵':($acc['type']==='ewallet'?'📱':'🏦') ?></span>
        <span><?= h($acc['name']) ?></span>
        <span class="<?= $isActive?'opacity-70':'text-fluent-n3' ?> font-normal"><?= money($bal) ?></span>
    </a>
    <?php endforeach; ?>
    <a href="/bank-accounts.php" class="btn-f flex-shrink-0 px-3 py-2 text-xs rounded-xl border border-dashed border-fluent-n4 dark:border-white/20 text-fluent-n3 hover:border-fluent-blue hover:text-fluent-blue bg-white dark:bg-gray-800 transition-colors">
        ⚙️ Gérer
    </a>
</div>

<!-- ── Hors facture info banner ──────────────────────────────── -->
<?php if ($horsFacture > 0 || $uncategorized > 0): ?>
<div class="mb-5 p-4 bg-fluent-n7 dark:bg-white/5 border border-fluent-n5 dark:border-white/10 rounded-2xl">
    <div class="flex items-start gap-3">
        <div class="text-xl flex-shrink-0 mt-0.5">📊</div>
        <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-fluent-neutral text-sm mb-1">Récapitulatif des mouvements — <?= $fy ?></h3>
            <div class="text-xs text-fluent-n2 space-y-1 leading-relaxed">
                <?php if ($horsFacture > 0): ?>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                    <strong><?= money($horsFacture) ?></strong> en crédits <strong>hors facture</strong>
                    (<?= $horsFacCount ?> mouvement<?= $horsFacCount>1?'s':'' ?>) —
                    <span class="text-fluent-n3">prestations sans facture émise</span>
                </div>
                <?php endif; ?>
                <?php if ($uncategorized > 0): ?>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="w-2 h-2 rounded-full bg-fluent-n4 flex-shrink-0"></span>
                    <strong><?= money($uncategorized) ?></strong> non classifié<?= $otherCount>1?'s':'' ?>
                    (<?= $otherCount ?> mouvement<?= $otherCount>1?'s':'' ?>) —
                    <button onclick="document.querySelectorAll('[data-unclassified]').forEach(b=>b.closest('tr')?.scrollIntoView({behavior:'smooth'}))"
                        class="text-fluent-blue hover:underline">cliquez ❓ dans la liste pour classer</button>
                </div>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2 mt-2.5 flex-wrap">
                <a href="/invoice-new.php" class="btn-f text-xs bg-fluent-blue text-white px-3 py-1.5 rounded-lg font-semibold hover:bg-fluent-blue-dk">
                    + Émettre une facture
                </a>
                <span class="text-xs text-fluent-n3">ou classez chaque mouvement via les badges dans la liste</span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── KPI strip ──────────────────────────────────────────────── -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-lg font-bold text-fluent-green"><?= money($totalCredit) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Total Crédits</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-lg font-bold text-fluent-red"><?= money($totalDebit) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Total Débits</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-lg font-bold text-fluent-green"><?= money($declaredCredits) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">✅ Déclarés</div>
        <div class="text-[10px] text-fluent-n4">Liés à des factures</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center relative overflow-hidden">
        <?php if ($horsFacture > 0): ?><div class="absolute top-0 inset-x-0 h-0.5 bg-amber-400"></div><?php endif; ?>
        <div class="text-lg font-bold <?= $horsFacture>0 ? 'text-amber-600' : 'text-fluent-n3' ?>"><?= money($horsFacture) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">💵 Hors facture</div>
        <div class="text-[10px] text-fluent-n4">Sans facture émise</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4 text-center">
        <div class="text-lg font-bold <?= $balance>=0?'text-fluent-neutral':'text-fluent-red' ?>"><?= money($balance) ?></div>
        <div class="text-xs text-fluent-n3 mt-0.5">Solde Net</div>
        <div class="text-[10px] <?= abs($ledgerGap)<1?'text-fluent-green':'text-amber-500' ?>">Écart: <?= money($ledgerGap) ?></div>
    </div>
</div>

<!-- ── Reconcile progress bar ────────────────────────────────── -->
<?php if (count($txAll)): $rPct = count($txAll)>0 ? round($reconciled/count($txAll)*100) : 0; ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f px-5 py-4 mb-5">
    <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
        <span class="text-xs font-semibold text-fluent-n2">Rapprochement <?= $fy ?></span>
        <div class="flex items-center gap-3 text-xs flex-wrap">
            <span class="text-fluent-green font-medium">✅ <?= $reconciled ?> rapprochées</span>
            <?php if ($horsFacCount > 0): ?><span class="text-amber-600 font-medium">💵 <?= $horsFacCount ?> hors facture</span><?php endif; ?>
            <?php if ($otherCount > 0):    ?><span class="text-fluent-n3 font-medium">❓ <?= $otherCount ?> non classifiées</span><?php endif; ?>
            <span class="text-fluent-n3"><?= $rPct ?>%</span>
        </div>
    </div>
    <div class="h-2 bg-fluent-n5 dark:bg-white/10 rounded-full overflow-hidden flex">
        <?php if ($totalCredit > 0):
            $decPct = round($declaredCredits / $totalCredit * 100);
            $infPct = round($horsFacture / $totalCredit * 100);
        ?>
        <div class="h-full bg-fluent-green rounded-l-full transition-all" style="width:<?= $decPct ?>%"></div>
        <div class="h-full bg-amber-400 transition-all" style="width:<?= $infPct ?>%"></div>
        <?php endif; ?>
    </div>
    <div class="flex items-center gap-4 mt-1.5 text-[10px] text-fluent-n3">
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-fluent-green"></span>Déclaré</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-400"></span>Hors facture</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-fluent-n4"></span>Neutre/Non classifié</span>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

<!-- ── Add form ─────────────────────────────────────────────── -->
<div class="space-y-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
        <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
            <span class="w-6 h-6 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs font-bold">+</span>
            Nouvelle Transaction
        </h2>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">

            <!-- Account -->
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Compte *</label>
                <select name="account_id" required
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                    <?php foreach ($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>" <?= $aid===(int)$acc['id']?'selected':'' ?>>
                        <?= $acc['type']==='cash'?'💵':($acc['type']==='ewallet'?'📱':'🏦') ?>
                        <?= h($acc['name']) ?><?= $acc['bank_name'] ? ' — '.h($acc['bank_name']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date -->
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Date *</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Description *</label>
                <input name="desc" placeholder="Ex: Virement client, Salaire, Facture..." required
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
            </div>

            <!-- Transaction type — KEY FEATURE -->
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1.5">
                    Nature du mouvement *
                    <span class="text-fluent-n3 font-normal ml-1">⚠️ Impact fiscal</span>
                </label>
                <div class="space-y-1.5">
                    <?php foreach ($txTypes as $key => $t): ?>
                    <label class="type-radio flex items-center gap-2.5 px-3 py-2 rounded-xl border cursor-pointer transition-all hover:border-fluent-blue <?= $t['bg'] ?> <?= $t['border'] ?>"
                        data-key="<?= $key ?>">
                        <input type="radio" name="tx_type" value="<?= $key ?>"
                            <?= $key==='other'?'checked':'' ?> class="text-fluent-blue flex-shrink-0">
                        <span class="text-base"><?= $t['icon'] ?></span>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-semibold <?= $t['color'] ?>"><?= $t['label'] ?></div>
                            <div class="text-[10px] text-fluent-n3 leading-tight"><?= $t['desc'] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2 p-2.5 bg-fluent-n7 dark:bg-white/5 rounded-lg border border-fluent-n5 dark:border-white/10">
                    <p class="text-[10px] text-fluent-n2 leading-relaxed">
                        <strong>📄 Facturé</strong> : lié à une facture, compte dans votre CA déclaré.<br>
                        <strong>💵 Hors facture</strong> : prestation sans facture émise, enregistrée pour votre suivi uniquement.
                    </p>
                </div>
            </div>

            <!-- Amounts -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-medium text-fluent-green mb-1">Crédit (MAD)</label>
                    <input type="number" name="credit" placeholder="0.00" min="0" step="0.01"
                        class="w-full px-3 py-2.5 text-sm border border-green-200 dark:border-green-800 rounded-xl inp bg-white dark:bg-gray-700 text-right font-semibold text-fluent-green">
                </div>
                <div>
                    <label class="block text-xs font-medium text-fluent-red mb-1">Débit (MAD)</label>
                    <input type="number" name="debit" placeholder="0.00" min="0" step="0.01"
                        class="w-full px-3 py-2.5 text-sm border border-red-200 dark:border-red-800 rounded-xl inp bg-white dark:bg-gray-700 text-right font-semibold text-fluent-red">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Notes</label>
                <input name="notes" placeholder="Référence, numéro chèque…"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
            </div>

            <button type="submit" class="btn-f w-full py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk shadow-f">
                Ajouter la transaction
            </button>
        </form>
    </div>

    <!-- Pending invoices -->
    <?php if ($pending): ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4">
        <h3 class="font-semibold text-xs text-fluent-n2 uppercase tracking-wider mb-3 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
            Factures en attente
        </h3>
        <div class="space-y-2 max-h-44 overflow-y-auto">
            <?php foreach ($pending as $p): ?>
            <div class="flex items-center justify-between px-2.5 py-2 bg-amber-50 dark:bg-amber-900/20 rounded-xl text-xs border border-amber-100 dark:border-amber-800">
                <div class="min-w-0">
                    <div class="font-bold text-amber-700 dark:text-amber-400 truncate"><?= h($p['invoice_number']) ?></div>
                    <div class="text-amber-600/70 truncate"><?= h($p['client_name']) ?></div>
                </div>
                <span class="font-bold text-amber-700 dark:text-amber-400 ml-2 flex-shrink-0"><?= money($p['amount_ttc']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Year + links -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-4">
        <h3 class="font-semibold text-xs text-fluent-n2 uppercase tracking-wider mb-3">Exercice</h3>
        <div class="flex flex-wrap gap-2 mb-3">
            <?php for ($y=(int)date('Y')+1; $y>=2020; $y--): ?>
            <a href="?year=<?= $y ?>&account=<?= $aid ?>"
                class="btn-f px-3 py-1.5 text-xs rounded-lg border
                <?= $y===$fy?'bg-fluent-blue text-white border-fluent-blue':'bg-fluent-n7 dark:bg-white/5 border-fluent-n4 dark:border-white/10 text-fluent-n2 hover:bg-fluent-n6' ?>">
                <?= $y ?>
            </a>
            <?php endfor; ?>
        </div>
        <div class="space-y-1">
            <a href="/bank-accounts.php" class="btn-f flex items-center gap-2 px-3 py-2 text-xs text-fluent-n2 hover:bg-fluent-n6 rounded-xl border border-fluent-n4 dark:border-white/20 bg-white dark:bg-gray-800 w-full">🏦 Gérer les comptes</a>
            <a href="/bank-import.php<?= $aid?'?account='.$aid:'' ?>" class="btn-f flex items-center gap-2 px-3 py-2 text-xs text-fluent-n2 hover:bg-fluent-n6 rounded-xl border border-fluent-n4 dark:border-white/20 bg-white dark:bg-gray-800 w-full">📥 Importer relevé CSV</a>
        </div>
    </div>
</div>

<!-- ── Transaction list ──────────────────────────────────────── -->
<div class="lg:col-span-2">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-fluent-n5 dark:border-white/10 flex-wrap gap-2">
            <h2 class="font-semibold text-sm text-fluent-neutral">
                <?= count($txAll) ?> transactions — <?= $fy ?>
                <?php if ($aid): $activeAcc = null; foreach($accounts as $acc) { if((int)$acc['id']===$aid){ $activeAcc=$acc; break; } } if($activeAcc): ?>
                <span class="text-xs text-fluent-n3 font-normal">/ <?= h($activeAcc['name']) ?></span>
                <?php endif; endif; ?>
            </h2>
        </div>

        <?php if (empty($txAll)): ?>
        <div class="px-5 py-16 text-center">
            <div class="text-5xl mb-3">🏦</div>
            <div class="text-sm font-medium text-fluent-neutral">Aucune transaction — <?= $fy ?></div>
            <div class="text-xs text-fluent-n3 mt-1">Ajoutez des mouvements ou importez un relevé CSV</div>
        </div>
        <?php else: ?>

        <!-- Desktop table -->
        <div class="hidden md:block overflow-x-auto" style="max-height:620px;overflow-y:auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-fluent-n7 dark:bg-gray-900 border-b border-fluent-n5 dark:border-white/10">
                        <th class="text-left px-3 py-3 text-xs font-semibold text-fluent-n3">DATE</th>
                        <th class="text-left px-3 py-3 text-xs font-semibold text-fluent-n3">DESCRIPTION</th>
                        <th class="text-right px-3 py-3 text-xs font-semibold text-fluent-green">CRÉDIT</th>
                        <th class="text-right px-3 py-3 text-xs font-semibold text-fluent-red">DÉBIT</th>
                        <th class="text-right px-3 py-3 text-xs font-semibold text-fluent-n3">SOLDE</th>
                        <th class="text-center px-3 py-3 text-xs font-semibold text-fluent-n3">NATURE</th>
                        <th class="px-2 py-3 w-8"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-fluent-n6 dark:divide-white/5">
                    <?php foreach ($txAll as $tx):
                        $tt     = $tx['transaction_type'] ?? 'other';
                        $tcfg   = $txTypes[$tt] ?? $txTypes['other'];
                        $isRec  = (bool)$tx['reconciled'];
                        $rowCls = match($tt) {
                            'hors_facture' => $tx['credit']>0 ? 'bg-amber-50/60 dark:bg-amber-900/10' : '',
                            'other'    => $tx['credit']>0 ? 'bg-gray-50/80 dark:bg-white/3' : '',
                            default    => '',
                        };
                    ?>
                    <tr class="hover:bg-fluent-n7 dark:hover:bg-white/5 transition-colors group <?= $rowCls ?>">

                        <td class="px-3 py-3 text-xs text-fluent-n3 whitespace-nowrap">
                            <?= date('d/m/Y',strtotime($tx['transaction_date'])) ?>
                        </td>

                        <td class="px-3 py-3">
                            <div class="text-sm font-medium text-fluent-neutral max-w-[180px] truncate"><?= h($tx['description']) ?></div>
                            <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                <?php if ($tx['notes']): ?>
                                <span class="text-[10px] text-fluent-n3 truncate max-w-[120px]"><?= h($tx['notes']) ?></span>
                                <?php endif; ?>
                                <?php if (!$aid && $tx['account_name']): ?>
                                <span class="text-[10px] px-1.5 py-0.5 rounded text-white font-medium flex-shrink-0"
                                    style="background:<?= h($tx['account_color']??'#0078d4') ?>">
                                    <?= h($tx['account_name']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td class="px-3 py-3 text-right font-bold text-sm <?= $tx['credit']>0?'text-fluent-green':'text-fluent-n4' ?>">
                            <?= $tx['credit']>0 ? '+'.money($tx['credit']) : '—' ?>
                        </td>

                        <td class="px-3 py-3 text-right font-bold text-sm <?= $tx['debit']>0?'text-fluent-red':'text-fluent-n4' ?>">
                            <?= $tx['debit']>0 ? '-'.money($tx['debit']) : '—' ?>
                        </td>

                        <td class="px-3 py-3 text-right text-xs font-mono <?= $tx['balance']>=0?'text-fluent-n2':'text-fluent-red' ?>">
                            <?= money($tx['balance']) ?>
                        </td>

                        <td class="px-3 py-3 text-center">
                            <div class="flex flex-col items-center gap-1">
                                <!-- Type badge — clickable to reclassify -->
                                <button onclick="openRetypeModal(<?= $tx['id'] ?>, '<?= $tt ?>')"
                                    title="Cliquez pour modifier la classification"
                                    class="<?= $tcfg['bg'] ?> <?= $tcfg['color'] ?> text-[10px] px-2 py-1 rounded-lg font-semibold hover:opacity-80 transition-opacity flex items-center gap-1 whitespace-nowrap border <?= $tcfg['border'] ?>">
                                    <span><?= $tcfg['icon'] ?></span>
                                    <span><?= $tcfg['label'] ?></span>
                                </button>
                                <!-- Invoice link / action -->
                                <?php if ($isRec && $tx['invoice_number']): ?>
                                <a href="/invoice-view.php?id=<?= $tx['invoice_id'] ?>"
                                    class="text-[9px] text-fluent-blue hover:underline truncate max-w-[110px]">
                                    📄 <?= h($tx['invoice_number']) ?>
                                </a>
                                <?php elseif ($tx['credit']>0 && !$isRec): ?>
                                <button onclick="openRecModal(<?= $tx['id'] ?>, <?= $tx['credit'] ?>, '<?= $tt ?>')"
                                    class="text-[9px] text-fluent-blue hover:underline whitespace-nowrap">
                                    ↔ Lier à facture
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td class="px-2 py-3">
                            <form method="POST" class="opacity-0 group-hover:opacity-100 transition-opacity">
                                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $tx['id'] ?>">
                                <button type="submit" onclick="return confirmDelete('Supprimer cette transaction ?')"
                                    class="btn-f p-1.5 text-fluent-n4 hover:text-fluent-red rounded-lg">
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
        <div class="md:hidden divide-y divide-fluent-n6 dark:divide-white/5" style="max-height:520px;overflow-y:auto">
            <?php foreach ($txAll as $tx):
                $tt   = $tx['transaction_type'] ?? 'other';
                $tcfg = $txTypes[$tt] ?? $txTypes['other'];
                $isRec= (bool)$tx['reconciled'];
            ?>
            <div class="px-4 py-3.5 <?= $tt==='hors_facture'&&$tx['credit']>0?'bg-amber-50/60 dark:bg-amber-900/10':'' ?>">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-fluent-neutral truncate"><?= h($tx['description']) ?></div>
                        <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                            <span class="text-xs text-fluent-n3"><?= date('d/m/Y',strtotime($tx['transaction_date'])) ?></span>
                            <?php if (!$aid && $tx['account_name']): ?>
                            <span class="text-[10px] px-1.5 py-0.5 rounded text-white font-medium" style="background:<?= h($tx['account_color']??'#0078d4') ?>"><?= h($tx['account_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <?php if ($tx['credit']>0): ?><div class="text-sm font-bold text-fluent-green">+<?= money($tx['credit']) ?></div><?php endif; ?>
                        <?php if ($tx['debit']>0): ?><div class="text-sm font-bold text-fluent-red">-<?= money($tx['debit']) ?></div><?php endif; ?>
                        <div class="text-[10px] text-fluent-n3 mt-0.5">Solde: <?= money($tx['balance']) ?></div>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button onclick="openRetypeModal(<?= $tx['id'] ?>, '<?= $tt ?>')"
                        class="<?= $tcfg['bg'] ?> <?= $tcfg['color'] ?> text-[10px] px-2 py-1 rounded-lg font-semibold flex items-center gap-1 border <?= $tcfg['border'] ?>">
                        <?= $tcfg['icon'] ?> <?= $tcfg['label'] ?>
                    </button>
                    <?php if ($isRec && $tx['invoice_number']): ?>
                    <a href="/invoice-view.php?id=<?= $tx['invoice_id'] ?>" class="text-[10px] text-fluent-blue hover:underline">📄 <?= h($tx['invoice_number']) ?></a>
                    <?php elseif ($tx['credit']>0 && !$isRec): ?>
                    <button onclick="openRecModal(<?= $tx['id'] ?>, <?= $tx['credit'] ?>, '<?= $tt ?>')" class="text-[10px] text-fluent-blue hover:underline">↔ Lier à facture</button>
                    <?php endif; ?>
                    <form method="POST" class="ml-auto">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $tx['id'] ?>">
                        <button type="submit" onclick="return confirmDelete('Supprimer ?')" class="btn-f p-1.5 text-fluent-n4 hover:text-fluent-red rounded-lg">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Totals -->
        <div class="grid grid-cols-3 gap-px bg-fluent-n5 dark:bg-white/10 border-t border-fluent-n5 dark:border-white/10">
            <div class="bg-fluent-green-lt dark:bg-green-900/30 px-4 py-3 text-center">
                <div class="text-[10px] text-fluent-green/70">Crédits</div>
                <div class="text-sm font-bold text-fluent-green"><?= money($totalCredit) ?></div>
            </div>
            <div class="bg-fluent-red-lt dark:bg-red-900/30 px-4 py-3 text-center">
                <div class="text-[10px] text-fluent-red/70">Débits</div>
                <div class="text-sm font-bold text-fluent-red"><?= money($totalDebit) ?></div>
            </div>
            <div class="bg-white dark:bg-gray-800 px-4 py-3 text-center">
                <div class="text-[10px] text-fluent-n3">Solde</div>
                <div class="text-sm font-bold <?= $balance>=0?'text-fluent-neutral':'text-fluent-red' ?>"><?= money($balance) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- ══ RECONCILE MODAL ════════════════════════════════════════ -->
<div id="rec-modal" class="fixed inset-0 z-[60] hidden flex items-end sm:items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-fxl w-full max-w-lg">
        <div class="px-5 py-4 border-b border-fluent-n5 dark:border-white/10 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-fluent-neutral text-sm">Rapprocher / Classifier</h3>
                <p class="text-xs text-fluent-n3 mt-0.5">Liez à une facture ou classez le mouvement</p>
            </div>
            <button onclick="closeRecModal()" class="text-fluent-n3 hover:text-fluent-neutral w-8 h-8 flex items-center justify-center rounded-lg hover:bg-fluent-n6 text-lg">✕</button>
        </div>
        <form method="POST" class="px-5 py-4 space-y-4">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="reconcile">
            <input type="hidden" name="tid" id="rec-tid">

            <div class="p-3 bg-fluent-blue-lt dark:bg-fluent-blue/20 rounded-xl text-sm font-bold text-fluent-blue text-center" id="rec-amount"></div>

            <!-- Link invoice -->
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1.5">
                    📄 Lier à une facture <span class="text-fluent-n3 font-normal">(associe ce crédit à une facture émise)</span>
                </label>
                <select name="iid" id="rec-iid" onchange="toggleClassify()"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                    <option value="">— Aucune facture correspondante —</option>
                    <?php foreach ($allInv as $ai): ?>
                    <option value="<?= $ai['id'] ?>" class="<?= $ai['status']==='Payé'?'text-fluent-n3':'' ?>">
                        <?= h($ai['invoice_number']) ?> — <?= h($ai['client_name']) ?> (<?= money($ai['amount_ttc']) ?>)<?= $ai['status']==='Payé'?' ✓':'' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- OR classify -->
            <div id="classify-section">
                <div class="flex items-center gap-3 my-1">
                    <div class="flex-1 h-px bg-fluent-n5 dark:bg-white/10"></div>
                    <span class="text-xs text-fluent-n3 font-medium px-2">OU classifier sans facture</span>
                    <div class="flex-1 h-px bg-fluent-n5 dark:bg-white/10"></div>
                </div>
                <div class="grid grid-cols-1 gap-1.5">
                    <?php foreach (['hors_facture','transfer','personal','expense','other'] as $key): $t=$txTypes[$key]; ?>
                    <label class="flex items-center gap-3 p-2.5 rounded-xl border cursor-pointer hover:border-fluent-blue transition-colors <?= $t['bg'] ?> <?= $t['border'] ?>">
                        <input type="radio" name="new_type" value="<?= $key ?>" <?= $key==='hors_facture'?'checked':'' ?> class="text-fluent-blue flex-shrink-0">
                        <span class="text-lg flex-shrink-0"><?= $t['icon'] ?></span>
                        <div>
                            <div class="text-xs font-semibold <?= $t['color'] ?>"><?= $t['label'] ?></div>
                            <div class="text-[10px] text-fluent-n3"><?= $t['desc'] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex gap-2 pt-1">
                <button type="button" onclick="closeRecModal()"
                    class="flex-1 py-2.5 border border-fluent-n4 dark:border-white/20 rounded-xl text-sm text-fluent-n2 hover:bg-fluent-n6">Annuler</button>
                <button type="submit"
                    class="flex-1 py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk">Confirmer</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ RETYPE MODAL ═══════════════════════════════════════════ -->
<div id="retype-modal" class="fixed inset-0 z-[60] hidden flex items-end sm:items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-fxl w-full max-w-md">
        <div class="px-5 py-4 border-b border-fluent-n5 dark:border-white/10 flex items-center justify-between">
            <h3 class="font-semibold text-fluent-neutral text-sm">Classifier la transaction</h3>
            <button onclick="closeRetypeModal()" class="text-fluent-n3 hover:text-fluent-neutral w-8 h-8 flex items-center justify-center rounded-lg hover:bg-fluent-n6 text-lg">✕</button>
        </div>
        <form method="POST" class="px-5 py-4 space-y-3">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="retype">
            <input type="hidden" name="tid" id="retype-tid">
            <p class="text-xs text-fluent-n3">Choisissez la nature de ce mouvement pour votre suivi.</p>
            <div class="space-y-1.5">
                <?php foreach ($txTypes as $key => $t): ?>
                <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer hover:border-fluent-blue transition-colors <?= $t['bg'] ?> <?= $t['border'] ?>">
                    <input type="radio" name="transaction_type" value="<?= $key ?>" id="rt-<?= $key ?>" class="text-fluent-blue flex-shrink-0">
                    <span class="text-xl flex-shrink-0"><?= $t['icon'] ?></span>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold <?= $t['color'] ?>"><?= $t['label'] ?></div>
                        <div class="text-xs text-fluent-n3"><?= $t['desc'] ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-2 pt-1">
                <button type="button" onclick="closeRetypeModal()"
                    class="flex-1 py-2.5 border border-fluent-n4 dark:border-white/20 rounded-xl text-sm text-fluent-n2 hover:bg-fluent-n6">Annuler</button>
                <button type="submit"
                    class="flex-1 py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Type radio card highlight ─────────────────────────────────
document.querySelectorAll('.type-radio input[type="radio"]').forEach(r => {
    const syncBorder = () => {
        document.querySelectorAll('.type-radio').forEach(l => {
            const inp = l.querySelector('input');
            l.style.borderColor = inp.checked ? '#0078d4' : '';
            l.style.boxShadow   = inp.checked ? '0 0 0 1.5px #0078d4' : '';
        });
    };
    r.addEventListener('change', syncBorder);
});

// ── Reconcile modal ───────────────────────────────────────────
function openRecModal(id, amount, currentType) {
    document.getElementById('rec-tid').value = id;
    document.getElementById('rec-amount').textContent = 'Crédit : ' + formatMAD(amount);
    document.getElementById('rec-iid').value = '';
    const r = document.querySelector(`input[name="new_type"][value="${currentType||'hors_facture'}"]`);
    if (r) r.checked = true;
    document.getElementById('classify-section').style.opacity = '1';
    document.getElementById('classify-section').style.pointerEvents = '';
    document.getElementById('rec-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeRecModal() {
    document.getElementById('rec-modal').classList.add('hidden');
    document.body.style.overflow = '';
}
function toggleClassify() {
    const iid  = document.getElementById('rec-iid').value;
    const sect = document.getElementById('classify-section');
    sect.style.opacity       = iid ? '.35' : '1';
    sect.style.pointerEvents = iid ? 'none' : '';
}
document.getElementById('rec-modal')?.addEventListener('click', e => {
    if (e.target === document.getElementById('rec-modal')) closeRecModal();
});

// ── Retype modal ──────────────────────────────────────────────
function openRetypeModal(id, currentType) {
    document.getElementById('retype-tid').value = id;
    const r = document.getElementById('rt-' + (currentType || 'other'));
    if (r) r.checked = true;
    document.getElementById('retype-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeRetypeModal() {
    document.getElementById('retype-modal').classList.add('hidden');
    document.body.style.overflow = '';
}
document.getElementById('retype-modal')?.addEventListener('click', e => {
    if (e.target === document.getElementById('retype-modal')) closeRetypeModal();
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
