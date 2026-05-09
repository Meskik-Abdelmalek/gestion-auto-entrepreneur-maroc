<?php
require_once __DIR__ . '/includes/functions.php';
$db  = getDB();
$cfg = getConfig();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /invoices.php'); exit; }

$stmt = $db->prepare("SELECT * FROM ae_invoices WHERE id=?");
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { header('Location: /invoices.php'); exit; }

$lines = $db->prepare("SELECT * FROM ae_invoice_lines WHERE invoice_id=? ORDER BY sort_order");
$lines->execute([$id]);
$lines = $lines->fetchAll();

$pageName = 'Modifier Facture';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $num     = clean($_POST['invoice_number'] ?? '');
    $date    = clean($_POST['invoice_date'] ?? '');
    $due     = clean($_POST['due_date'] ?? '');
    $clientN = clean($_POST['client_name'] ?? '');
    $cat     = clean($_POST['category'] ?? 'Service');
    $status  = clean($_POST['status'] ?? 'En attente');
    $payDate = clean($_POST['payment_date'] ?? '');
    $payMode = clean($_POST['payment_method'] ?? '');
    $notes   = clean($_POST['notes'] ?? '');
    $linesIn = $_POST['lines'] ?? [];

    $total_ht = 0;
    foreach ($linesIn as $line) {
        $qty   = (float)($line['qty']   ?? 0);
        $price = (float)($line['price'] ?? 0);
        $total_ht += $qty * $price;
    }
    $quarter = getQuarter($date);
    $fy      = (int)date('Y', strtotime($date));

    if ($clientN && $total_ht > 0) {
        $db->prepare("UPDATE ae_invoices SET invoice_number=?,client_name=?,invoice_date=?,due_date=?,
            category=?,amount_ht=?,amount_ttc=?,status=?,payment_date=?,payment_method=?,quarter=?,fiscal_year=?,notes=?
            WHERE id=?")->execute([
            $num, $clientN, $date, $due ?: null, $cat, $total_ht, $total_ht,
            $status, $payDate ?: null, $payMode ?: null, $quarter, $fy, $notes, $id
        ]);

        // Replace lines
        $db->prepare("DELETE FROM ae_invoice_lines WHERE invoice_id=?")->execute([$id]);
        foreach ($linesIn as $i => $line) {
            $desc  = clean($line['desc'] ?? '');
            $qty   = (float)($line['qty'] ?? 0);
            $price = (float)($line['price'] ?? 0);
            if ($desc || $qty > 0) {
                $db->prepare("INSERT INTO ae_invoice_lines (invoice_id,description,quantity,unit_price,amount,sort_order) VALUES (?,?,?,?,?,?)")
                   ->execute([$id, $desc, $qty, $price, $qty * $price, $i]);
            }
        }

        flash('message', "Facture {$num} mise à jour !");
        header("Location: /invoice-view.php?id=$id"); exit;
    } else {
        $error = "Veuillez renseigner le client et au moins une ligne.";
    }
}

$clients = $db->query("SELECT * FROM ae_clients ORDER BY name")->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto">

<div class="flex items-center justify-between mb-5">
    <a href="/invoice-view.php?id=<?= $id ?>" class="fluent-btn flex items-center gap-1.5 px-3 py-2 text-sm border border-fluent-neutral-4 rounded-xl text-fluent-neutral-2 hover:bg-fluent-neutral-6 bg-white">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Annuler
    </a>
    <span class="text-xs text-fluent-neutral-3">Modification: <?= h($inv['invoice_number']) ?></span>
</div>

