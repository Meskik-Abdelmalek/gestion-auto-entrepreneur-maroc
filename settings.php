<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/logo.php';
require_once __DIR__ . '/includes/email.php';
$pageName = 'Paramètres';
$db   = getDB();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ── Logo upload ────────────────────────────────────────────
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $r = handleLogoUpload($_FILES['logo']);
        flash('message', $r['ok'] ? 'Logo mis à jour !' : $r['error'], $r['ok'] ? 'success' : 'error');
        header('Location: /settings.php#logo'); exit;
    }
    if (isset($_POST['delete_logo'])) {
        deleteLogo();
        flash('message', 'Logo supprimé.');
        header('Location: /settings.php#logo'); exit;
    }

    // ── Password change ────────────────────────────────────────
    if (isset($_POST['change_password'])) {
        $result = changePassword($user['id'], $_POST['current_pw']??'', $_POST['new_pw']??'');
        flash('message', $result['ok'] ? 'Mot de passe modifié !' : $result['error'], $result['ok'] ? 'success' : 'error');
        header('Location: /settings.php#security'); exit;
    }

    // ── SMTP test ──────────────────────────────────────────────
    if (isset($_POST['test_smtp'])) {
        // Save first, then test
        // fall through to save below, then redirect to test endpoint
    }

    // ── Config save ────────────────────────────────────────────
    $textFields = [
        'owner_name','email','ice','if_fiscal','tp','cnss_phone','address','bank_rib',
        'currency','activity_1','activity_2','activity_3','quote_footer_text','invoice_footer_text',
        // v2.1
        'smtp_host','smtp_user','smtp_pass','smtp_from_name','smtp_from_email','smtp_encryption',
        'email_invoice_subject','email_invoice_body','email_quote_subject','email_quote_body',
    ];
    $intFields   = ['fiscal_year','quote_validity_days','smtp_port','logo_width_mm'];
    $rateFields  = ['ir_rate_services','ir_rate_commerce','alert_yellow','alert_orange','alert_red'];
    $floatFields = ['ceiling_services','ceiling_commerce','cnss_monthly'];

    $set = []; $params = [];
    foreach ($textFields  as $f) if (array_key_exists($f,$_POST)) { $set[]="$f=?"; $params[]=clean($_POST[$f]); }
    foreach ($intFields   as $f) if (array_key_exists($f,$_POST)) { $set[]="$f=?"; $params[]=(int)$_POST[$f]; }
    foreach ($rateFields  as $f) if (array_key_exists($f,$_POST)) { $set[]="$f=?"; $params[]=(float)$_POST[$f]/100; }
    foreach ($floatFields as $f) if (array_key_exists($f,$_POST)) { $set[]="$f=?"; $params[]=(float)$_POST[$f]; }

    if ($set) {
        $db->prepare("UPDATE ae_config SET ".implode(',',$set)." WHERE id=1")->execute($params);
        flash('message','Paramètres sauvegardés !');
    }

    $anchor = $_POST['_anchor'] ?? '';
    header('Location: /settings.php' . ($anchor ? "#$anchor" : '')); exit;
}

$cfg = getConfig();
require_once __DIR__ . '/includes/header.php';

// Helper: render a labeled input
$inp = fn(string $name, string $label, string $ph='', string $type='text', string $note='') =>
    '<div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">' . $label . '</label>
        <input type="' . $type . '" name="' . $name . '"
            value="' . h($cfg[$name]??'') . '"
            placeholder="' . h($ph) . '"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
        ' . ($note ? '<p class="text-[10px] text-fluent-n3 mt-0.5">' . $note . '</p>' : '') . '
    </div>';

// Flash
$flash = flash('message');
?>

<div class="max-w-2xl mx-auto space-y-4">

<?php if ($flash): ?>
<div class="px-4 py-3 rounded-xl text-sm font-medium <?= $flash['type']==='error'?'bg-red-50 text-red-700 border border-red-200':'bg-green-50 text-green-700 border border-green-200' ?> flex items-center gap-2">
    <span><?= $flash['type']==='error'?'⚠️':'✅' ?></span>
    <?= h($flash['msg']) ?>
</div>
<?php endif; ?>

