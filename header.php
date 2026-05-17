<?php
// ── Auth guard (every page includes this) ─────────────────────
require_once __DIR__ . '/functions.php';
requireAuth();

$cfg         = getConfig();
$pageName    = $pageName ?? 'Dashboard';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$csrf        = csrfToken();
$notifications = getNotifications();
$notifCount  = count($notifications);
$user        = currentUser();
$activities  = getActivities();

// Handle exports from header
if (isset($_GET['export'])) {
    if ($_GET['export']==='invoices') exportInvoicesCSV(['year'=>$_GET['year']??null,'status'=>$_GET['status']??null]);
    if ($_GET['export']==='expenses') exportExpensesCSV((int)($_GET['year']??date('Y')));
    if ($_GET['export']==='quotes')   exportQuotesCSV((int)($_GET['year']??date('Y')));
}

// ── v2.1 NAV : added bank-accounts ────────────────────────────
$nav = [
    ['id'=>'dashboard',      'label'=>'Dashboard',     'icon'=>'grid',        'href'=>'/dashboard.php'],
    ['id'=>'invoices',       'label'=>'Factures',       'icon'=>'document',    'href'=>'/invoices.php'],
    ['id'=>'quotes',         'label'=>'Devis',          'icon'=>'clipboard',   'href'=>'/quotes.php'],
    ['id'=>'clients',        'label'=>'Clients',        'icon'=>'people',      'href'=>'/clients.php'],
    ['id'=>'expenses',       'label'=>'Dépenses',       'icon'=>'money-off',   'href'=>'/expenses.php'],
    ['id'=>'declarations',   'label'=>'Déclarations',   'icon'=>'calendar',    'href'=>'/declarations.php'],
    ['id'=>'reminders',      'label'=>'Relances',       'icon'=>'alert-circle','href'=>'/reminders.php'],
    ['id'=>'bank-accounts',  'label'=>'Comptes',        'icon'=>'wallet',      'href'=>'/bank-accounts.php', 'badge'=>'v2.1'],
    ['id'=>'bank',           'label'=>'Mouvements',     'icon'=>'bank',        'href'=>'/bank.php'],
    ['id'=>'report',         'label'=>'Rapport',        'icon'=>'chart',       'href'=>'/report.php'],
    ['id'=>'settings',       'label'=>'Paramètres',     'icon'=>'settings',    'href'=>'/settings.php'],
];

function navSvg(string $icon): string {
    return match($icon) {
        'grid'         => '<path d="M3 3h7v7H3V3zm0 11h7v7H3v-7zm11-11h7v7h-7V3zm0 11h7v7h-7v-7z"/>',
        'document'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'clipboard'    => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="15" y2="16"/>',
        'people'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'money-off'    => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'calendar'     => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'alert-circle' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'bank'         => '<path d="M3 21h18"/><path d="M3 10h18"/><path d="M5 6l7-3 7 3"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M8 14v3"/><path d="M12 14v3"/><path d="M16 14v3"/>',
        'wallet'       => '<path d="M21 12V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5z"/><path d="M16 12h4"/><circle cx="17" cy="12" r="1"/>',
        'chart'        => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
        'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'plus'         => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'mail'         => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'image'        => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'upload'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        default        => '',
    };
}

