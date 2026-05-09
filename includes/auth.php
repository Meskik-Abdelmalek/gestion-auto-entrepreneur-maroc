<?php
/**
 * Moroccan AE System — Auth Layer
 * Handles login, session validation, rate-limiting, remember-me
 */

if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 3600 * 8); // 8 hours
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

define('AUTH_LOGIN_PAGE', '/login.php');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);
define('REMEMBER_DAYS', 30);

// ── Guard: require authentication ─────────────────────────────
function requireAuth(): void {
    if (isLoggedIn()) return;

    // Check remember-me cookie
    if (isset($_COOKIE['ae_remember']) && rememberLogin($_COOKIE['ae_remember'])) return;

    $redirect = urlencode($_SERVER['REQUEST_URI']);
    header('Location: ' . AUTH_LOGIN_PAGE . '?redirect=' . $redirect);
    exit;
}

// ── Check if user is logged in ────────────────────────────────
function isLoggedIn(): bool {
    if (empty($_SESSION['ae_user_id'])) return false;
    // Validate session fingerprint to prevent hijacking
    $fp = sessionFingerprint();
    if (($_SESSION['ae_fingerprint'] ?? '') !== $fp) {
        session_destroy();
        return false;
    }
    // Sliding session expiry
    $_SESSION['ae_last_active'] = time();
    return true;
}

// ── Session fingerprint (IP + partial UA) ────────────────────
function sessionFingerprint(): string {
    $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 64);
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256', $ip . $ua . (defined('DB_NAME') ? DB_NAME : ''));
}

// ── Attempt login ─────────────────────────────────────────────
function attemptLogin(string $username, string $password, bool $remember = false): array {
    $db = getDB();

    // Fetch user
    $st = $db->prepare("SELECT * FROM ae_users WHERE username = ? LIMIT 1");
    $st->execute([trim($username)]);
    $user = $st->fetch();

    if (!$user) {
        // Timing-safe dummy check to prevent user enumeration
        password_verify($password, '$2y$10$dummy.hash.to.prevent.timing.attacks.xxxxx');
        return ['ok' => false, 'error' => 'Identifiants incorrects.'];
    }

    // Check lockout
    if ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
        $remaining = (int)ceil((strtotime($user['locked_until']) - time()) / 60);
        return ['ok' => false, 'error' => "Compte temporairement verrouillé. Réessayez dans {$remaining} min."];
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        $attempts = $user['login_attempts'] + 1;
        $lock = $attempts >= MAX_LOGIN_ATTEMPTS
            ? date('Y-m-d H:i:s', strtotime('+' . LOCKOUT_MINUTES . ' minutes'))
            : null;
        $db->prepare("UPDATE ae_users SET login_attempts=?, locked_until=? WHERE id=?")
           ->execute([$attempts, $lock, $user['id']]);
        $left = MAX_LOGIN_ATTEMPTS - $attempts;
        $msg  = $left > 0
            ? "Mot de passe incorrect. Il vous reste $left tentative(s)."
            : "Trop de tentatives. Compte verrouillé " . LOCKOUT_MINUTES . " min.";
        return ['ok' => false, 'error' => $msg];
    }

    // Success — reset attempts, update last login
    $db->prepare("UPDATE ae_users SET login_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?")
       ->execute([$user['id']]);

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);
    $_SESSION['ae_user_id']    = $user['id'];
    $_SESSION['ae_username']   = $user['username'];
    $_SESSION['ae_fingerprint']= sessionFingerprint();
    $_SESSION['ae_last_active']= time();

    // Remember me
    if ($remember) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+' . REMEMBER_DAYS . ' days'));
        $hash    = hash('sha256', $token);
        $db->prepare("UPDATE ae_users SET remember_token=?, remember_expires=? WHERE id=?")
           ->execute([$hash, $expires, $user['id']]);
        $cookieExpiry = time() + (REMEMBER_DAYS * 86400);
        setcookie('ae_remember', $token, [
            'expires'  => $cookieExpiry,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    return ['ok' => true, 'user' => $user];
}

// ── Remember-me cookie login ──────────────────────────────────
function rememberLogin(string $token): bool {
    if (strlen($token) !== 64) return false;
    $db   = getDB();
    $hash = hash('sha256', $token);
    $st   = $db->prepare("SELECT * FROM ae_users WHERE remember_token=? AND remember_expires > NOW() LIMIT 1");
    $st->execute([$hash]);
    $user = $st->fetch();
    if (!$user) return false;

    // Rotate token
    $newToken = bin2hex(random_bytes(32));
    $newHash  = hash('sha256', $newToken);
    $expires  = date('Y-m-d H:i:s', strtotime('+' . REMEMBER_DAYS . ' days'));
    $db->prepare("UPDATE ae_users SET remember_token=?, remember_expires=?, last_login=NOW() WHERE id=?")
       ->execute([$newHash, $expires, $user['id']]);
    setcookie('ae_remember', $newToken, [
        'expires'  => time() + (REMEMBER_DAYS * 86400),
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_regenerate_id(true);
    $_SESSION['ae_user_id']    = $user['id'];
    $_SESSION['ae_username']   = $user['username'];
    $_SESSION['ae_fingerprint']= sessionFingerprint();
    $_SESSION['ae_last_active']= time();
    return true;
}

// ── Logout ────────────────────────────────────────────────────
function logout(): void {
    $db = getDB();
    if (!empty($_SESSION['ae_user_id'])) {
        $db->prepare("UPDATE ae_users SET remember_token=NULL, remember_expires=NULL WHERE id=?")
           ->execute([$_SESSION['ae_user_id']]);
    }
    // Clear cookie
    setcookie('ae_remember', '', ['expires' => time()-3600, 'path' => '/']);
    // Destroy session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Get current user ──────────────────────────────────────────
function currentUser(): ?array {
    if (empty($_SESSION['ae_user_id'])) return null;
    static $u = null;
    if ($u === null) {
        $st = getDB()->prepare("SELECT id,username,email,last_login FROM ae_users WHERE id=?");
        $st->execute([$_SESSION['ae_user_id']]);
        $u = $st->fetch() ?: null;
    }
    return $u;
}

// ── Change password ───────────────────────────────────────────
function changePassword(int $userId, string $currentPw, string $newPw): array {
    if (strlen($newPw) < 8) return ['ok' => false, 'error' => 'Le mot de passe doit contenir au moins 8 caractères.'];
    $db = getDB();
    $st = $db->prepare("SELECT password_hash FROM ae_users WHERE id=?");
    $st->execute([$userId]); $u = $st->fetch();
    if (!$u || !password_verify($currentPw, $u['password_hash']))
        return ['ok' => false, 'error' => 'Mot de passe actuel incorrect.'];
    $db->prepare("UPDATE ae_users SET password_hash=? WHERE id=?")
       ->execute([password_hash($newPw, PASSWORD_DEFAULT), $userId]);
    return ['ok' => true];
}

// ── First-run: create default admin if no users exist ─────────
function ensureDefaultUser(): void {
    $db = getDB();
    $count = (int)$db->query("SELECT COUNT(*) FROM ae_users")->fetchColumn();
    if ($count === 0) {
        // Default credentials: admin / AEMaroc2026! — user MUST change on first login
        $hash = password_hash('AEMaroc2026!', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO ae_users (username, password_hash, email) VALUES (?,?,?)")
           ->execute(['admin', $hash, '']);
    }
}