<!-- Section nav pills -->
<div class="flex items-center gap-2 overflow-x-auto pb-1 flex-nowrap">
    <?php foreach ([
        ['#identity','👤','Identité'],['#activities','🎯','Activités'],
        ['#fiscal','📊','Fiscal'],['#documents','📋','Documents'],
        ['#logo','🖼️','Logo'],['#email-config','📧','Email SMTP'],
        ['#email-templates','✉️','Templates'],['#security','🔐','Sécurité'],
    ] as [$href,$ico,$label]): ?>
    <a href="<?= $href ?>" class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-gray-800 rounded-xl text-xs font-medium text-fluent-n2 hover:bg-fluent-blue-lt hover:text-fluent-blue shadow-f border border-fluent-n5 dark:border-white/10 transition-colors">
        <span><?= $ico ?></span><span><?= $label ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- ═══════════════ IDENTITY ══════════════════════════════════ -->
<form id="identity" method="POST" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5" enctype="multipart/form-data">
<input type="hidden" name="_csrf" value="<?= $csrf ?>">
<input type="hidden" name="_anchor" value="identity">
<h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
    <span class="w-7 h-7 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs">👤</span>
    Identité Auto-Entrepreneur
</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <?= $inp('owner_name','Nom / Raison sociale *','Mohamed Benali') ?>
    <?= $inp('email','Email professionnel','votre@email.com','email') ?>
    <?= $inp('ice','ICE (Identifiant Commun Entreprise)','001234567000000') ?>
    <?= $inp('if_fiscal','Identifiant Fiscal (IF)','12345678') ?>
    <?= $inp('tp','Taxe Professionnelle (TP)','12345678') ?>
    <?= $inp('cnss_phone','Téléphone / CNSS','+212 6XX XXXXXX') ?>
    <?= $inp('bank_rib','RIB Bancaire','123456789012345678901234') ?>
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Adresse</label>
        <textarea name="address" rows="2" placeholder="Rue, Ville, Code Postal, Maroc"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 resize-none placeholder-fluent-n3"
            ><?= h($cfg['address']??'') ?></textarea>
    </div>
</div>
<div class="flex justify-end mt-4">
    <button type="submit" class="btn-f px-5 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold hover:bg-fluent-blue-dk shadow-f">Sauvegarder</button>
</div>
</form>

<!-- ═══════════════ ACTIVITIES ════════════════════════════════ -->
<form id="activities" method="POST" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
<input type="hidden" name="_csrf" value="<?= $csrf ?>">
<input type="hidden" name="_anchor" value="activities">
<h2 class="font-semibold text-sm text-fluent-neutral mb-1 flex items-center gap-2">
    <span class="w-7 h-7 bg-amber-500 rounded-lg flex items-center justify-center text-white text-xs">🎯</span>
    Activités Professionnelles
</h2>
<p class="text-xs text-fluent-n3 mb-4">Copiez vos activités depuis <a href="https://rn.ae.gov.ma" target="_blank" class="text-fluent-blue hover:underline">rn.ae.gov.ma</a> — elles s'affichent sur les documents et alimentent le calcul IR.</p>
<div class="space-y-2">
    <?php for ($i=1; $i<=3; $i++): ?>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">
            Activité <?= $i ?> <?= $i===1?'<span class="text-fluent-n3 font-normal">(principale)</span>':'' ?>
        </label>
        <div class="relative">
            <input name="activity_<?= $i ?>" value="<?= h($cfg["activity_$i"]??'') ?>"
                placeholder="Ex: Développement logiciel, Conseil en informatique…"
                class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 placeholder-fluent-n3">
            <?php if ($cfg["activity_$i"]??''): ?>
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-fluent-green text-xs font-bold">✓</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endfor; ?>
</div>
<div class="flex justify-end mt-4">
    <button type="submit" class="btn-f px-5 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold hover:bg-fluent-blue-dk shadow-f">Sauvegarder</button>
</div>
</form>

<!-- ═══════════════ FISCAL ════════════════════════════════════ -->
<form id="fiscal" method="POST" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
<input type="hidden" name="_csrf" value="<?= $csrf ?>">
<input type="hidden" name="_anchor" value="fiscal">
<h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
    <span class="w-7 h-7 bg-purple-500 rounded-lg flex items-center justify-center text-white text-xs">📊</span>
    Paramètres Fiscaux
