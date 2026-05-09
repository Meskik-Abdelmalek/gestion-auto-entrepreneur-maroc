<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Modifier Devis';
$db = getDB(); $cfg = getConfig();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /quotes.php'); exit; }

$st = $db->prepare("SELECT * FROM ae_quotes WHERE id=?"); $st->execute([$id]); $q = $st->fetch();
if (!$q) { header('Location: /quotes.php'); exit; }

$lines = $db->prepare("SELECT * FROM ae_quote_lines WHERE quote_id=? ORDER BY sort_order");
$lines->execute([$id]); $lines = $lines->fetchAll();
$activities = getActivities();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $num      = clean($_POST['quote_number'] ?? '');
    $date     = clean($_POST['quote_date']   ?? '');
    $validity = (int)($_POST['validity_days'] ?? 30);
    $validUntil = date('Y-m-d', strtotime("+$validity days", strtotime($date)));
    $clientN  = clean($_POST['client_name']  ?? '');
    $cat      = clean($_POST['category']     ?? 'Service');
    $activity = clean($_POST['activity']     ?? '');
    $status   = clean($_POST['status']       ?? 'Brouillon');
    $notes    = clean($_POST['notes']        ?? '');
    $linesIn  = $_POST['lines'] ?? [];

    $total_ht = 0;
    foreach ($linesIn as $l) $total_ht += (float)($l['qty']??0) * (float)($l['price']??0);

    if ($clientN && $total_ht > 0) {
        $db->prepare("UPDATE ae_quotes SET quote_number=?,client_name=?,quote_date=?,valid_until=?,category=?,activity=?,amount_ht=?,amount_ttc=?,status=?,notes=? WHERE id=?")
           ->execute([$num,$clientN,$date,$validUntil,$cat,$activity,$total_ht,$total_ht,$status,$notes,$id]);

        $db->prepare("DELETE FROM ae_quote_lines WHERE quote_id=?")->execute([$id]);
        foreach ($linesIn as $i => $l) {
            $desc=clean($l['desc']??''); $qty=(float)($l['qty']??0); $price=(float)($l['price']??0);
            if ($desc||$qty>0) $db->prepare("INSERT INTO ae_quote_lines (quote_id,description,quantity,unit_price,amount,sort_order) VALUES (?,?,?,?,?,?)")->execute([$id,$desc,$qty,$price,$qty*$price,$i]);
        }
        flash('message',"Devis $num mis à jour !"); header("Location: /quote-view.php?id=$id"); exit;
    }
    $error = "Renseignez le client et au moins une ligne.";
}

$clients = $db->query("SELECT * FROM ae_clients ORDER BY name")->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto">

<div class="flex items-center justify-between mb-5">
    <a href="/quote-view.php?id=<?= $id ?>" class="btn-f flex items-center gap-1.5 px-3 py-2 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6 dark:hover:bg-white/10 bg-white dark:bg-gray-800">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>Annuler
    </a>
    <span class="text-xs text-fluent-n3">Modification: <?= h($q['quote_number']) ?></span>
</div>

<?php if (!empty($error)): ?>
<div class="mb-4 px-4 py-3 bg-fluent-red-lt dark:bg-red-900/30 text-fluent-red rounded-xl text-sm border border-red-200"><?= h($error) ?></div>
<?php endif; ?>

<form method="POST" class="space-y-4">
<input type="hidden" name="_csrf" value="<?= $csrf ?>">

