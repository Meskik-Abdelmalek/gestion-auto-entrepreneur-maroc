<?php
// ── includes/email_modal.php ──────────────────────────────────
// Usage: include this at the bottom of invoice-view.php / quote-view.php
// Variables expected in scope: $id (doc id), $type ('invoice'|'quote'), $csrf, $doc (the invoice/quote row)
// The button to open the modal:
//   <button onclick="document.getElementById('email-modal').classList.remove('hidden')">📧 Envoyer par email</button>

$clientEmail = '';
if (isset($doc['client_id']) && $doc['client_id']) {
    $cSt = getDB()->prepare("SELECT email FROM ae_clients WHERE id=?");
    $cSt->execute([$doc['client_id']]);
    $clientEmail = $cSt->fetchColumn() ?: '';
}
$clientName = $doc['client_name'] ?? '';
?>

<!-- Email send modal -->
<div id="email-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-sm text-fluent-neutral">
                📧 Envoyer par email
            </h2>
            <button onclick="document.getElementById('email-modal').classList.add('hidden')" class="text-fluent-n3 hover:text-fluent-neutral">✕</button>
        </div>

        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Email destinataire *</label>
                <input type="email" id="email-to" value="<?= h($clientEmail) ?>" required
                    placeholder="client@exemple.com"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1">Nom destinataire</label>
                <input type="text" id="email-name" value="<?= h($clientName) ?>"
                    placeholder="Nom du client"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
            </div>

            <div id="email-result" class="hidden text-sm px-3 py-2 rounded-xl"></div>

            <div class="flex gap-2 pt-1">
                <button onclick="sendEmailNow()"
                    id="email-send-btn"
                    class="flex-1 btn-f py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    Envoyer
                </button>
                <button onclick="document.getElementById('email-modal').classList.add('hidden')"
                    class="px-4 py-2.5 border border-fluent-n4 dark:border-white/20 rounded-xl text-sm text-fluent-n2 hover:bg-fluent-n6">
                    Annuler
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function sendEmailNow() {
    const toEmail = document.getElementById('email-to').value.trim();
    const toName  = document.getElementById('email-name').value.trim();
    const btn     = document.getElementById('email-send-btn');
    const result  = document.getElementById('email-result');

    if (!toEmail || !toEmail.includes('@')) {
        result.className = 'text-sm px-3 py-2 rounded-xl bg-red-50 text-red-700 border border-red-200';
        result.textContent = 'Veuillez renseigner une adresse email valide.';
        result.classList.remove('hidden');
        return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path></svg> Envoi…';

    const fd = new FormData();
    fd.append('_csrf', '<?= $csrf ?>');
    fd.append('type', '<?= $type ?>');
    fd.append('id',   '<?= $id ?>');
    fd.append('to_email', toEmail);
    fd.append('to_name',  toName);

    fetch('/api/send-email.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            result.classList.remove('hidden');
            if (d.ok) {
                result.className = 'text-sm px-3 py-2 rounded-xl bg-green-50 text-green-700 border border-green-200';
                result.textContent = '✅ Email envoyé avec succès à ' + toEmail;
                btn.innerHTML = '✅ Envoyé';
                setTimeout(() => document.getElementById('email-modal').classList.add('hidden'), 2500);
            } else {
                result.className = 'text-sm px-3 py-2 rounded-xl bg-red-50 text-red-700 border border-red-200';
                result.textContent = '❌ ' + (d.error || 'Erreur inconnue');
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Réessayer';
            }
        })
        .catch(err => {
            result.classList.remove('hidden');
            result.className = 'text-sm px-3 py-2 rounded-xl bg-red-50 text-red-700 border border-red-200';
            result.textContent = '❌ Erreur réseau : ' + err.message;
            btn.disabled = false;
            btn.innerHTML = 'Réessayer';
        });
}
</script>
