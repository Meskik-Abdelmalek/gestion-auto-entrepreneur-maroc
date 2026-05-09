<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in?
if (isLoggedIn()) { header('Location: /dashboard.php'); exit; }

$error    = '';
$success  = '';
$redirect = clean($_GET['redirect'] ?? '/dashboard.php');
// Safety: only allow relative redirects
if (!str_starts_with($redirect, '/')) $redirect = '/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF check on login
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['login_csrf'] ?? '', $token)) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } else {
        $username = clean($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (!$username || !$password) {
            $error = 'Veuillez renseigner tous les champs.';
        } else {
            $result = attemptLogin($username, $password, $remember);
            if ($result['ok']) {
                header('Location: ' . $redirect);
                exit;
            }
            $error = $result['error'];
        }
    }
}

// Generate login CSRF (separate from main app CSRF)
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

$cfg = [];
try { $cfg = getDB()->query("SELECT owner_name FROM ae_config WHERE id=1")->fetch() ?: []; } catch (Throwable) {}

function clean(mixed $v): string { return trim((string)$v); }
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Connexion — Moroccan AE System</title>
    <meta name="theme-color" content="#0078d4">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: { extend: {
            colors: { fluent: { blue:'#0078d4','blue-dk':'#106ebe','blue-lt':'#eff6fc', neutral:'#323130','n2':'#605e5c','n3':'#a19f9d','n4':'#c8c6c4','n5':'#edebe9','n6':'#f3f2f1','n7':'#faf9f8' }},
            fontFamily: { sans: ['"Segoe UI"','system-ui','sans-serif'] }
        }}
    }
    </script>
    <style>
        .inp:focus { outline:none; border-color:#0078d4; box-shadow:0 0 0 1.5px #0078d4; }
        .btn-f { position:relative; overflow:hidden; transition:all .12s; }
        .btn-f:hover::after { content:''; position:absolute; inset:0; background:rgba(255,255,255,.1); }
        @keyframes fadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        .anim { animation: fadeUp .25s ease-out; }
        .bg-pattern {
            background-color: #0078d4;
            background-image: radial-gradient(circle at 20% 80%, rgba(255,255,255,.08) 0%, transparent 50%),
                              radial-gradient(circle at 80% 20%, rgba(255,255,255,.05) 0%, transparent 50%),
                              radial-gradient(circle at 50% 50%, rgba(0,50,120,.3) 0%, transparent 60%);
        }
    </style>
</head>
<body class="h-full bg-fluent-n7 font-sans flex items-center justify-center min-h-screen">

<!-- Background decoration -->
<div class="fixed inset-0 bg-pattern opacity-90 -z-10"></div>
<div class="fixed inset-0 bg-fluent-n7/10 backdrop-blur-3xl -z-10"></div>

<!-- Card -->
<div class="w-full max-w-sm px-4 anim">
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

        <!-- Header -->
        <div class="bg-pattern px-8 pt-10 pb-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm mb-4 shadow-lg">
                <span class="text-white font-bold text-2xl tracking-tight">AE</span>
            </div>
            <h1 class="text-xl font-bold text-white">Moroccan AE System</h1>
            <p class="text-white/70 text-sm mt-1">
                <?= $cfg['owner_name'] ? h($cfg['owner_name']) : 'Système de gestion' ?>
            </p>
        </div>

        <!-- Form -->
        <div class="px-8 py-7">
            <h2 class="text-base font-semibold text-fluent-neutral mb-5 text-center">Connexion à votre espace</h2>

            <?php if ($error): ?>
            <div class="mb-4 flex items-start gap-2.5 px-4 py-3 bg-red-50 text-red-700 rounded-xl text-sm border border-red-200">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= h($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4" autocomplete="on">
                <input type="hidden" name="_csrf" value="<?= h($_SESSION['login_csrf']) ?>">
                <input type="hidden" name="redirect" value="<?= h($redirect) ?>">

                <div>
                    <label class="block text-xs font-semibold text-fluent-n2 mb-1.5">Nom d'utilisateur</label>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fluent-n3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" name="username" autocomplete="username"
                            value="<?= h(clean($_POST['username'] ?? '')) ?>"
                            placeholder="admin"
                            class="w-full pl-10 pr-4 py-3 text-sm border border-fluent-n4 rounded-xl inp bg-white"
                            required autofocus>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-fluent-n2 mb-1.5">Mot de passe</label>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fluent-n3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        <input type="password" name="password" id="pw-input"
                            autocomplete="current-password" placeholder="••••••••"
                            class="w-full pl-10 pr-11 py-3 text-sm border border-fluent-n4 rounded-xl inp bg-white"
                            required>
                        <button type="button" onclick="togglePw()" class="absolute right-3 top-1/2 -translate-y-1/2 text-fluent-n3 hover:text-fluent-neutral">
                            <svg id="eye-icon" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="w-4 h-4 text-fluent-blue rounded border-fluent-n4">
                        <span class="text-xs text-fluent-n2">Se souvenir de moi (30 jours)</span>
                    </label>
                </div>

                <button type="submit"
                    class="btn-f w-full py-3 bg-fluent-blue hover:bg-fluent-blue-dk text-white rounded-xl font-semibold text-sm transition-colors shadow-md mt-1">
                    Connexion →
                </button>
            </form>

            <!-- Info box -->
            <div class="mt-6 p-3.5 bg-fluent-n7 rounded-xl border border-fluent-n5">
                <p class="text-xs text-fluent-n2 font-medium mb-1">🔐 Première connexion ?</p>
                <p class="text-[11px] text-fluent-n3 leading-relaxed">
                    Identifiants par défaut&nbsp;: <code class="font-mono bg-white px-1 py-0.5 rounded text-fluent-neutral border border-fluent-n4">admin</code> /
                    <code class="font-mono bg-white px-1 py-0.5 rounded text-fluent-neutral border border-fluent-n4">AEMaroc2026!</code><br>
                    Changez-les immédiatement dans les <strong>Paramètres</strong>.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-8 pb-6 text-center">
            <p class="text-[11px] text-fluent-n3">
                Moroccan AE System · Open Source · MIT License
            </p>
        </div>
    </div>
</div>

<script>
function togglePw() {
    const inp = document.getElementById('pw-input');
    const ico = document.getElementById('eye-icon');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        inp.type = 'password';
        ico.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}
// Apply dark mode if stored
if (localStorage.getItem('ae-dark')==='1') document.documentElement.classList.add('dark');
</script>
</body>
</html>
<?php function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');} ?>