<!-- Step 1 -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
        <span class="w-6 h-6 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs font-bold">1</span>
        Informations Générales
    </h2>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="block text-xs font-medium text-fluent-n2 mb-1">N° Devis</label>
            <input name="quote_number" value="<?= h($q['quote_number']) ?>" required class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 font-mono"></div>
        <div><label class="block text-xs font-medium text-fluent-n2 mb-1">Date</label>
            <input type="date" name="quote_date" value="<?= h($q['quote_date']) ?>" required class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700"></div>
        <div><label class="block text-xs font-medium text-fluent-n2 mb-1">Validité (jours)</label>
            <?php
            $daysLeft = $q['valid_until'] ? (int)ceil((strtotime($q['valid_until'])-strtotime($q['quote_date']))/86400) : 30;
            ?>
            <input type="number" name="validity_days" value="<?= h($daysLeft) ?>" min="1" max="365" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center"></div>
        <div><label class="block text-xs font-medium text-fluent-n2 mb-1">Catégorie</label>
            <select name="category" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                <?php foreach (['Service','Commerce','Industrie'] as $c): ?>
                <option <?= $q['category']===$c?'selected':'' ?>><?= $c ?></option>
                <?php endforeach; ?></select></div>
        <?php if ($activities): ?>
        <div class="col-span-2"><label class="block text-xs font-medium text-fluent-n2 mb-1">Activité Professionnelle</label>
            <select name="activity" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                <option value="">— Sélectionner —</option>
                <?php foreach ($activities as $act): ?><option <?= $q['activity']===$act?'selected':'' ?>><?= h($act) ?></option><?php endforeach; ?>
            </select></div>
        <?php endif; ?>
        <div class="<?= $activities?'':'col-span-2' ?>"><label class="block text-xs font-medium text-fluent-n2 mb-1">Statut</label>
            <select name="status" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                <?php foreach (['Brouillon','Envoyé','Accepté','Refusé','Expiré'] as $s): ?>
                <option <?= $q['status']===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?></select></div>
    </div>
</div>

<!-- Step 2: Client -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
        <span class="w-6 h-6 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs font-bold">2</span>
        Client
    </h2>
    <div class="relative">
        <input name="client_name" id="client-inp" value="<?= h($q['client_name']) ?>" autocomplete="off" required
            oninput="filterClients(this.value)" onfocus="showDrop()" onblur="setTimeout(hideDrop,200)"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
        <div id="client-drop" class="hidden absolute top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 rounded-xl shadow-fl border border-fluent-n5 dark:border-white/10 z-30 max-h-48 overflow-y-auto">
            <?php foreach ($clients as $cl): ?>
            <div class="px-4 py-2.5 hover:bg-fluent-n7 dark:hover:bg-white/5 cursor-pointer client-opt" data-name="<?= h($cl['name']) ?>" onclick="pickClient('<?= h(addslashes($cl['name'])) ?>')">
                <div class="text-sm font-medium text-fluent-neutral"><?= h($cl['name']) ?></div>
                <div class="text-xs text-fluent-n3"><?= h($cl['city']??'') ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Step 3: Lines -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center justify-between">
        <span class="flex items-center gap-2"><span class="w-6 h-6 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs font-bold">3</span>Prestations</span>
        <button type="button" onclick="addLine()" class="text-xs text-fluent-blue font-semibold hover:underline flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Ajouter</button>
    </h2>
    <div class="hidden sm:grid grid-cols-12 gap-2 mb-2 px-1 text-xs font-medium text-fluent-n3">
        <div class="col-span-6">Description</div><div class="col-span-2 text-center">Qté</div><div class="col-span-3 text-right">Prix Unit.</div><div class="col-span-1"></div>
    </div>
    <div id="lines-wrap" class="space-y-2">
        <?php foreach ($lines as $i => $l): ?>
        <div class="line-row grid grid-cols-12 gap-2 items-center">
            <div class="col-span-12 sm:col-span-6"><input name="lines[<?= $i ?>][desc]" value="<?= h($l['description']) ?>" placeholder="Description…" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3"></div>
            <div class="col-span-4 sm:col-span-2"><input type="number" name="lines[<?= $i ?>][qty]" value="<?= h($l['quantity']) ?>" min="0" step="0.01" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center" oninput="calcTotal()"></div>
            <div class="col-span-7 sm:col-span-3"><input type="number" name="lines[<?= $i ?>][price]" value="<?= h($l['unit_price']) ?>" min="0" step="0.01" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-right" oninput="calcTotal()"></div>
            <div class="col-span-1 flex justify-center"><button type="button" onclick="removeLine(this)" class="text-fluent-n4 hover:text-fluent-red"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
        </div>
        <?php endforeach; if (empty($lines)): ?>
        <div class="line-row grid grid-cols-12 gap-2 items-center">
            <div class="col-span-12 sm:col-span-6"><input name="lines[0][desc]" placeholder="Description…" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3"></div>
            <div class="col-span-4 sm:col-span-2"><input type="number" name="lines[0][qty]" value="1" min="0" step="0.01" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center" oninput="calcTotal()"></div>
            <div class="col-span-7 sm:col-span-3"><input type="number" name="lines[0][price]" placeholder="0.00" min="0" step="0.01" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-right" oninput="calcTotal()"></div>
            <div class="col-span-1 flex justify-center"><button type="button" onclick="removeLine(this)" class="text-fluent-n4 hover:text-fluent-red"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
        </div>
        <?php endif; ?>
    </div>
    <div class="flex flex-col items-end gap-1 mt-4 pt-4 border-t border-fluent-n5 dark:border-white/10">
        <div class="flex justify-between w-56 text-sm"><span class="text-fluent-n3">Total HT</span><span class="font-semibold text-fluent-neutral" id="ttl-ht"><?= money($q['amount_ht']) ?></span></div>
        <div class="flex justify-between w-56 pt-2 border-t border-fluent-n4 dark:border-white/20 mt-1 text-base font-bold">
            <span class="text-fluent-neutral">Total TTC</span><span class="text-fluent-blue" id="ttl-ttc"><?= money($q['amount_ttc']) ?></span>
        </div>
    </div>
