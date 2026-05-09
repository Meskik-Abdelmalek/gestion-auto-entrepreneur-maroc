<?php
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Paramètres';
$db = getDB();
$user = currentUser();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verifyCsrf();
    $result = changePassword($user['id'], $_POST['current_pw']??'', $_POST['new_pw']??'');
    flash('message', $result['ok'] ? 'Mot de passe modifié !' : $result['error'], $result['ok'] ? 'success' : 'error');
    header('Location: /settings.php#security'); exit;
}

// Handle config save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['change_password'])) {
    verifyCsrf();
    $textFields  = ['owner_name','email','ice','if_fiscal','tp','cnss_phone','address','bank_rib',
                    'currency','activity_1','activity_2','activity_3','quote_footer_text','invoice_footer_text'];
    $intFields   = ['fiscal_year','quote_validity_days'];
    $rateFields  = ['ir_rate_services','ir_rate_commerce','alert_yellow','alert_orange','alert_red'];
    $floatFields = ['ceiling_services','ceiling_commerce','cnss_monthly'];

    $set=[]; $params=[];
    foreach ($textFields  as $f) if(isset($_POST[$f])){$set[]="$f=?";$params[]=clean($_POST[$f]);}
    foreach ($intFields   as $f) if(isset($_POST[$f])){$set[]="$f=?";$params[]=(int)$_POST[$f];}
    foreach ($rateFields  as $f) if(isset($_POST[$f])){$set[]="$f=?";$params[]=(float)$_POST[$f]/100;}
    foreach ($floatFields as $f) if(isset($_POST[$f])){$set[]="$f=?";$params[]=(float)$_POST[$f];}

    if ($set) { $db->prepare("UPDATE ae_config SET ".implode(',',$set)." WHERE id=1")->execute($params); flash('message','Paramètres sauvegardés !'); }
    header('Location: /settings.php'); exit;
}

$cfg = getConfig();
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-4">

<!-- ── Identity ─────────────────────────────────────────────── -->
<form method="POST" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
<input type="hidden" name="_csrf" value="<?= $csrf ?>">
<h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
    <span class="w-7 h-7 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs">👤</span>
    Identité Auto-Entrepreneur
</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <?php
    $inp = fn($n,$l,$ph,$t='text') => "
    <div>
        <label class='block text-xs font-medium text-fluent-n2 mb-1'>$l</label>
        <input type='$t' name='$n' value='".h($cfg[$n]??'')."' placeholder='$ph'
            class='w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3'>
    </div>";
    echo $inp('owner_name','Nom complet *','MESKIK Abdelmalek');
    echo $inp('email','Email','email@example.com','email');
    echo $inp('ice','ICE','003938004000054');
    echo $inp('if_fiscal','Identifiant Fiscal (IF)','12345678');
    echo $inp('tp','Taxe Professionnelle (TP)','12345678');
    echo $inp('cnss_phone','Téléphone / N° CNSS','+212 6XX XXX XXX');
    ?>
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Adresse</label>
        <textarea name="address" rows="2" placeholder="Rue, Ville, Maroc"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 resize-none placeholder-fluent-n3"><?= h($cfg['address']??'') ?></textarea>
    </div>
    <div class="sm:col-span-2"><?= $inp('bank_rib','RIB Bancaire','007 123 456789012345678901 67') ?></div>
</div>
<div class="flex justify-end mt-4"><button type="submit" class="btn-f px-5 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold hover:bg-fluent-blue-dk shadow-f">Sauvegarder</button></div>
</form>

<!-- ── Activités professionnelles ───────────────────────────── -->
<form method="POST" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
<input type="hidden" name="_csrf" value="<?= $csrf ?>">
<h2 class="font-semibold text-sm text-fluent-neutral mb-2 flex items-center gap-2">
    <span class="w-7 h-7 bg-amber-100 dark:bg-amber-900/30 rounded-lg flex items-center justify-center text-amber-600 text-xs">🎯</span>
    Activités Professionnelles
</h2>
<div class="mb-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
    <p class="text-xs text-amber-800 dark:text-amber-300 font-medium mb-2">ℹ️ Comment trouver vos activités officielles ?</p>
    <ol class="text-xs text-amber-700 dark:text-amber-400 space-y-1 list-decimal list-inside">
        <li>Connectez-vous à votre espace AE sur <a href="https://rn.ae.gov.ma/" target="_blank" rel="noopener" class="font-semibold underline hover:text-amber-900">rn.ae.gov.ma</a></li>
        <li>Accédez à votre profil / fiche d'entreprise</li>
        <li>Copiez exactement le libellé de votre <strong>Activité Professionnelle</strong> (ex: "Développement informatique")</li>
        <li>Renseignez-la ci-dessous — elle apparaîtra sur vos factures et devis</li>
    </ol>
    <p class="text-[11px] text-amber-600 dark:text-amber-500 mt-2">Vous pouvez avoir jusqu'à <strong>3 activités</strong> enregistrées auprès de l'AE.</p>
