    </main>
</div>

<!-- Mobile bottom nav -->
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-50 acrylic border-t border-fluent-n5 pb-safe">
    <div class="flex items-center h-16">
        <?php
        $bnav = [
            ['id'=>'dashboard', 'label'=>'Home',     'icon'=>'grid',        'href'=>'/dashboard.php'],
            ['id'=>'invoices',  'label'=>'Factures', 'icon'=>'document',    'href'=>'/invoices.php'],
            ['id'=>'plus',      'label'=>'',         'icon'=>'plus',        'href'=>'/invoice-new.php', 'accent'=>true],
            ['id'=>'quotes',    'label'=>'Devis',    'icon'=>'clipboard',   'href'=>'/quotes.php'],
            ['id'=>'settings',  'label'=>'Compte',   'icon'=>'settings',    'href'=>'/settings.php'],
        ];
        foreach ($bnav as $item):
            $active = $currentPage === $item['id'];
            if ($item['id']==='plus'):
        ?>
        <a href="<?= h($item['href']) ?>" class="flex-1 flex flex-col items-center">
            <div class="w-12 h-12 bg-fluent-blue rounded-2xl flex items-center justify-center shadow-fm -mt-5 border-2 border-white dark:border-gray-900">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </div>
        </a>
        <?php else: ?>
        <a href="<?= h($item['href']) ?>" class="flex-1 flex flex-col items-center gap-0.5 py-2 <?= $active?'text-fluent-blue':'text-fluent-n3' ?>">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="<?= $active?'2':'1.5' ?>">
                <?= navSvg($item['icon']) ?>
            </svg>
            <span class="text-[9px] font-medium"><?= h($item['label']) ?></span>
        </a>
        <?php endif; endforeach; ?>
    </div>
</nav>

<!-- Global Search Modal -->
<div id="search-modal" class="fixed inset-0 z-[60] hidden flex items-start justify-center pt-[15vh] px-4">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeSearch()"></div>
    <div class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-fl overflow-hidden">
        <div class="flex items-center gap-3 px-4 py-3.5 border-b border-fluent-n5 dark:border-white/10">
            <svg class="w-4 h-4 text-fluent-n3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="modal-search-input" type="text" placeholder="Rechercher factures, devis, clients…"
                class="flex-1 text-sm bg-transparent outline-none placeholder-fluent-n3 text-fluent-neutral dark:text-gray-100"
                oninput="modalSearch(this.value)">
            <kbd class="text-[10px] px-1.5 py-0.5 bg-fluent-n5 dark:bg-white/10 text-fluent-n3 rounded border border-fluent-n4 dark:border-white/20">ESC</kbd>
        </div>
        <div id="modal-search-results" class="max-h-80 overflow-y-auto">
            <div class="px-4 py-8 text-center text-xs text-fluent-n3">Commencez à taper pour rechercher…</div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= $csrf ?>';

// ── Dark mode ─────────────────────────────────────────────────
const html = document.documentElement;
function toggleDark() {
    html.classList.toggle('dark');
    localStorage.setItem('ae-dark', html.classList.contains('dark') ? '1' : '0');
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
    const isOpen = !sb.classList.contains('-translate-x-full');
    sb.classList.toggle('-translate-x-full', isOpen);
    ov.classList.toggle('hidden', isOpen);
}

// ── Notifications ─────────────────────────────────────────────
function toggleNotifs() {
    document.getElementById('notif-panel')?.classList.toggle('hidden');
}
document.addEventListener('click', e => {
    const w = document.getElementById('notif-wrapper');
    if (w && !w.contains(e.target)) document.getElementById('notif-panel')?.classList.add('hidden');
});

// ── Search ────────────────────────────────────────────────────
function openSearch() {
    document.getElementById('search-modal').classList.remove('hidden');
    setTimeout(() => document.getElementById('modal-search-input')?.focus(), 50);
}
function closeSearch() {
    document.getElementById('search-modal').classList.add('hidden');
}
document.addEventListener('keydown', e => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); openSearch(); }
    if (e.key === 'Escape') closeSearch();
});

let searchTimer = null;
function sidebarSearch(q) {
    clearTimeout(searchTimer);
    if (q.length < 2) { document.getElementById('sidebar-results')?.classList.add('hidden'); return; }
    searchTimer = setTimeout(() => doSearch(q, 'sidebar-results'), 220);
}
function showSearchDrop() {
    const v = document.getElementById('sidebar-search')?.value;
    if (v && v.length >= 2) doSearch(v, 'sidebar-results');
}
function hideSearchDrop() { document.getElementById('sidebar-results')?.classList.add('hidden'); }

let modalTimer = null;
function modalSearch(q) {
    clearTimeout(modalTimer);
    const el = document.getElementById('modal-search-results');
    if (q.length < 2) { el.innerHTML = '<div class="px-4 py-8 text-center text-xs text-fluent-n3">Commencez à taper…</div>'; return; }
    modalTimer = setTimeout(() => doSearch(q, 'modal-search-results'), 220);
}

function doSearch(q, targetId) {
    fetch('/api/search.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => renderSearch(data, targetId))
        .catch(() => {});
}

function renderSearch(results, targetId) {
    const el = document.getElementById(targetId);
    if (!el) return;
    if (!results.length) {
        el.innerHTML = '<div class="px-4 py-6 text-center text-xs text-fluent-n3">Aucun résultat</div>';
        el.classList.remove('hidden'); return;
    }
    const badgeCls = s => {
        if (!s) return 'hidden';
        const m = {'Payé':'badge-paid','En attente':'badge-pending','Annulé':'badge-cancelled',
            'Brouillon':'badge-draft','Envoyé':'badge-sent','Accepté':'badge-accepted','Refusé':'badge-refused','Expiré':'badge-expired'};
        return m[s] || 'bg-fluent-n6 text-fluent-n3';
    };
    el.innerHTML = results.map(r => `
        <a href="${r.href}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-fluent-n7 dark:hover:bg-white/5 border-b border-fluent-n6 dark:border-white/5 last:border-0 transition-colors" onclick="closeSearch()">
            <span class="text-base leading-none">${r.icon}</span>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-fluent-neutral dark:text-gray-100 truncate">${r.title}</div>
                <div class="text-xs text-fluent-n3 truncate">${r.sub}</div>
            </div>
            ${r.badge ? `<span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium flex-shrink-0 ${badgeCls(r.badge)}">${r.badge}</span>` : ''}
        </a>`).join('');
    el.classList.remove('hidden');
}

// ── Flash auto-dismiss ────────────────────────────────────────
setTimeout(() => {
    const f = document.getElementById('flash-msg');
    if (f) { f.style.transition='opacity .4s'; f.style.opacity='0'; setTimeout(()=>f.remove(),400); }
}, 3500);

// ── Helpers ───────────────────────────────────────────────────
function confirmDelete(msg) { return confirm(msg || 'Supprimer cet élément ?'); }
function formatMAD(n) { return new Intl.NumberFormat('fr-MA',{minimumFractionDigits:2}).format(n)+' MAD'; }
</script>
</body>
</html>