</h2>
<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Taux IR Services (%)</label>
        <input type="number" name="ir_rate_services" value="<?= h(round(($cfg['ir_rate_services']??0.01)*100,2)) ?>" step="0.01" min="0" max="100"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center">
        <p class="text-[10px] text-fluent-n3 mt-0.5">Légal : 1%</p>
    </div>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Taux IR Commerce (%)</label>
        <input type="number" name="ir_rate_commerce" value="<?= h(round(($cfg['ir_rate_commerce']??0.005)*100,2)) ?>" step="0.01" min="0" max="100"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center">
        <p class="text-[10px] text-fluent-n3 mt-0.5">Légal : 0.5%</p>
    </div>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Plafond Services (MAD)</label>
        <input type="number" name="ceiling_services" value="<?= h($cfg['ceiling_services']??200000) ?>" step="1000"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center">
    </div>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Plafond Commerce (MAD)</label>
        <input type="number" name="ceiling_commerce" value="<?= h($cfg['ceiling_commerce']??500000) ?>" step="1000"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center">
    </div>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">CNSS mensuel (MAD)</label>
        <input type="number" name="cnss_monthly" value="<?= h($cfg['cnss_monthly']??100) ?>" step="0.01"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center">
    </div>
    <div>
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Exercice fiscal</label>
        <select name="fiscal_year" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
            <?php for ($y=(int)date('Y')+1;$y>=2020;$y--): ?>
            <option value="<?= $y ?>" <?= ($cfg['fiscal_year']??date('Y'))==$y?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
</div>
<!-- Alert thresholds -->
<div class="mt-4 p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl">
    <div class="text-xs font-medium text-fluent-n2 mb-2">Seuils d'alerte plafond (%)</div>
    <div class="grid grid-cols-3 gap-2">
        <?php foreach ([['alert_yellow','Jaune'],['alert_orange','Orange'],['alert_red','Rouge ⚠️']] as [$f,$l]): ?>
        <div>
            <label class="block text-[10px] text-fluent-n3 mb-1"><?= $l ?></label>
            <input type="number" name="<?= $f ?>" value="<?= h(round(($cfg[$f]??0.75)*100)) ?>" min="1" max="100"
                class="w-full px-3 py-2 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center">
        </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="flex justify-end mt-4">
    <button type="submit" class="btn-f px-5 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold hover:bg-fluent-blue-dk shadow-f">Sauvegarder</button>
</div>
</form>

<!-- ═══════════════ DOCUMENTS ════════════════════════════════ -->
<form id="documents" method="POST" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
<input type="hidden" name="_csrf" value="<?= $csrf ?>">
<input type="hidden" name="_anchor" value="documents">
<h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
    <span class="w-7 h-7 bg-fluent-green rounded-lg flex items-center justify-center text-white text-xs">📋</span>
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
            <?php foreach (['MAD','EUR','USD','GBP'] as $c): ?>
            <option value="<?= $c ?>" <?= ($cfg['currency']??'MAD')===$c?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Pied de page des Devis</label>
        <textarea name="quote_footer_text" rows="2" placeholder="Ex: Devis valable sous réserve d'acceptation dans le délai indiqué…"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 resize-none placeholder-fluent-n3"
            ><?= h($cfg['quote_footer_text']??'') ?></textarea>
    </div>
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-fluent-n2 mb-1">Pied de page des Factures</label>
        <textarea name="invoice_footer_text" rows="2" placeholder="Ex: Paiement à 30 jours. Pénalités en cas de retard…"
            class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 resize-none placeholder-fluent-n3"
            ><?= h($cfg['invoice_footer_text']??'') ?></textarea>
    </div>
</div>
<div class="flex justify-end mt-4">
    <button type="submit" class="btn-f px-5 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold hover:bg-fluent-blue-dk shadow-f">Sauvegarder</button>
</div>
</form>