</div>
<div class="space-y-3">
    <?php for ($i=1;$i<=3;$i++): ?>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">
            Activité <?= $i ?> <?= $i===1?'<span class="text-fluent-n3">(principale)</span>':'' ?>
        </label>
        <div class="relative">
            <input name="activity_<?= $i ?>" value="<?= h($cfg["activity_$i"]??'') ?>"
                placeholder="Ex: Développement logiciel, Conseil en informatique…"
                class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
            <?php if ($cfg["activity_$i"]??''): ?>
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-fluent-green text-xs">✓</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endfor; ?>
</div>
<div class="flex justify-end mt-4"><button type="submit" class="btn-f px-5 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold hover:bg-fluent-blue-dk shadow-f">Sauvegarder</button></div>
</form>

<!-- ── Fiscal parameters ─────────────────────────────────────── -->
<form method="POST" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
<input type="hidden" name="_csrf" value="<?= $csrf ?>">
<h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
    <span class="w-7 h-7 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center text-purple-600 text-xs font-bold">📊</span>
    Paramètres Fiscaux
</h2>
<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Taux IR Services (%)</label>
        <input type="number" name="ir_rate_services" value="<?= h(round(($cfg['ir_rate_services']??0.01)*100,2)) ?>" step="0.01" min="0" max="100"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
        <p class="text-[10px] text-fluent-n3 mt-0.5">Taux légal : 1%</p>
    </div>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Taux IR Commerce (%)</label>
        <input type="number" name="ir_rate_commerce" value="<?= h(round(($cfg['ir_rate_commerce']??0.005)*100,2)) ?>" step="0.01" min="0" max="100"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
        <p class="text-[10px] text-fluent-n3 mt-0.5">Taux légal : 0.5%</p>
    </div>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Plafond Services (MAD)</label>
        <input type="number" name="ceiling_services" value="<?= h($cfg['ceiling_services']??200000) ?>" step="1000"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
    </div>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Plafond Commerce (MAD)</label>
        <input type="number" name="ceiling_commerce" value="<?= h($cfg['ceiling_commerce']??500000) ?>" step="1000"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
    </div>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">CNSS Mensuel (MAD)</label>
        <input type="number" name="cnss_monthly" value="<?= h($cfg['cnss_monthly']??100) ?>" step="1"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
    </div>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Exercice Fiscal Actif</label>
        <select name="fiscal_year" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
            <?php for ($y=(int)date('Y')+1;$y>=2020;$y--): ?>
            <option value="<?= $y ?>" <?= $y===(int)($cfg['fiscal_year']??date('Y'))?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-span-2">
        <p class="text-xs font-semibold text-fluent-n2 mb-2">Seuils d'alerte plafond</p>
        <div class="grid grid-cols-3 gap-3">
            <?php foreach ([['alert_yellow','🟡 Attention (%)',75],['alert_orange','🟠 Urgent (%)',85],['alert_red','🔴 Critique (%)',95]] as [$n,$l,$d]): ?>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1"><?= $l ?></label>
                <input type="number" name="<?= $n ?>" value="<?= h(round(($cfg[$n]??$d/100)*100)) ?>" min="1" max="100"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="flex justify-end mt-4"><button type="submit" class="btn-f px-5 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold hover:bg-fluent-blue-dk shadow-f">Sauvegarder</button></div>
</form>

<!-- ── Quote & Invoice settings ──────────────────────────────── -->
<form method="POST" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
<input type="hidden" name="_csrf" value="<?= $csrf ?>">
<h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
    <span class="w-7 h-7 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center text-fluent-green text-xs">📋</span>
    Factures & Devis
</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Validité devis par défaut (jours)</label>
        <input type="number" name="quote_validity_days" value="<?= h($cfg['quote_validity_days']??30) ?>" min="1" max="365"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center">
    </div>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Devise</label>
        <select name="currency" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
            <?php foreach (['MAD','EUR','USD'] as $c): ?>
            <option value="<?= $c ?>" <?= ($cfg['currency']??'MAD')===$c?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Pied de page des Devis</label>
        <textarea name="quote_footer_text" rows="2" placeholder="Ex: Devis valable sous réserve d'acceptation dans le délai indiqué…"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 resize-none placeholder-fluent-n3"><?= h($cfg['quote_footer_text']??'') ?></textarea>
    </div>
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Pied de page des Factures</label>
        <textarea name="invoice_footer_text" rows="2" placeholder="Ex: Paiement à 30 jours. Pénalités en cas de retard selon Art. 15…"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 resize-none placeholder-fluent-n3"><?= h($cfg['invoice_footer_text']??'') ?></textarea>
    </div>
