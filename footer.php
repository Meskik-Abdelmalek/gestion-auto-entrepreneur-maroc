    </main>
</div>

<!-- ── MOBILE BOTTOM NAV ───────────────────────────────────── -->
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-50 acrylic border-t border-fluent-n5 pb-safe">
    <div class="flex items-center h-16">
        <?php
        $bnav = [
            ['id'=>'dashboard',     'label'=>'Home',     'icon'=>'grid',     'href'=>'/dashboard.php'],
            ['id'=>'invoices',      'label'=>'Factures', 'icon'=>'document', 'href'=>'/invoices.php'],
            ['id'=>'plus',          'label'=>'',         'icon'=>'plus',     'href'=>'/invoice-new.php', 'accent'=>true],
            ['id'=>'bank-accounts', 'label'=>'Comptes',  'icon'=>'wallet',   'href'=>'/bank-accounts.php'],
            ['id'=>'settings',      'label'=>'Compte',   'icon'=>'settings', 'href'=>'/settings.php'],
        ];
        foreach ($bnav as $item):
            $active = $currentPage === $item['id'];
            if ($item['id']==='plus'):
        ?>
        <a href="<?= h($item['href']) ?>" class="flex-1 flex flex-col items-center">
            <div class="w-12 h-12 bg-fluent-blue rounded-2xl flex items-center justify-center shadow-fm fab-shadow -mt-5 border-2 border-white dark:border-gray-900">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </div>
        </a>
        <?php else: ?>
        <a href="<?= h($item['href']) ?>" class="flex-1 flex flex-col items-center gap-0.5 py-2 <?= $active?'text-fluent-blue':'text-fluent-n3' ?>">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="<?= $active?'2':'1.5' ?>">
                <?= navSvg($item['icon']) ?>
            </svg>
            <span class="text-[10px] font-medium"><?= $item['label'] ?></span>
            <?php if ($active): ?><div class="w-1 h-1 rounded-full bg-fluent-blue mt-0.5"></div><?php endif; ?>
        </a>
        <?php endif; endforeach; ?>
    </div>
</nav>

<!-- ── GLOBAL SEARCH MODAL ───────────────────────────────────── -->
<div id="search-modal" class="hidden fixed inset-0 z-[60] flex items-start justify-center pt-[10vh] p-4 bg-black/40 backdrop-blur-sm">
    <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-fxl overflow-hidden animate-slide-up">
        <div class="flex items-center gap-3 px-4 py-3 border-b border-fluent-n5 dark:border-white/10">
            <svg class="w-5 h-5 text-fluent-n3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="modal-search-input" type="text" placeholder="Rechercher factures, devis, clients…"
                class="flex-1 text-sm bg-transparent border-none outline-none text-fluent-neutral placeholder-fluent-n3"
                oninput="modalSearch(this.value)">
            <kbd class="text-xs text-fluent-n3 bg-fluent-n6 dark:bg-white/10 px-2 py-1 rounded font-mono" onclick="closeSearch()" style="cursor:pointer">Esc</kbd>
        </div>
        <div id="modal-search-results" class="max-h-96 overflow-y-auto p-2">
            <div class="px-3 py-6 text-center text-xs text-fluent-n3">
                <div class="text-2xl mb-2">🔍</div>
                Commencez à taper pour rechercher…
            </div>
        </div>
        <!-- Quick nav hints -->
        <div class="px-4 py-3 border-t border-fluent-n5 dark:border-white/10 flex items-center gap-4 flex-wrap">
            <?php foreach ([['⚡','Nouvelle facture','/invoice-new.php'],['📋','Nouveau devis','/quote-new.php'],['🏦','Comptes','/bank-accounts.php'],['📥','Import CSV','/bank-import.php']] as [$ico,$lbl,$url]): ?>
            <a href="<?= $url ?>" onclick="closeSearch()" class="flex items-center gap-1.5 text-xs text-fluent-n2 hover:text-fluent-blue">
                <span><?= $ico ?></span><span><?= $lbl ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── EMAIL SEND MODAL (v2.1, reusable) ──────────────────── -->