<?php if (!empty($error)): ?>
<div class="mb-4 px-4 py-3 bg-fluent-red-lt text-fluent-red rounded-xl text-sm border border-red-200"><?= h($error) ?></div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">

    <!-- Meta -->
    <div class="bg-white rounded-2xl shadow-fluent p-5 mb-4">
        <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
            <span class="w-6 h-6 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs font-bold">1</span>
            Informations Générales
        </h2>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-fluent-neutral-2 mb-1">N° Facture</label>
                <input name="invoice_number" value="<?= h($inv['invoice_number']) ?>" required
                    class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-neutral-2 mb-1">Date</label>
                <input type="date" name="invoice_date" value="<?= h($inv['invoice_date']) ?>" required
                    class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-neutral-2 mb-1">Échéance</label>
                <input type="date" name="due_date" value="<?= h($inv['due_date'] ?? '') ?>"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-neutral-2 mb-1">Catégorie</label>
                <select name="category" class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white">
                    <?php foreach (['Service','Commerce','Industrie'] as $c): ?>
                    <option <?= $inv['category']===$c ? 'selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Client -->
    <div class="bg-white rounded-2xl shadow-fluent p-5 mb-4">
        <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
            <span class="w-6 h-6 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs font-bold">2</span>
            Client
        </h2>
        <input name="client_name" list="clients-list" value="<?= h($inv['client_name']) ?>" required placeholder="Nom ou raison sociale"
            class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white">
        <datalist id="clients-list">
            <?php foreach ($clients as $cl): ?>
            <option value="<?= h($cl['name']) ?>">
            <?php endforeach; ?>
        </datalist>
    </div>

    <!-- Lines -->
    <div class="bg-white rounded-2xl shadow-fluent p-5 mb-4">
        <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center justify-between">
            <span class="flex items-center gap-2">
                <span class="w-6 h-6 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs font-bold">3</span>
                Lignes de Prestation
            </span>
            <button type="button" onclick="addLine()" class="text-xs text-fluent-blue font-medium hover:underline">+ Ajouter</button>
        </h2>

        <div class="hidden sm:grid grid-cols-12 gap-2 mb-2 px-1">
            <div class="col-span-6 text-xs font-medium text-fluent-neutral-3">Description</div>
            <div class="col-span-2 text-xs font-medium text-fluent-neutral-3 text-center">Qté</div>
            <div class="col-span-3 text-xs font-medium text-fluent-neutral-3 text-right">Prix Unitaire</div>
            <div class="col-span-1"></div>
        </div>

        <div id="lines-container" class="space-y-2">
            <?php foreach ($lines as $i => $l): ?>
            <div class="line-row grid grid-cols-12 gap-2 items-center">
                <div class="col-span-12 sm:col-span-6">
                    <input name="lines[<?= $i ?>][desc]" value="<?= h($l['description']) ?>" placeholder="Description…"
                        class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white">
                </div>
                <div class="col-span-4 sm:col-span-2">
                    <input type="number" name="lines[<?= $i ?>][qty]" value="<?= h($l['quantity']) ?>" min="0" step="0.01"
                        class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white text-center" oninput="calcTotal()">
                </div>
                <div class="col-span-7 sm:col-span-3">
                    <input type="number" name="lines[<?= $i ?>][price]" value="<?= h($l['unit_price']) ?>" min="0" step="0.01"
                        class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white text-right" oninput="calcTotal()">
                </div>
                <div class="col-span-1 flex justify-center">
                    <button type="button" onclick="removeLine(this)" class="text-fluent-neutral-4 hover:text-fluent-red">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($lines)): ?>
            <div class="line-row grid grid-cols-12 gap-2 items-center">
                <div class="col-span-12 sm:col-span-6"><input name="lines[0][desc]" placeholder="Description…" class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white"></div>
                <div class="col-span-4 sm:col-span-2"><input type="number" name="lines[0][qty]" value="1" min="0" step="0.01" class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white text-center" oninput="calcTotal()"></div>
                <div class="col-span-7 sm:col-span-3"><input type="number" name="lines[0][price]" placeholder="0.00" min="0" step="0.01" class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white text-right" oninput="calcTotal()"></div>
                <div class="col-span-1 flex justify-center"><button type="button" onclick="removeLine(this)" class="text-fluent-neutral-4 hover:text-fluent-red"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="flex justify-end mt-4 pt-4 border-t border-fluent-neutral-5">
            <div class="space-y-1 w-56">
                <div class="flex justify-between text-sm">
                    <span class="text-fluent-neutral-3">Total HT</span>
                    <span class="font-semibold text-fluent-neutral" id="total-ht"><?= money($inv['amount_ht']) ?></span>
                </div>
                <div class="flex justify-between text-sm font-bold pt-1 border-t border-fluent-neutral-4">
                    <span class="text-fluent-neutral">Total TTC</span>
                    <span class="text-fluent-blue" id="total-ttc"><?= money($inv['amount_ttc']) ?></span>
                </div>
                <div class="text-xs text-fluent-neutral-3 text-right" id="ir-preview"></div>
            </div>
        </div>
    </div>

    <!-- Payment -->
    <div class="bg-white rounded-2xl shadow-fluent p-5 mb-5">
        <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
            <span class="w-6 h-6 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs font-bold">4</span>
            Paiement & Statut
        </h2>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-fluent-neutral-2 mb-1">Statut</label>
                <select name="status" id="status-select" onchange="togglePayment(this.value)"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white">
                    <?php foreach (['En attente','Payé','Annulé'] as $s): ?>
                    <option <?= $inv['status']===$s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-neutral-2 mb-1">Mode de Paiement</label>
                <select name="payment_method" class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white">
                    <option value="">—</option>
                    <?php foreach (['Virement','Chèque','Espèces','CB','PayPal','Mobile Money'] as $m): ?>
                    <option <?= ($inv['payment_method']??'')===$m ? 'selected':'' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="pay-date-wrap" class="col-span-2 <?= $inv['status']!=='Payé' ? 'hidden' : '' ?>">
                <label class="block text-xs font-medium text-fluent-neutral-2 mb-1">Date de Paiement</label>
                <input type="date" name="payment_date" value="<?= h($inv['payment_date'] ?? date('Y-m-d')) ?>"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-fluent-neutral-2 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white resize-none"><?= h($inv['notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="flex gap-3 justify-end">
        <a href="/invoice-view.php?id=<?= $id ?>" class="fluent-btn px-5 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl text-fluent-neutral-2 hover:bg-fluent-neutral-6 bg-white">Annuler</a>
        <button type="submit" class="fluent-btn px-6 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold shadow-fluent hover:bg-fluent-blue-dk">
            Sauvegarder les Modifications
        </button>
    </div>
