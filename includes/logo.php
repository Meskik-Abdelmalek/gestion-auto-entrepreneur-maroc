<?php
// ── Logo Upload Helper v2.1 ───────────────────────────────────
// Safe against: missing DB columns, non-writable dirs, Hostinger quirks.

function _logoColumnExists(): bool
{
    static $checked = null;
    if ($checked !== null) return $checked;
    try {
        $cols = getDB()->query("SHOW COLUMNS FROM ae_config LIKE 'logo_path'")->fetchAll();
        $checked = count($cols) > 0;
    } catch (\Throwable) {
        $checked = false;
    }
    return $checked;
}

function handleLogoUpload(array $file): array
{
    // ── Guard: column must exist ──────────────────────────────
    if (!_logoColumnExists()) {
        return [
            'ok'    => false,
            'path'  => null,
            'error' => 'La colonne logo_path n\'existe pas encore dans la base de données. '
                     . 'Exécutez sql/migrate_v2.1_safe.sql dans phpMyAdmin avant d\'uploader un logo.',
        ];
    }

    // ── Resolve upload directory ──────────────────────────────
    // Works whether the project is in public_html root or a subdirectory
    $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $uploadDir = $docRoot . '/uploads/logos/';
    $webPath   = '/uploads/logos/';

    // ── Create directory ──────────────────────────────────────
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) {
            // Try relative path fallback (some Hostinger configs)
            $uploadDir = __DIR__ . '/../uploads/logos/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
        }
    }

    if (!is_dir($uploadDir)) {
        return [
            'ok'    => false,
            'path'  => null,
            'error' => 'Impossible de créer le dossier uploads/logos/ — vérifiez les permissions (755) dans le Gestionnaire de Fichiers Hostinger.',
        ];
    }

    if (!is_writable($uploadDir)) {
        return [
            'ok'    => false,
            'path'  => null,
            'error' => 'Le dossier uploads/logos/ existe mais n\'est pas accessible en écriture. Mettez les permissions à 755.',
        ];
    }

    // Protect directory from PHP execution
    $htaccess = $uploadDir . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\nRemoveHandler .php .php5 .phtml\nOptions -ExecCGI\n");
    }

    // ── Validate upload ───────────────────────────────────────
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (limite php.ini).',
            UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux (limite formulaire).',
            UPLOAD_ERR_PARTIAL    => 'Upload incomplet.',
            UPLOAD_ERR_NO_FILE    => 'Aucun fichier reçu.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant sur le serveur.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire sur le disque.',
            UPLOAD_ERR_EXTENSION  => 'Upload bloqué par une extension PHP.',
        ];
        return ['ok' => false, 'path' => null, 'error' => $errors[$file['error']] ?? 'Erreur upload code ' . $file['error']];
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return ['ok' => false, 'path' => null, 'error' => 'Logo trop volumineux (max 2 Mo).'];
    }

    // Validate MIME type
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
    $mime = 'unknown';
    if (function_exists('finfo_open')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
    } else {
        // Fallback for servers without fileinfo
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extMap = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml'];
        $mime = $extMap[$ext] ?? 'unknown';
    }

    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'path' => null, 'error' => 'Format non supporté. Utilisez PNG, JPG, GIF, WEBP ou SVG.'];
    }

    // ── Delete old logo ───────────────────────────────────────
    try {
        $db  = getDB();
        $old = $db->query("SELECT logo_path FROM ae_config WHERE id=1")->fetchColumn();
        if ($old) {
            $oldFile = $docRoot . $old;
            if (file_exists($oldFile)) @unlink($oldFile);
            // Also try relative
            $oldRel = __DIR__ . '/..' . $old;
            if (file_exists($oldRel)) @unlink($oldRel);
        }
    } catch (\Throwable) {}

    // ── Move file ─────────────────────────────────────────────
    $ext      = $allowed[$mime];
    $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'path' => null, 'error' => 'Impossible de déplacer le fichier. Vérifiez les permissions du dossier uploads/logos/'];
    }

    // ── Save to DB ────────────────────────────────────────────
    $path = $webPath . $filename;
    try {
        getDB()->prepare("UPDATE ae_config SET logo_path=? WHERE id=1")->execute([$path]);
    } catch (\Throwable $e) {
        @unlink($dest);
        return ['ok' => false, 'path' => null, 'error' => 'Fichier uploadé mais erreur DB : ' . $e->getMessage()];
    }

    return ['ok' => true, 'path' => $path, 'error' => null];
}

function deleteLogo(): void
{
    if (!_logoColumnExists()) return;
    try {
        $db      = getDB();
        $old     = $db->query("SELECT logo_path FROM ae_config WHERE id=1")->fetchColumn();
        $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        if ($old) {
            foreach ([$docRoot . $old, __DIR__ . '/..' . $old] as $f) {
                if ($f && file_exists($f)) @unlink($f);
            }
        }
        $db->prepare("UPDATE ae_config SET logo_path=NULL WHERE id=1")->execute([]);
    } catch (\Throwable) {}
}

function getLogoPath(): ?string
{
    if (!_logoColumnExists()) return null;
    try {
        $path = getDB()->query("SELECT logo_path FROM ae_config WHERE id=1")->fetchColumn();
        return $path ?: null;
    } catch (\Throwable) {
        return null;
    }
}

function logoExistsOnDisk(?string $path): bool
{
    if (!$path) return false;
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    return file_exists($docRoot . $path) || file_exists(__DIR__ . '/..' . $path);
}