<div id="email-send-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-fxl overflow-hidden animate-slide-up">
        <div class="flex items-center justify-between px-5 py-4 border-b border-fluent-n5 dark:border-white/10">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-fluent-blue flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <h3 class="font-semibold text-sm text-fluent-neutral">Envoyer par email</h3>
            </div>
            <button onclick="closeEmailModal()" class="text-fluent-n3 hover:text-fluent-neutral p-1 rounded-lg hover:bg-fluent-n6">✕</button>
        </div>
        <div class="px-5 py-4 space-y-3">
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1.5">Adresse email *</label>
                <input type="email" id="email-to-addr" placeholder="client@exemple.com"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
            </div>
            <div>
                <label class="block text-xs font-medium text-fluent-n2 mb-1.5">Nom du destinataire</label>
                <input type="text" id="email-to-name" placeholder="Nom du client"
                    class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl inp bg-white dark:bg-gray-700">
            </div>
            <div id="email-send-result" class="hidden text-sm px-3 py-2.5 rounded-xl"></div>
        </div>
        <div class="px-5 pb-5 flex gap-2">
            <button id="email-send-btn" onclick="doSendEmail()"
                class="flex-1 btn-f py-2.5 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                Envoyer
            </button>
            <button onclick="closeEmailModal()"
                class="px-4 py-2.5 border border-fluent-n4 dark:border-white/20 rounded-xl text-sm text-fluent-n2 hover:bg-fluent-n6">
                Annuler
            </button>
        </div>
    </div>
</div>

<!-- ── TOAST NOTIFICATIONS ───────────────────────────────────── -->
<div id="toast-container" class="fixed bottom-20 lg:bottom-4 right-4 z-[70] space-y-2 pointer-events-none"></div>

<!-- ── GLOBAL JS ─────────────────────────────────────────────── -->
<script>
// ── Dark mode ─────────────────────────────────────────────────
const html = document.documentElement;
function toggleDark() {
    const d = html.classList.toggle('dark');
    localStorage.setItem('ae-dark', d ? '1' : '0');
    updateDarkIcons();
}
function updateDarkIcons() {
    const d = html.classList.contains('dark');
    document.getElementById('icon-light')?.classList.toggle('hidden', d);
    document.getElementById('icon-dark')?.classList.toggle('hidden', !d);
}
if (localStorage.getItem('ae-dark') === '1') html.classList.add('dark');
document.addEventListener('DOMContentLoaded', updateDarkIcons);

// ── Drawer ────────────────────────────────────────────────────
function toggleDrawer() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('drawer-overlay');
    const open = sb.classList.contains('-translate-x-full');
    sb.classList.toggle('-translate-x-full', !open);
    ov.classList.toggle('hidden', !open);
    document.body.style.overflow = open ? 'hidden' : '';
}

// ── Notifications ─────────────────────────────────────────────
function toggleNotifs() {
    document.getElementById('notif-panel')?.classList.toggle('hidden');
}
document.addEventListener('click', e => {
    const w = document.getElementById('notif-wrapper');
    if (w && !w.contains(e.target)) document.getElementById('notif-panel')?.classList.add('hidden');
});

// ── Global search ─────────────────────────────────────────────
function openSearch() {
    document.getElementById('search-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('modal-search-input')?.focus(), 50);
}
function closeSearch() {
    document.getElementById('search-modal').classList.add('hidden');
    document.body.style.overflow = '';
    if (document.getElementById('modal-search-input'))
        document.getElementById('modal-search-input').value = '';
}
document.addEventListener('keydown', e => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); openSearch(); }
    if (e.key === 'Escape') { closeSearch(); closeEmailModal(); }
});
document.getElementById('search-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeSearch();
});

let sTimer = null;
function sidebarSearch(q) {
    clearTimeout(sTimer);
    const el = document.getElementById('sidebar-results');
    if (q.length < 2) { el.classList.add('hidden'); return; }
    sTimer = setTimeout(() => doSearch(q, 'sidebar-results'), 220);
}
function showSearchDrop() {
    const v = document.getElementById('sidebar-search')?.value;
    if (v && v.length >= 2) doSearch(v, 'sidebar-results');
}
function hideSearchDrop() { document.getElementById('sidebar-results')?.classList.add('hidden'); }

let mTimer = null;
function modalSearch(q) {
    clearTimeout(mTimer);
    const el = document.getElementById('modal-search-results');
    if (q.length < 2) {
        el.innerHTML = '<div class="px-3 py-6 text-center text-xs text-fluent-n3"><div class="text-2xl mb-2">🔍</div>Commencez à taper…</div>';
        return;
    }
    el.innerHTML = '<div class="px-3 py-4 text-center text-xs text-fluent-n3">Recherche en cours…</div>';
    mTimer = setTimeout(() => doSearch(q, 'modal-search-results'), 220);
}

