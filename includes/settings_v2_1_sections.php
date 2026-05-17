<?php
// ── settings_v2.1_sections.php ──────────────────────────────────
// Include this inside settings.php inside the <div class="max-w-2xl mx-auto space-y-4">
// (or paste the three <form> blocks directly into settings.php)
//
// Handles:
//   - Logo upload / delete
//   - SMTP configuration
//   - Email templates
//
// The main settings.php POST handler already handles text/rate/float fields.
// Add logo + smtp fields to the $textFields / $intFields arrays in settings.php:
//
//   $textFields[] = 'smtp_host';
//   $textFields[] = 'smtp_user';
//   $textFields[] = 'smtp_pass';
//   $textFields[] = 'smtp_from_name';
//   $textFields[] = 'smtp_from_email';
//   $textFields[] = 'smtp_encryption';
//   $textFields[] = 'email_invoice_subject';
//   $textFields[] = 'email_invoice_body';
//   $textFields[] = 'email_quote_subject';
//   $textFields[] = 'email_quote_body';
//   $intFields[]  = 'smtp_port';
//   $intFields[]  = 'logo_width_mm';
//
// And add logo handler at top of settings.php POST block:
//
//   if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
//       require_once __DIR__ . '/includes/logo.php';
//       $logoResult = handleLogoUpload($_FILES['logo']);
//       if (!$logoResult['ok']) flash('message', $logoResult['error'], 'error');
//       else flash('message', 'Logo mis à jour !');
//       header('Location: /settings.php#logo'); exit;
//   }
//   if (isset($_POST['delete_logo'])) {
//       require_once __DIR__ . '/includes/logo.php';
//       deleteLogo();
//       flash('message', 'Logo supprimé.');
//       header('Location: /settings.php#logo'); exit;
//   }
?>

