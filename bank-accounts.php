<?php
// ── Bank Accounts Management (Multi-Account v2.1) ─────────────
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Comptes Bancaires';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'save';

    // ── Delete account ─────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $st = $db->prepare("SELECT COUNT(*) FROM ae_bank_transactions WHERE account_id=?");
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('message', 'Impossible : ce compte contient des transactions.', 'error');
        } else {
            $db->prepare("DELETE FROM ae_bank_accounts WHERE id=? AND is_default=0")->execute([$id]);
            flash('message', 'Compte supprimé.');
        }
        header('Location: /bank-accounts.php'); exit;
    }

    // ── Set default ────────────────────────────────────────────
    if ($action === 'set_default') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE ae_bank_accounts SET is_default=0")->execute([]);
        $db->prepare("UPDATE ae_bank_accounts SET is_default=1 WHERE id=?")->execute([$id]);
        flash('message', 'Compte par défaut mis à jour.');
        header('Location: /bank-accounts.php'); exit;
    }

    // ── Transfer between accounts ──────────────────────────────
    if ($action === 'transfer') {
        $from   = (int)$_POST['from_account'];
        $to     = (int)$_POST['to_account'];
        $amount = (float)$_POST['amount'];
        $date   = clean($_POST['transfer_date'] ?? date('Y-m-d'));
        $desc   = clean($_POST['description']   ?? 'Virement interne');
        $fy     = (int)date('Y', strtotime($date));

        if ($from === $to || $amount <= 0) {
            flash('message', 'Paramètres de virement invalides.', 'error');
            header('Location: /bank-accounts.php'); exit;
        }

        $db->beginTransaction();
        try {
            // Debit from source
            $db->prepare("INSERT INTO ae_bank_transactions (account_id, transaction_date, description, credit, debit, fiscal_year, notes) VALUES (?,?,?,0,?,?,?)")
               ->execute([$from, $date, $desc . ' → sortant', $amount, $fy, 'Virement interne']);
            $fromTx = (int)$db->lastInsertId();

            // Credit to destination
            $db->prepare("INSERT INTO ae_bank_transactions (account_id, transaction_date, description, credit, debit, fiscal_year, notes) VALUES (?,?,?,?,0,?,?)")
               ->execute([$to, $date, $desc . ' → entrant', $amount, $fy, 'Virement interne']);
            $toTx = (int)$db->lastInsertId();

            // Log transfer
            $db->prepare("INSERT INTO ae_bank_transfers (from_account_id,to_account_id,amount,transfer_date,description,from_tx_id,to_tx_id) VALUES (?,?,?,?,?,?,?)")
               ->execute([$from, $to, $amount, $date, $desc, $fromTx, $toTx]);

            $db->commit();
            flash('message', 'Virement enregistré.');
        } catch (\Exception $e) {
            $db->rollBack();
            flash('message', 'Erreur lors du virement : ' . $e->getMessage(), 'error');
        }
        header('Location: /bank-accounts.php'); exit;
    }

    // ── Save / Create account ──────────────────────────────────
    $id      = (int)($_POST['id'] ?? 0);
    $name    = clean($_POST['name']     ?? '');
    $type    = clean($_POST['type']     ?? 'bank');
    $bank    = clean($_POST['bank_name']?? '');
    $rib     = clean($_POST['rib']      ?? '');
    $opening = (float)($_POST['opening_balance'] ?? 0);
    $color   = clean($_POST['color']    ?? '#0078d4');
    $notes   = clean($_POST['notes']    ?? '');

    if (!$name) { flash('message', 'Nom du compte requis.', 'error'); header('Location: /bank-accounts.php'); exit; }

    if ($id) {
        $db->prepare("UPDATE ae_bank_accounts SET name=?,type=?,bank_name=?,rib=?,opening_balance=?,color=?,notes=? WHERE id=?")
           ->execute([$name,$type,$bank,$rib,$opening,$color,$notes,$id]);
        flash('message', "Compte « $name » mis à jour.");
    } else {
        $db->prepare("INSERT INTO ae_bank_accounts (name,type,bank_name,rib,opening_balance,color,notes) VALUES (?,?,?,?,?,?,?)")
           ->execute([$name,$type,$bank,$rib,$opening,$color,$notes]);
        flash('message', "Compte « $name » créé.");
    }
    header('Location: /bank-accounts.php'); exit;
}