<!-- ═══════════════ LOGO (v2.1) ══════════════════════════════ -->
<div id="logo" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-pink-500 rounded-lg flex items-center justify-center text-white text-xs">🖼️</span>
        Logo (Factures & Devis)
        <span class="text-[9px] font-bold text-white bg-fluent-purple px-1.5 py-0.5 rounded">v2.1</span>
    </h2>

    <?php $_logoPath = getLogoPath(); if ($_logoPath && logoExistsOnDisk($_logoPath)): ?>
    <div class="flex items-center gap-4 mb-4 p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl">
        <img src="<?= h($_logoPath) ?>?v=<?= time() ?>"
            alt="Logo actuel" class="max-h-16 max-w-[180px] object-contain rounded">
        <div class="flex-1">
            <div class="text-sm font-medium text-fluent-neutral">Logo actuel</div>
            <div class="text-xs text-fluent-n3"><?= h(basename($_logoPath)) ?></div>
        </div>
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="delete_logo" value="1">
            <button type="submit" onclick="return confirm('Supprimer le logo ?')"
                class="btn-f px-3 py-1.5 text-xs border border-red-200 rounded-xl text-fluent-red hover:bg-fluent-red-lt">
                Supprimer
            </button>
        </form>
    </div>
    <?php else: ?>
    <div class="flex items-center gap-3 mb-4 p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl text-sm text-fluent-n3">
        <div class="text-2xl">🖼️</div>
        <div>Aucun logo — vos documents n'afficheront que le nom de l'entreprise</div>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <div class="border-2 border-dashed border-fluent-n4 dark:border-white/20 rounded-xl p-5 text-center hover:border-fluent-blue cursor-pointer transition-colors"
            onclick="document.getElementById('logo-inp').click()">
            <div class="text-2xl mb-1">📤</div>
            <div class="text-sm font-medium text-fluent-n2">Cliquez pour uploader un logo</div>
            <div class="text-xs text-fluent-n3 mt-0.5">PNG, JPG, SVG — max 2 Mo</div>
            <div id="logo-fn" class="text-xs text-fluent-blue mt-1.5 hidden"></div>
        </div>
        <input type="file" id="logo-inp" name="logo" accept="image/*" class="hidden"
            onchange="document.getElementById('logo-fn').textContent=this.files[0]?.name||'';document.getElementById('logo-fn').classList.remove('hidden')">
        <div class="flex items-center gap-3">
            <div class="flex-1">
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Largeur sur document (mm)</label>
                <input type="number" name="logo_width_mm" value="<?= (int)($cfg['logo_width_mm']??40) ?>" min="10" max="80"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
            </div>
            <div class="flex-shrink-0 mt-5">
                <button type="submit" class="btn-f px-4 py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk">
                    Enregistrer
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ═══════════════ SMTP EMAIL (v2.1) ════════════════════════ -->
<div id="email-config" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs">📧</span>
        Configuration Email SMTP
        <span class="text-[9px] font-bold text-white bg-fluent-purple px-1.5 py-0.5 rounded">v2.1</span>
    </h2>
    <div class="mb-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl text-xs text-blue-700 dark:text-blue-300">
        💡 Gmail : utilisez un <strong>App Password</strong> (pas votre mot de passe Gmail). Office 365 : port 587, TLS.
        Si le champ SMTP Host est vide, le système utilise la fonction <code>mail()</code> de PHP.
    </div>
    <form method="POST" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="_anchor" value="email-config">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <?= $inp('smtp_host','Serveur SMTP','smtp.gmail.com / smtp.office365.com') ?>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Port</label>
                <input type="number" name="smtp_port" value="<?= (int)($cfg['smtp_port']??587) ?>"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 text-center">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Chiffrement</label>
                <select name="smtp_encryption" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
                    <?php foreach (['tls'=>'STARTTLS (587)','ssl'=>'SSL (465)','none'=>'Aucun'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($cfg['smtp_encryption']??'tls')===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?= $inp('smtp_user','Utilisateur SMTP','votre@gmail.com') ?>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Mot de passe SMTP</label>
                <div class="relative">
                    <input type="password" name="smtp_pass" id="smtp-pw" value="<?= h($cfg['smtp_pass']??'') ?>"
                        placeholder="App Password recommandé"
                        class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 pr-10">
                    <button type="button" onclick="toggleSmtpPw()" class="absolute right-3 top-1/2 -translate-y-1/2 text-fluent-n3 text-xs">👁</button>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?= $inp('smtp_from_name','Nom expéditeur',$cfg['owner_name']??'') ?>
            <?= $inp('smtp_from_email','Email expéditeur',$cfg['email']??'','email') ?>
        </div>
        <div class="flex flex-wrap items-center gap-2 pt-1">
            <button type="submit" class="btn-f px-4 py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk shadow-f">
                Enregistrer SMTP
            </button>
            <button type="button" id="smtp-test-btn" onclick="testSmtp()"
                class="btn-f px-4 py-2.5 border border-fluent-n4 dark:border-white/20 rounded-xl text-sm text-fluent-n2 hover:bg-fluent-n6">
                📤 Tester l'envoi
            </button>
            <span id="smtp-test-result" class="text-xs text-fluent-n3"></span>
        </div>
    </form>
</div>

<!-- ═══════════════ EMAIL TEMPLATES (v2.1) ═══════════════════ -->
<div id="email-templates" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-2 flex items-center gap-2">
        <span class="w-7 h-7 bg-amber-500 rounded-lg flex items-center justify-center text-white text-xs">✉️</span>
        Modèles d'Emails
        <span class="text-[9px] font-bold text-white bg-fluent-purple px-1.5 py-0.5 rounded">v2.1</span>
    </h2>
    <p class="text-xs text-fluent-n3 mb-4">
        Variables : <code class="bg-fluent-n6 dark:bg-white/10 px-1 rounded">{{number}}</code>
        <code class="bg-fluent-n6 dark:bg-white/10 px-1 rounded">{{client}}</code>
        <code class="bg-fluent-n6 dark:bg-white/10 px-1 rounded">{{amount}}</code>
        <code class="bg-fluent-n6 dark:bg-white/10 px-1 rounded">{{date}}</code>
        <code class="bg-fluent-n6 dark:bg-white/10 px-1 rounded">{{owner}}</code>
    </p>
    <form method="POST" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="_anchor" value="email-templates">
        <?php foreach ([['invoice','📄','Facture'],['quote','📋','Devis']] as [$t,$ico,$lbl]): ?>
        <div class="p-4 border border-fluent-n5 dark:border-white/10 rounded-xl space-y-3">
            <div class="text-xs font-semibold text-fluent-n2 uppercase tracking-wider"><?= $ico ?> Email <?= $lbl ?></div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Objet</label>
                <input type="text" name="email_<?= $t ?>_subject"
                    value="<?= h($cfg["email_{$t}_subject"] ?? "Votre {$lbl} {{number}}") ?>"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Corps (HTML accepté)</label>
                <textarea name="email_<?= $t ?>_body" rows="4"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700 font-mono resize-y"
                    ><?= h($cfg["email_{$t}_body"] ?? "Bonjour {{client}},\n\nVeuillez trouver ci-dessous votre {$lbl} <strong>{{number}}</strong> d'un montant de <strong>{{amount}}</strong>.\n\nCordialement,\n{{owner}}") ?></textarea>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="flex justify-end">
            <button type="submit" class="btn-f px-5 py-2.5 text-sm bg-fluent-blue text-white rounded-xl font-semibold hover:bg-fluent-blue-dk shadow-f">
                Enregistrer les modèles
            </button>
        </div>
    </form>
</div>

<!-- ═══════════════ SECURITY ══════════════════════════════════ -->
<div id="security" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center text-fluent-red text-xs">🔐</span>
        Sécurité & Accès
    </h2>
    <div class="mb-4 p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl text-xs text-fluent-n2">
        Connecté : <strong><?= h($user['username']??'') ?></strong>
        <?php if ($user['last_login']??null): ?>
        · Dernière connexion : <?= date('d/m/Y H:i',strtotime($user['last_login'])) ?>
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
            <label class="block text-xs font-medium text-fluent-n2 mb-1">Nouveau mot de passe <span class="text-fluent-n3">(min 8 car.)</span></label>
            <input type="password" name="new_pw" required minlength="8" autocomplete="new-password"
                class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
        </div>
        <div>
            <label class="block text-xs font-medium text-fluent-n2 mb-1">Confirmer</label>
            <input type="password" name="confirm_pw" required autocomplete="new-password"
                class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
        </div>
        <div class="flex justify-end pt-1">
            <button type="submit" class="btn-f px-5 py-2.5 text-sm bg-fluent-red text-white rounded-xl font-semibold hover:bg-red-700 shadow-f">
                Changer le mot de passe
            </button>
        </div>
    </form>
</div>

</div><!-- /max-w-2xl -->

<script>
function toggleSmtpPw() {
    const el = document.getElementById('smtp-pw');
    el.type = el.type === 'password' ? 'text' : 'password';
}
function testSmtp() {
    const btn = document.getElementById('smtp-test-btn');
    const res = document.getElementById('smtp-test-result');
    btn.disabled = true;
    btn.textContent = '⏳ Envoi…';
    res.textContent = '';
    const fd = new FormData();
    fd.append('_csrf', '<?= $csrf ?>');
    fd.append('action', 'test_smtp');
    fetch('/api/test-smtp.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            res.textContent = d.ok ? '✅ ' + (d.message||'Test réussi !') : '❌ ' + d.error;
            res.className   = 'text-xs ' + (d.ok ? 'text-fluent-green' : 'text-fluent-red');
        })
        .catch(() => { res.textContent = '❌ Erreur réseau'; res.className = 'text-xs text-fluent-red'; })
        .finally(() => { btn.disabled = false; btn.textContent = '📤 Tester l\'envoi'; });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