// Fetch quick stats for sidebar
$qSideStats = [];
try {
    $db = getDB();
    $qSideStats['accounts'] = (int)$db->query("SELECT COUNT(*) FROM ae_bank_accounts WHERE is_active=1")->fetchColumn();
    $totBal = $db->query("SELECT COALESCE(SUM(opening_balance),0) FROM ae_bank_accounts WHERE is_active=1")->fetchColumn()
            + $db->query("SELECT COALESCE(SUM(credit)-SUM(debit),0) FROM ae_bank_transactions")->fetchColumn();
    $qSideStats['total_balance'] = (float)$totBal;
} catch (\Throwable) {}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0078d4">
    <link rel="manifest" href="/manifest.json">
    <title><?= h($pageName) ?> · Moroccan AE v2.1</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: { extend: {
            colors: {
                fluent: {
                    blue:'#0078d4','blue-dk':'#106ebe','blue-lt':'#eff6fc',
                    green:'#107c10','green-lt':'#dff6dd',
                    red:'#d13438','red-lt':'#fde7e9',
                    purple:'#8764b8','purple-lt':'#f3eaff',
                    amber:'#f7630c','amber-lt':'#fff4ce',
                    neutral:'#323130','n2':'#605e5c','n3':'#a19f9d',
                    'n4':'#c8c6c4','n5':'#edebe9','n6':'#f3f2f1','n7':'#faf9f8'
                }
            },
            fontFamily: { sans: ['"Segoe UI Variable"','"Segoe UI"','system-ui','sans-serif'] },
            boxShadow: {
                'f':    '0 1.6px 3.6px rgba(0,0,0,.06), 0 .3px .9px rgba(0,0,0,.04)',
                'fm':   '0 3.2px 7.2px rgba(0,0,0,.08), 0 .6px 1.8px rgba(0,0,0,.05)',
                'fl':   '0 6.4px 14.4px rgba(0,0,0,.13), 0 1.2px 3.6px rgba(0,0,0,.08)',
                'fxl':  '0 25.6px 57.6px rgba(0,0,0,.2),  0 4.8px 14.4px rgba(0,0,0,.13)'
            },
            animation: {
                'fade-in':  'fadeIn .25s ease-out',
                'slide-up': 'slideUp .3s ease-out',
                'pulse-soft': 'pulseSoft 2s ease-in-out infinite'
            },
            keyframes: {
                fadeIn: {'0%':{opacity:0},'100%':{opacity:1}},
                slideUp: {'0%':{opacity:0,transform:'translateY(8px)'},'100%':{opacity:1,transform:'translateY(0)'}},
                pulseSoft: {'0%,100%':{opacity:1},'50%':{opacity:.7}}
            }
        }}
    }
    </script>
    <style>
        /* ── Fluent surface tokens ───────────────────────────── */
        .acrylic { backdrop-filter:saturate(180%) blur(20px); -webkit-backdrop-filter:saturate(180%) blur(20px); background:rgba(255,255,255,.72); }
        .dark .acrylic { background:rgba(32,31,30,.72); }
        body { font-feature-settings: "ss01","cv11"; }
        .btn-f { transition: transform .08s ease, box-shadow .12s ease, background .12s ease; }
        .btn-f:hover { transform: translateY(-1px); }
        .btn-f:active { transform: translateY(0); }

        /* Badges */
        .badge-paid     { background:#dff6dd; color:#107c10; }
        .badge-pending  { background:#fff4ce; color:#835b00; }
        .badge-cancelled{ background:#fde7e9; color:#a4262c; }
        .badge-draft    { background:#f3f2f1; color:#605e5c; }
        .badge-sent     { background:#eff6fc; color:#0078d4; }
        .badge-accepted { background:#dff6dd; color:#107c10; }
        .badge-refused  { background:#fde7e9; color:#a4262c; }
        .badge-expired  { background:#fff4ce; color:#835b00; }

        .inp:focus   { outline:none; border-color:#0078d4; box-shadow:0 0 0 1.5px #0078d4; }
        .nav-on      { background:#eff6fc; color:#0078d4; font-weight:600; }
        .dark .nav-on{ background:#1a3550; color:#5eb3f8; }

        /* Dark mode */
        .dark body, .dark { background:#1b1a19; color:#f3f2f1; }
        .dark .bg-white  { background:#252423 !important; }
        .dark .bg-fluent-n6, .dark .bg-fluent-n7 { background:#1b1a19 !important; }
        .dark .border-fluent-n5 { border-color:#3b3a39 !important; }
        .dark .text-fluent-neutral { color:#f3f2f1; }
        .dark .text-fluent-n2 { color:#c8c6c4; }
        .dark .text-fluent-n3 { color:#a19f9d; }

        /* Animations */
        .notif-dot { animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
        .pb-safe { padding-bottom: env(safe-area-inset-bottom, 0px); }

        /* Row hover */
        .check-row:has(input:checked) { background:#eff6fc; }
        .dark .check-row:has(input:checked) { background:#1a3550; }

        /* Custom scrollbar */
        ::-webkit-scrollbar { width:8px; height:8px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:#c8c6c4; border-radius:4px; }
        ::-webkit-scrollbar-thumb:hover { background:#a19f9d; }
        .dark ::-webkit-scrollbar-thumb { background:#3b3a39; }

        /* v2.1 — New floating action helpers */
        .fab-shadow { box-shadow: 0 8px 24px rgba(0,120,212,.35); }
        .ring-blue  { box-shadow: 0 0 0 4px rgba(0,120,212,.15); }
    </style>
</head>
<body class="h-full bg-fluent-n7 font-sans text-fluent-neutral transition-colors duration-200">

<!-- ── MOBILE TOPBAR ───────────────────────────────────────── -->
<header class="lg:hidden fixed top-0 inset-x-0 z-50 acrylic border-b border-fluent-n5 shadow-f">
    <div class="flex items-center h-14 px-3 gap-2">
        <button onclick="toggleDrawer()" class="btn-f p-2.5 rounded-xl text-fluent-n2 hover:bg-fluent-n6 dark:hover:bg-white/10" aria-label="Menu">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="3" y1="7" x2="21" y2="7"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="17" x2="21" y2="17"/></svg>
        </button>
        <button onclick="openSearch()" class="flex-1 flex items-center gap-2 px-3 py-2 bg-fluent-n6 dark:bg-white/10 rounded-xl text-sm text-fluent-n3 hover:bg-fluent-n5">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <span class="truncate">Rechercher…</span>
        </button>
        <button onclick="toggleNotifs()" class="relative btn-f p-2.5 rounded-xl text-fluent-n2 hover:bg-fluent-n6 dark:hover:bg-white/10">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?php if ($notifCount): ?><span class="notif-dot absolute top-1.5 right-1.5 w-2 h-2 bg-fluent-red rounded-full"></span><?php endif; ?>
        </button>
        <a href="/invoice-new.php" class="btn-f flex items-center gap-1 bg-fluent-blue text-white px-3 py-2 rounded-xl text-xs font-semibold shadow-f">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Facture
        </a>
    </div>
</header>

<div id="drawer-overlay" onclick="toggleDrawer()" class="lg:hidden fixed inset-0 z-40 bg-black/50 hidden"></div>

<!-- ── SIDEBAR ──────────────────────────────────────────────── -->
<aside id="sidebar" class="fixed left-0 top-0 bottom-0 z-50 w-64 flex flex-col bg-white dark:bg-gray-900 border-r border-fluent-n5 dark:border-white/10 shadow-fl -translate-x-full lg:translate-x-0 transition-transform duration-200">

    <!-- Brand with logo -->
    <div class="flex items-center gap-3 h-16 px-4 border-b border-fluent-n5 dark:border-white/10 flex-shrink-0">
        <?php if (!empty($cfg['logo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $cfg['logo_path'])): ?>
        <img src="<?= h($cfg['logo_path']) ?>?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].$cfg['logo_path']) ?>"
            alt="Logo" class="w-9 h-9 rounded-xl object-contain bg-white p-0.5 shadow-f flex-shrink-0">
        <?php else: ?>
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-fluent-blue to-blue-700 flex items-center justify-center shadow-f flex-shrink-0">
            <span class="text-white font-bold text-sm">AE</span>
        </div>
        <?php endif; ?>
        <div class="min-w-0 flex-1">
            <div class="font-bold text-sm text-fluent-neutral truncate"><?= h($cfg['owner_name'] ?: 'Moroccan AE') ?></div>
            <?php if ($activities): ?>
            <div class="text-[10px] text-fluent-n3 truncate"><?= h($activities[0]) ?></div>
            <?php else: ?>
            <div class="text-[10px] text-fluent-n3">Auto-Entrepreneur</div>
            <?php endif; ?>
        </div>
        <span class="text-[9px] font-bold text-fluent-blue bg-fluent-blue-lt dark:bg-fluent-blue/20 px-1.5 py-0.5 rounded">v2.1</span>
    </div>

    <!-- Quick search -->
    <div class="px-3 pt-3 pb-1">
        <div class="relative">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-fluent-n3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="sidebar-search" type="text" placeholder="Rechercher… (Ctrl+K)" autocomplete="off"
                class="w-full pl-7 pr-3 py-2 text-xs bg-fluent-n6 dark:bg-white/10 border border-transparent rounded-xl inp placeholder-fluent-n3 focus:bg-white dark:focus:bg-white/20"
                oninput="sidebarSearch(this.value)" onfocus="showSearchDrop()" onblur="setTimeout(hideSearchDrop,200)">
            <div id="sidebar-results" class="hidden absolute top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 rounded-xl shadow-fl border border-fluent-n5 dark:border-white/10 z-50 overflow-hidden max-h-72 overflow-y-auto"></div>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-2 py-2 space-y-0.5">
        <?php foreach ($nav as $item):
            $active = $currentPage === $item['id'];
            $overdueCount  = $item['id']==='reminders' ? count(getOverdueInvoices()) : 0;
            $pendingQuotes = $item['id']==='quotes' ? (int)getDB()->query("SELECT COUNT(*) FROM ae_quotes WHERE status='Envoyé' AND valid_until >= CURDATE()")->fetchColumn() : 0;
        ?>
        <a href="<?= h($item['href']) ?>"
            class="btn-f flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors
            <?= $active ? 'nav-on' : 'text-fluent-n2 dark:text-gray-300 hover:bg-fluent-n6 dark:hover:bg-white/10' ?>">
            <svg class="w-5 h-5 flex-shrink-0 <?= $active?'text-fluent-blue':'text-fluent-n3' ?>"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="<?= $active?'2':'1.5' ?>">
                <?= navSvg($item['icon']) ?>
            </svg>
            <span class="flex-1 truncate"><?= h($item['label']) ?></span>
            <?php if (!empty($item['badge'])): ?>
            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-fluent-purple text-white"><?= h($item['badge']) ?></span>
            <?php endif; ?>
            <?php if ($overdueCount > 0): ?>
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-fluent-red text-white min-w-[18px] text-center"><?= $overdueCount ?></span>
            <?php elseif ($pendingQuotes > 0): ?>
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-fluent-blue text-white min-w-[18px] text-center"><?= $pendingQuotes ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Multi-account quick widget (v2.1) -->
    <?php if (!empty($qSideStats['accounts']) && $qSideStats['accounts'] > 0 && !in_array($currentPage, ['bank-accounts'])): ?>
    <div class="mx-3 mb-2 p-3 bg-gradient-to-br from-fluent-blue to-blue-700 rounded-xl text-white shadow-f">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider opacity-80">Solde total</span>
            <span class="text-[9px] bg-white/20 px-1.5 py-0.5 rounded"><?= $qSideStats['accounts'] ?> compte<?= $qSideStats['accounts']>1?'s':'' ?></span>
        </div>
        <div class="font-bold text-lg"><?= money($qSideStats['total_balance'] ?? 0) ?></div>
        <a href="/bank-accounts.php" class="text-[11px] opacity-90 hover:opacity-100 mt-1 inline-block">Gérer →</a>
    </div>
    <?php endif; ?>

    <!-- User footer -->
    <div class="border-t border-fluent-n5 dark:border-white/10 p-3 flex-shrink-0">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-fluent-blue to-purple-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                <?= strtoupper(substr($user['username']??'A', 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold text-fluent-neutral truncate"><?= h($user['username']??'admin') ?></div>
                <a href="/logout.php" class="text-[10px] text-fluent-n3 hover:text-fluent-red">Déconnexion</a>
            </div>
            <button onclick="toggleDark()" class="btn-f p-1.5 rounded-lg text-fluent-n3 hover:bg-fluent-n6 dark:hover:bg-white/10" aria-label="Thème">
                <svg id="icon-light" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
                <svg id="icon-dark" class="w-4 h-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </div>
    </div>
</aside>

<!-- ── MAIN AREA ────────────────────────────────────────────── -->
<div class="lg:pl-64 min-h-full flex flex-col">

    <!-- Desktop topbar -->
    <div class="hidden lg:flex items-center justify-between h-14 px-6 bg-white dark:bg-gray-900 border-b border-fluent-n5 dark:border-white/10 shadow-f sticky top-0 z-30">
        <div class="flex items-center gap-2 text-sm">
            <span class="font-semibold text-fluent-neutral"><?= h($pageName) ?></span>
            <span class="text-fluent-n4">·</span>
            <span class="text-xs text-fluent-n3"><?= date('d/m/Y') ?></span>
            <?php if ($activities): ?>
            <span class="text-fluent-n4">·</span>
            <span class="text-xs text-fluent-blue font-medium"><?= h(implode(' / ', $activities)) ?></span>
            <?php endif; ?>
            <?php if (daysUntilDeclaration() <= 15): ?>
            <a href="/declarations.php" class="text-[10px] px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full font-semibold animate-pulse ml-1">
                📅 Déclaration dans <?= daysUntilDeclaration() ?>j
            </a>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-2">
            <!-- Notifications -->
            <div class="relative" id="notif-wrapper">
                <button onclick="toggleNotifs()" class="btn-f relative p-2 rounded-xl text-fluent-n2 hover:bg-fluent-n6 dark:hover:bg-white/10">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($notifCount): ?><span class="notif-dot absolute top-1.5 right-1.5 w-2 h-2 bg-fluent-red rounded-full"></span><?php endif; ?>
                </button>
                <div id="notif-panel" class="hidden absolute right-0 top-full mt-2 w-80 bg-white dark:bg-gray-800 rounded-2xl shadow-fl border border-fluent-n5 dark:border-white/10 z-50 overflow-hidden">
                    <div class="px-4 py-3 border-b border-fluent-n5 dark:border-white/10 flex justify-between items-center">
                        <div class="text-sm font-semibold text-fluent-neutral">Notifications</div>
                        <span class="text-xs text-fluent-n3"><?= $notifCount ?></span>
                    </div>
                    <div class="max-h-80 overflow-y-auto">
                        <?php if (empty($notifications)): ?>
                        <div class="px-4 py-8 text-center text-xs text-fluent-n3">Aucune notification</div>
                        <?php else: foreach ($notifications as $n): ?>
                        <a href="<?= h($n['href']??'#') ?>" class="block px-4 py-3 hover:bg-fluent-n6 dark:hover:bg-white/5 border-b border-fluent-n6 dark:border-white/5">
                            <div class="text-sm font-medium text-fluent-neutral"><?= h($n['title']??'') ?></div>
                            <div class="text-xs text-fluent-n3 mt-0.5"><?= h($n['desc']??'') ?></div>
                        </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <!-- Theme toggle (already in sidebar but mirror here for desktop convenience) -->
            <button onclick="openSearch()" class="btn-f flex items-center gap-2 px-3 py-2 bg-fluent-n6 dark:bg-white/10 rounded-xl text-xs text-fluent-n3 hover:bg-fluent-n5 dark:hover:bg-white/20 min-w-[180px]">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span>Rechercher…</span>
                <kbd class="ml-auto text-[10px] px-1.5 py-0.5 bg-white dark:bg-white/10 rounded font-mono">⌘K</kbd>
            </button>
            <a href="/invoice-new.php" class="btn-f flex items-center gap-1.5 bg-fluent-blue text-white px-3 py-2 rounded-xl text-xs font-semibold shadow-f hover:bg-fluent-blue-dk">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nouvelle Facture
            </a>
        </div>
    </div>

    <main class="flex-1 pt-16 lg:pt-0 pb-20 lg:pb-0 px-4 lg:px-6 py-4 lg:py-5 animate-fade-in">

<!-- (page content goes here) -->