function doSearch(q, targetId) {
    fetch('/api/search.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById(targetId);
            if (!el) return;
            if (!data.length) {
                el.classList.remove('hidden');
                el.innerHTML = '<div class="px-3 py-6 text-center text-xs text-fluent-n3">Aucun résultat pour «&nbsp;' + q + '&nbsp;»</div>';
                return;
            }
            el.classList.remove('hidden');
            el.innerHTML = data.map(r => `
                <a href="${r.url}" class="flex items-center gap-3 px-3 py-2.5 hover:bg-fluent-n6 dark:hover:bg-white/5 rounded-xl transition-colors group">
                    <div class="w-8 h-8 rounded-lg ${r.type==='invoice'?'bg-fluent-blue-lt text-fluent-blue':r.type==='quote'?'bg-purple-50 text-purple-600':'bg-green-50 text-fluent-green'} flex items-center justify-center text-xs font-bold flex-shrink-0">
                        ${r.type==='invoice'?'F':r.type==='quote'?'D':'C'}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-fluent-neutral truncate">${r.title}</div>
                        <div class="text-xs text-fluent-n3 truncate">${r.subtitle||''}</div>
                    </div>
                    <div class="text-xs text-fluent-n4 group-hover:text-fluent-blue">→</div>
                </a>`).join('');
        }).catch(() => {});
}

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const colors = { success:'bg-fluent-green text-white', error:'bg-fluent-red text-white', info:'bg-fluent-blue text-white', warning:'bg-amber-500 text-white' };
    const t = document.createElement('div');
    t.className = `pointer-events-auto px-4 py-3 rounded-xl shadow-fl text-sm font-medium ${colors[type]||colors.info} animate-fade-in flex items-center gap-2`;
    t.innerHTML = `<span>${msg}</span><button onclick="this.parentElement.remove()" class="ml-2 opacity-70 hover:opacity-100">✕</button>`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

// ── Flash messages as toast ───────────────────────────────────
<?php $flash = flash('message'); if ($flash): ?>
document.addEventListener('DOMContentLoaded', () => showToast('<?= addslashes(h($flash['msg'])) ?>', '<?= $flash['type']==='error'?'error':'success' ?>'));
<?php endif; ?>

// ── Email modal (v2.1) ────────────────────────────────────────
let _emailDocType = '', _emailDocId = 0;
function openEmailModal(type, id, toEmail = '', toName = '') {
    _emailDocType = type;
    _emailDocId   = id;
    document.getElementById('email-to-addr').value = toEmail;
    document.getElementById('email-to-name').value = toName;
    document.getElementById('email-send-result').classList.add('hidden');
    document.getElementById('email-send-btn').disabled = false;
    document.getElementById('email-send-btn').innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Envoyer';
    document.getElementById('email-send-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('email-to-addr')?.focus(), 50);
}
function closeEmailModal() {
    document.getElementById('email-send-modal').classList.add('hidden');
    document.body.style.overflow = '';
}
document.getElementById('email-send-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeEmailModal();
});
function doSendEmail() {
    const toEmail = document.getElementById('email-to-addr').value.trim();
    const toName  = document.getElementById('email-to-name').value.trim();
    const btn     = document.getElementById('email-send-btn');
    const res     = document.getElementById('email-send-result');
    if (!toEmail || !toEmail.includes('@')) {
        res.className = 'text-sm px-3 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-700';
        res.textContent = '⚠️ Adresse email invalide.';
        res.classList.remove('hidden'); return;
    }
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path></svg> Envoi…';
    const fd = new FormData();
    fd.append('_csrf', '<?= $csrf ?>');
    fd.append('type', _emailDocType);
    fd.append('id',   _emailDocId);
    fd.append('to_email', toEmail);
    fd.append('to_name',  toName);
    fetch('/api/send-email.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            res.classList.remove('hidden');
            if (d.ok) {
                res.className = 'text-sm px-3 py-2.5 rounded-xl bg-green-50 border border-green-200 text-green-700';
                res.textContent = '✅ Email envoyé à ' + toEmail;
                btn.innerHTML = '✅ Envoyé';
                showToast('Email envoyé avec succès !');
                setTimeout(closeEmailModal, 2500);
            } else {
                res.className = 'text-sm px-3 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-700';
                res.textContent = '❌ ' + (d.error || 'Erreur inconnue');
                btn.disabled = false;
                btn.innerHTML = 'Réessayer';
            }
        })
        .catch(() => {
            res.className = 'text-sm px-3 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-700';
            res.textContent = '❌ Erreur réseau';
            res.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = 'Réessayer';
        });
}

// ── Confirm delete helper ─────────────────────────────────────
function confirmDelete(msg = 'Supprimer définitivement ?') { return confirm(msg); }
</script>
</body>
</html>