</form>
</div>

<script>
let lineIndex = <?= max(count($lines), 1) ?>;
const irRate = <?= json_encode((float)($cfg['ir_rate_services'] ?? 0.01)) ?>;

function addLine() {
    const i = lineIndex++;
    const div = document.createElement('div');
    div.className = 'line-row grid grid-cols-12 gap-2 items-center';
    div.innerHTML = `
        <div class="col-span-12 sm:col-span-6"><input name="lines[${i}][desc]" placeholder="Description…" class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white"></div>
        <div class="col-span-4 sm:col-span-2"><input type="number" name="lines[${i}][qty]" value="1" min="0" step="0.01" class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white text-center" oninput="calcTotal()"></div>
        <div class="col-span-7 sm:col-span-3"><input type="number" name="lines[${i}][price]" placeholder="0.00" min="0" step="0.01" class="w-full px-3 py-2.5 text-sm border border-fluent-neutral-4 rounded-xl fluent-input bg-white text-right" oninput="calcTotal()"></div>
        <div class="col-span-1 flex justify-center"><button type="button" onclick="removeLine(this)" class="text-fluent-neutral-4 hover:text-fluent-red"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>`;
    document.getElementById('lines-container').appendChild(div);
}
function removeLine(btn) {
    if (document.querySelectorAll('.line-row').length > 1) { btn.closest('.line-row').remove(); calcTotal(); }
}
function calcTotal() {
    let total = 0;
    document.querySelectorAll('.line-row').forEach(row => {
        const qty   = parseFloat(row.querySelector('[name*="[qty]"]')?.value) || 0;
        const price = parseFloat(row.querySelector('[name*="[price]"]')?.value) || 0;
        total += qty * price;
    });
    const fmt = n => new Intl.NumberFormat('fr-MA', {minimumFractionDigits:2}).format(n) + ' MAD';
    document.getElementById('total-ht').textContent  = fmt(total);
    document.getElementById('total-ttc').textContent = fmt(total);
    document.getElementById('ir-preview').textContent = total > 0 ? `IR estimé: ${fmt(total * irRate)}` : '';
}
function togglePayment(status) {
    document.getElementById('pay-date-wrap').classList.toggle('hidden', status !== 'Payé');
}
calcTotal();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