<!-- ── LOGO SECTION ─────────────────────────────────────────── -->
<div id="logo" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-purple-500 rounded-lg flex items-center justify-center text-white text-xs">🖼️</span>
        Logo (Factures & Devis)
    </h2>

    <!-- Current logo preview -->
    <?php if (!empty($cfg['logo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $cfg['logo_path'])): ?>
    <div class="flex items-center gap-4 mb-4 p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl">
        <img src="<?= h($cfg['logo_path']) ?>?v=<?= time() ?>" alt="Logo actuel"
            class="max-h-16 max-w-[160px] object-contain rounded">
        <div class="flex-1">
            <div class="text-sm font-medium text-fluent-neutral">Logo actuel</div>
            <div class="text-xs text-fluent-n3"><?= h(basename($cfg['logo_path'])) ?></div>
        </div>
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="delete_logo" value="1">
            <button type="submit" onclick="return confirm('Supprimer le logo ?')"
                class="btn-f px-3 py-1.5 text-xs border border-red-200 rounded-lg text-fluent-red hover:bg-red-50">
                Supprimer
            </button>
        </form>
    </div>
    <?php else: ?>
    <div class="flex items-center gap-3 mb-4 p-3 bg-fluent-n7 dark:bg-white/5 rounded-xl text-sm text-fluent-n3">
        <div class="text-2xl">🖼️</div>
        <div>Aucun logo — vos documents afficheront uniquement le nom de l'entreprise</div>
    </div>
    <?php endif; ?>

    <!-- Upload form -->
    <form method="POST" enctype="multipart/form-data" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">

        <div class="border-2 border-dashed border-fluent-n4 dark:border-white/20 rounded-xl p-5 text-center hover:border-fluent-blue transition-colors cursor-pointer"
            onclick="document.getElementById('logo-upload-input').click()">
            <div class="text-2xl mb-1">📤</div>
            <div class="text-sm font-medium text-fluent-n2">Cliquez pour uploader un logo</div>
            <div class="text-xs text-fluent-n3 mt-0.5">PNG, JPG, SVG — max 2 Mo</div>
            <div id="logo-filename" class="text-xs text-fluent-blue mt-1 hidden"></div>
        </div>
        <input type="file" id="logo-upload-input" name="logo" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" class="hidden"
            onchange="document.getElementById('logo-filename').textContent=this.files[0].name;document.getElementById('logo-filename').classList.remove('hidden')">

        <div>
            <label class="block text-xs font-medium text-fluent-n2 mb-1">Largeur sur document (mm)</label>
            <input type="number" name="logo_width_mm" min="10" max="80" value="<?= (int)($cfg['logo_width_mm']??40) ?>"
                class="w-32 px-3 py-2 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            <span class="text-xs text-fluent-n3 ml-2">px → mm pour Dompdf</span>
        </div>

        <button type="submit" class="btn-f px-4 py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk">
            Enregistrer le logo
        </button>
    </form>
</div>

<!-- ── SMTP / EMAIL SECTION ─────────────────────────────────── -->
<div id="email-config" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-fluent-blue rounded-lg flex items-center justify-center text-white text-xs">📧</span>
        Configuration Email (SMTP)
    </h2>
    <form method="POST" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Serveur SMTP</label>
                <input type="text" name="smtp_host" value="<?= h($cfg['smtp_host']??'') ?>"
                    placeholder="smtp.gmail.com / smtp.office365.com"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Port</label>
                <input type="number" name="smtp_port" value="<?= (int)($cfg['smtp_port']??587) ?>"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Utilisateur SMTP</label>
                <input type="text" name="smtp_user" value="<?= h($cfg['smtp_user']??'') ?>"
                    placeholder="votre@email.com"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Mot de passe SMTP</label>
                <input type="password" name="smtp_pass" value="<?= h($cfg['smtp_pass']??'') ?>"
                    placeholder="App password recommandé"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Nom expéditeur</label>
                <input type="text" name="smtp_from_name" value="<?= h($cfg['smtp_from_name']??$cfg['owner_name']??'') ?>"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Email expéditeur</label>
                <input type="email" name="smtp_from_email" value="<?= h($cfg['smtp_from_email']??$cfg['email']??'') ?>"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-fluent-n2 mb-1">Chiffrement</label>
            <select name="smtp_encryption" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
                <?php foreach (['tls'=>'STARTTLS (port 587 — recommandé)','ssl'=>'SSL (port 465)','none'=>'Aucun (non recommandé)'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($cfg['smtp_encryption']??'tls')===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Test button -->
        <div class="flex items-center gap-3 pt-1">
            <button type="submit" name="save_smtp" value="1"
                class="btn-f px-4 py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk">
                Enregistrer SMTP
            </button>
            <button type="button"
                onclick="testSmtp()"
                class="btn-f px-4 py-2.5 border border-fluent-n4 dark:border-white/20 rounded-xl text-sm text-fluent-n2 hover:bg-fluent-n6">
                📤 Tester l'envoi
            </button>
            <span id="smtp-test-result" class="text-xs text-fluent-n3"></span>
        </div>
    </form>
</div>

<!-- ── EMAIL TEMPLATES ───────────────────────────────────────── -->
<div id="email-templates" class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
    <h2 class="font-semibold text-sm text-fluent-neutral mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-amber-500 rounded-lg flex items-center justify-center text-white text-xs">✉️</span>
        Modèles d'emails
    </h2>
    <p class="text-xs text-fluent-n3 mb-4">Variables disponibles : <code class="bg-fluent-n6 dark:bg-white/10 px-1 rounded">{{number}}</code> <code class="bg-fluent-n6 dark:bg-white/10 px-1 rounded">{{client}}</code> <code class="bg-fluent-n6 dark:bg-white/10 px-1 rounded">{{amount}}</code> <code class="bg-fluent-n6 dark:bg-white/10 px-1 rounded">{{date}}</code> <code class="bg-fluent-n6 dark:bg-white/10 px-1 rounded">{{owner}}</code></p>

    <form method="POST" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">

        <!-- Invoice email -->
        <div class="p-4 border border-fluent-n5 dark:border-white/10 rounded-xl space-y-3">
            <div class="text-xs font-semibold text-fluent-n2 uppercase tracking-wider">📄 Email Facture</div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Objet</label>
                <input type="text" name="email_invoice_subject"
                    value="<?= h($cfg['email_invoice_subject']??'Votre facture {{number}}') ?>"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Corps (HTML accepté)</label>
                <textarea name="email_invoice_body" rows="4"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp font-mono resize-y"
                    ><?= h($cfg['email_invoice_body'] ?? "Bonjour {{client}},\n\nVeuillez trouver ci-dessous votre facture <strong>{{number}}</strong> d'un montant de <strong>{{amount}}</strong>.\n\nMerci pour votre confiance.\n\nCordialement,\n{{owner}}") ?></textarea>
            </div>
        </div>

        <!-- Quote email -->
        <div class="p-4 border border-fluent-n5 dark:border-white/10 rounded-xl space-y-3">
            <div class="text-xs font-semibold text-fluent-n2 uppercase tracking-wider">📋 Email Devis</div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Objet</label>
                <input type="text" name="email_quote_subject"
                    value="<?= h($cfg['email_quote_subject']??'Votre devis {{number}}') ?>"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Corps (HTML accepté)</label>
                <textarea name="email_quote_body" rows="4"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp font-mono resize-y"
                    ><?= h($cfg['email_quote_body'] ?? "Bonjour {{client}},\n\nVeuillez trouver ci-joint votre devis <strong>{{number}}</strong> d'un montant de <strong>{{amount}}</strong>.\n\nN'hésitez pas à nous contacter pour toute question.\n\nCordialement,\n{{owner}}") ?></textarea>
            </div>
        </div>

        <button type="submit" class="btn-f px-4 py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk">
            Enregistrer les modèles
        </button>
    </form>
</div>

<!-- SMTP test script -->
<script>
function testSmtp() {
    const btn = event.target;
    const resultEl = document.getElementById('smtp-test-result');
    btn.disabled = true;
    btn.textContent = 'Envoi en cours…';
    resultEl.textContent = '';

    const fd = new FormData();
    fd.append('_csrf', '<?= $csrf ?>');
    fd.append('action', 'test_smtp');

    fetch('/api/test-smtp.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            resultEl.textContent = d.ok ? '✅ ' + (d.message || 'Email test envoyé !') : '❌ ' + d.error;
            resultEl.className = 'text-xs ' + (d.ok ? 'text-fluent-green' : 'text-fluent-red');
        })
        .catch(() => { resultEl.textContent = '❌ Erreur réseau'; resultEl.className = 'text-xs text-fluent-red'; })
        .finally(() => { btn.disabled = false; btn.textContent = '📤 Tester l\'envoi'; });
}
</script>