</div>
<div class="flex justify-end mt-4"><button type="submit" class="btn-f px-5 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold hover:bg-fluent-blue-dk shadow-f">Sauvegarder</button></div>
</form>

<!-- ── Security / Password ───────────────────────────────────── -->
<div id="security" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center text-fluent-red text-xs">🔐</span>
        Sécurité & Accès
    </h2>
    <div class="mb-4 p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl text-xs text-fluent-n2">
        Connecté en tant que <strong><?= h($user['username']??'') ?></strong>
        <?php if ($user['last_login']??null): ?>
        · Dernière connexion: <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?>
        <?php endif; ?>
    </div>
    <form method="POST" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="change_password" value="1">
        <div>
            <label class="block text-xs font-medium text-fluent-n2 mb-1">Mot de passe actuel</label>
            <input type="password" name="current_pw" required autocomplete="current-password"
                class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
        </div>
        <div>
            <label class="block text-xs font-medium text-fluent-n2 mb-1">Nouveau mot de passe <span class="text-fluent-n3">(min. 8 caractères)</span></label>
            <input type="password" name="new_pw" id="new-pw" required minlength="8" autocomplete="new-password"
                class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700"
                oninput="checkPwStrength(this.value)">
            <div id="pw-strength" class="mt-1.5 h-1 rounded-full bg-fluent-n5 overflow-hidden"><div id="pw-bar" class="h-full rounded-full transition-all duration-300"></div></div>
            <div id="pw-hint" class="text-[10px] text-fluent-n3 mt-0.5"></div>
        </div>
        <div>
            <label class="block text-xs font-medium text-fluent-n2 mb-1">Confirmer le nouveau mot de passe</label>
            <input type="password" name="confirm_pw" id="confirm-pw" required autocomplete="new-password"
                class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
        </div>
        <div class="flex justify-end pt-1">
            <button type="submit" onclick="return checkConfirm()"
                class="btn-f px-5 py-2.5 text-sm bg-fluent-red text-white rounded-xl font-semibold hover:bg-red-700 shadow-f">
                Changer le Mot de Passe
            </button>
        </div>
    </form>
</div>

<!-- ── About ─────────────────────────────────────────────────── -->
<div class="bg-fluent-blue-lt dark:bg-fluent-blue/10 border border-fluent-blue/20 rounded-2xl p-5">
    <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-xl bg-fluent-blue flex items-center justify-center text-white font-bold flex-shrink-0 shadow-f">AE</div>
        <div>
            <div class="font-bold text-fluent-blue">Moroccan AE System v<?= APP_VERSION ?></div>
            <div class="text-xs text-fluent-blue/70 mt-0.5">Système open source pour Auto-Entrepreneurs marocains</div>
            <div class="text-xs text-fluent-n3 mt-2">Conforme DGI / CNSS · TVA exonérée Art. 91-I-B-1° CGI · MIT License</div>
            <a href="https://github.com/moroccan-ae-system" target="_blank" rel="noopener" class="text-xs text-fluent-blue font-semibold hover:underline mt-1 inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>
                GitHub — Contribuer au projet
            </a>
        </div>
    </div>
</div>

</div>

<script>
function checkPwStrength(v) {
    const bar = document.getElementById('pw-bar');
    const hint = document.getElementById('pw-hint');
    let score = 0; let hints = [];
    if (v.length >= 8)  score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++; else hints.push('majuscule');
    if (/[0-9]/.test(v)) score++; else hints.push('chiffre');
    if (/[^A-Za-z0-9]/.test(v)) score++; else hints.push('symbole');
    const colors = ['bg-fluent-red','bg-fluent-red','bg-amber-400','bg-amber-400','bg-fluent-green','bg-fluent-green'];
    const labels = ['Très faible','Faible','Moyen','Correct','Fort','Très fort'];
    bar.className = 'h-full rounded-full transition-all duration-300 ' + (colors[score]||'bg-fluent-red');
    bar.style.width = Math.min(100, score * 20) + '%';
    hint.textContent = (labels[score]||'') + (hints.length ? ' · Ajouter: ' + hints.join(', ') : '');
}
function checkConfirm() {
    const np = document.getElementById('new-pw').value;
    const cp = document.getElementById('confirm-pw').value;
    if (np !== cp) { alert('Les mots de passe ne correspondent pas.'); return false; }
    return true;
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