// ── Fetch all accounts with running balance ───────────────────
$accounts = $db->query("SELECT * FROM ae_bank_accounts ORDER BY sort_order, id")->fetchAll();

foreach ($accounts as &$acc) {
    $st = $db->prepare("SELECT COALESCE(SUM(credit),0)-COALESCE(SUM(debit),0) FROM ae_bank_transactions WHERE account_id=?");
    $st->execute([$acc['id']]);
    $acc['balance'] = (float)$acc['opening_balance'] + (float)$st->fetchColumn();
    $st2 = $db->prepare("SELECT COUNT(*) FROM ae_bank_transactions WHERE account_id=?");
    $st2->execute([$acc['id']]);
    $acc['tx_count'] = (int)$st2->fetchColumn();
}
unset($acc);

$editId  = (int)($_GET['edit'] ?? 0);
$editAcc = null;
if ($editId) {
    foreach ($accounts as $a) if ($a['id'] === $editId) { $editAcc = $a; break; }
}

$flash = flash('message');
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium <?= $flash['type']==='error'?'bg-red-50 text-red-700 border border-red-200':'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= h($flash['msg']) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-lg font-bold text-fluent-neutral">Comptes & Portefeuilles</h1>
        <p class="text-xs text-fluent-n3 mt-0.5">Gérez vos comptes bancaires, e-wallets et caisse</p>
    </div>
    <button onclick="document.getElementById('new-acc-modal').classList.remove('hidden')"
        class="btn-f flex items-center gap-2 px-4 py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk shadow-f">
        + Nouveau compte
    </button>
</div>

<!-- Accounts grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
<?php foreach ($accounts as $acc): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5 relative overflow-hidden">
    <!-- Color accent -->
    <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl" style="background:<?= h($acc['color']) ?>"></div>

    <div class="flex items-start justify-between mt-1">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white text-sm font-bold shadow-sm"
                style="background:<?= h($acc['color']) ?>">
                <?= $acc['type']==='cash'?'💵':($acc['type']==='ewallet'?'📱':'🏦') ?>
            </div>
            <div>
                <div class="font-semibold text-sm text-fluent-neutral"><?= h($acc['name']) ?></div>
                <div class="text-xs text-fluent-n3"><?= h($acc['bank_name']) ?></div>
            </div>
        </div>
        <?php if ($acc['is_default']): ?>
        <span class="text-[10px] px-2 py-0.5 bg-fluent-blue text-white rounded-full font-medium">Défaut</span>
        <?php endif; ?>
    </div>

    <div class="mt-4 mb-3">
        <div class="text-xs text-fluent-n3 mb-0.5">Solde actuel</div>
        <div class="text-2xl font-bold <?= $acc['balance']>=0?'text-fluent-neutral':'text-fluent-red' ?>">
            <?= money($acc['balance']) ?>
        </div>
        <?php if ($acc['opening_balance'] != 0): ?>
        <div class="text-[10px] text-fluent-n3">Solde d'ouverture : <?= money($acc['opening_balance']) ?></div>
        <?php endif; ?>
    </div>

    <div class="flex items-center gap-1 flex-wrap">
        <a href="/bank.php?account=<?= $acc['id'] ?>"
            class="btn-f px-3 py-1.5 text-xs bg-fluent-blue text-white rounded-lg hover:bg-fluent-blue-dk">
            Transactions
        </a>
        <a href="/bank-import.php?account=<?= $acc['id'] ?>"
            class="btn-f px-3 py-1.5 text-xs border border-fluent-n4 dark:border-white/20 rounded-lg text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6">
            Import CSV
        </a>
        <?php if (!$acc['is_default']): ?>
        <form method="POST" class="inline">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="set_default">
            <input type="hidden" name="id" value="<?= $acc['id'] ?>">
            <button type="submit" class="btn-f px-3 py-1.5 text-xs border border-fluent-n4 dark:border-white/20 rounded-lg text-fluent-n2 hover:bg-fluent-n6">
                Défaut
            </button>
        </form>
        <?php endif; ?>
        <a href="/bank-accounts.php?edit=<?= $acc['id'] ?>"
            class="btn-f px-2 py-1.5 text-xs border border-fluent-n4 dark:border-white/20 rounded-lg text-fluent-n2 hover:bg-fluent-n6">✏️</a>
        <?php if (!$acc['is_default'] && $acc['tx_count'] == 0): ?>
        <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce compte ?')">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $acc['id'] ?>">
            <button type="submit" class="btn-f px-2 py-1.5 text-xs border border-red-200 rounded-lg text-fluent-red hover:bg-red-50">🗑️</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Transfer form -->