</div>

<!-- Notes -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-3 flex items-center gap-2">
        <span class="w-6 h-6 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs font-bold">4</span>
        Notes & Conditions
    </h2>
    <textarea name="notes" rows="3" placeholder="Conditions particulières…" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 resize-none placeholder-fluent-n3"><?= h($q['notes']??'') ?></textarea>
</div>

<div class="flex gap-3 justify-end pb-4">
    <a href="/quote-view.php?id=<?= $id ?>" class="btn-f px-5 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6 dark:hover:bg-white/10 bg-white dark:bg-gray-800">Annuler</a>
    <button type="submit" class="btn-f px-6 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold shadow-f hover:bg-fluent-blue-dk">Sauvegarder</button>
</div>
</form>
</div>

<script>
let li = <?= max(count($lines), 1) ?>;
function fmt(n){return new Intl.NumberFormat('fr-MA',{minimumFractionDigits:2}).format(n)+' MAD';}
function calcTotal(){
    let t=0; document.querySelectorAll('.line-row').forEach(r=>{t+=(parseFloat(r.querySelector('[name*="[qty]"]')?.value)||0)*(parseFloat(r.querySelector('[name*="[price]"]')?.value)||0);});
    document.getElementById('ttl-ht').textContent=fmt(t); document.getElementById('ttl-ttc').textContent=fmt(t);
}
function addLine(){
    const i=li++; const d=document.createElement('div'); d.className='line-row grid grid-cols-12 gap-2 items-center';
    d.innerHTML=`<div class="col-span-12 sm:col-span-6"><input name="lines[${i}][desc]" placeholder="Description…" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3"></div><div class="col-span-4 sm:col-span-2"><input type="number" name="lines[${i}][qty]" value="1" min="0" step="0.01" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center" oninput="calcTotal()"></div><div class="col-span-7 sm:col-span-3"><input type="number" name="lines[${i}][price]" placeholder="0.00" min="0" step="0.01" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-right" oninput="calcTotal()"></div><div class="col-span-1 flex justify-center"><button type="button" onclick="removeLine(this)" class="text-fluent-n4 hover:text-fluent-red"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>`;
    document.getElementById('lines-wrap').appendChild(d); d.querySelector('input').focus();
}
function removeLine(b){if(document.querySelectorAll('.line-row').length>1){b.closest('.line-row').remove();calcTotal();}}
function filterClients(q){const opts=document.querySelectorAll('.client-opt');let a=false;opts.forEach(o=>{const m=o.dataset.name.toLowerCase().includes(q.toLowerCase());o.style.display=m?'':'none';if(m)a=true;});document.getElementById('client-drop').classList.toggle('hidden',!q||!a);}
function showDrop(){document.getElementById('client-drop').classList.remove('hidden');}
function hideDrop(){document.getElementById('client-drop').classList.add('hidden');}
function pickClient(n){document.getElementById('client-inp').value=n;hideDrop();}
calcTotal();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