<?php if (count($accounts) >= 2): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5 mb-6">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
        <span class="text-lg">↔️</span> Virement entre comptes
    </h2>
    <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="transfer">
        <div>
            <label class="block text-xs font-medium text-fluent-n2 mb-1">De</label>
            <select name="from_account" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
                <?php foreach ($accounts as $a): ?>
                <option value="<?= $a['id'] ?>"><?= h($a['name']) ?> (<?= money($a['balance']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-fluent-n2 mb-1">Vers</label>
            <select name="to_account" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
                <?php foreach ($accounts as $a): ?>
                <option value="<?= $a['id'] ?>" <?= count($accounts)>1&&$a===end($accounts)?'selected':'' ?>><?= h($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-fluent-n2 mb-1">Montant *</label>
            <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
        </div>
        <div>
            <label class="block text-xs font-medium text-fluent-n2 mb-1">Date *</label>
            <input type="date" name="transfer_date" value="<?= date('Y-m-d') ?>" required
                class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
        </div>
        <div>
            <button type="submit" class="w-full btn-f px-4 py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk">
                Transférer
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- New / Edit account modal -->
<div id="new-acc-modal" class="<?= $editAcc ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-sm text-fluent-neutral"><?= $editAcc ? 'Modifier le compte' : 'Nouveau compte' ?></h2>
            <button onclick="document.getElementById('new-acc-modal').classList.add('hidden')" class="text-fluent-n3 hover:text-fluent-neutral">✕</button>
        </div>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="save">
            <?php if ($editAcc): ?><input type="hidden" name="id" value="<?= $editAcc['id'] ?>"><?php endif; ?>

            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Nom du compte *</label>
                <input type="text" name="name" required value="<?= h($editAcc['name']??'') ?>" placeholder="Ex: CIH Principal, CashPlus, Caisse"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Type</label>
                    <select name="type" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
                        <?php foreach (['bank'=>'Compte Bancaire','ewallet'=>'E-Wallet / Mobile','cash'=>'Caisse Espèces'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= ($editAcc['type']??'bank')===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Banque / Opérateur</label>
                    <select name="bank_name" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
                        <?php foreach ([''=>'—','CIH Bank'=>'CIH Bank','Attijariwafa Bank'=>'Attijariwafa Bank','BMCE Bank of Africa'=>'BMCE Bank of Africa','OCP (Barid Bank)'=>'OCP (Barid Bank)','CashPlus'=>'CashPlus','Banque Populaire'=>'Banque Populaire','BMCI'=>'BMCI','Société Générale'=>'Société Générale','CFG Bank'=>'CFG Bank','Autre'=>'Autre'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= ($editAcc['bank_name']??'')===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">RIB / IBAN</label>
                <input type="text" name="rib" value="<?= h($editAcc['rib']??'') ?>" placeholder="Ex: 123456789012345678901234"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp font-mono">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Solde d'ouverture</label>
                    <input type="number" step="0.01" name="opening_balance" value="<?= h($editAcc['opening_balance']??0) ?>"
                        class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
                </div>
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Couleur</label>
                    <input type="color" name="color" value="<?= h($editAcc['color']??'#0078d4') ?>"
                        class="w-full h-10 px-1 py-1 border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 cursor-pointer">
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 btn-f py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk">
                    <?= $editAcc ? 'Enregistrer' : 'Créer le compte' ?>
                </button>
                <button type="button" onclick="document.getElementById('new-acc-modal').classList.add('hidden')"
                    class="px-4 py-2.5 border border-fluent-n4 dark:border-white/20 rounded-xl text-sm text-fluent-n2 hover:bg-fluent-n6">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
