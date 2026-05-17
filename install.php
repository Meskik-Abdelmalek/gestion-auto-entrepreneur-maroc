<?php
/**
 * Moroccan AE System v2.1 — Installer
 * DELETE THIS FILE AFTER INSTALLATION
 */
declare(strict_types=1);

if (file_exists(__DIR__ . '/install.lock')) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Locked</title><style>body{font-family:"Segoe UI",sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f3f2f1}.card{background:#fff;border-radius:16px;padding:32px;max-width:440px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,.1);text-align:center}.icon{font-size:48px;margin-bottom:16px}.title{font-size:18px;font-weight:700;color:#323130;margin:0 0 8px}.desc{font-size:13px;color:#605e5c;line-height:1.6}code{background:#fef2f2;color:#d13438;padding:2px 8px;border-radius:6px;font-family:monospace}</style></head><body><div class="card"><div class="icon">🔒</div><h1 class="title">Installateur désactivé</h1><p class="desc">L\'installation est terminée (<code>install.lock</code> présent).<br><br>Pour réinstaller, supprimez <code>install.lock</code> via FTP ou le Gestionnaire de Fichiers Hostinger.</p></div></body></html>');
}

session_start();

function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }

function runSql(PDO $pdo, string $raw): array {
    $sql = preg_replace('/\/\*.*?\*\//s', '', $raw);
    $stmts=[]; $buf=''; $inStr=false; $ch='';
    for ($i=0,$len=strlen($sql); $i<$len; $i++) {
        $c=$sql[$i];
        if (!$inStr && ($c==='"'||$c==="'")) { $inStr=true; $ch=$c; $buf.=$c; }
        elseif ($inStr && $c===$ch && ($i===0||$sql[$i-1]!=='\\')) { $inStr=false; $buf.=$c; }
        elseif (!$inStr && $c===';') { $s=trim($buf); if($s) $stmts[]=$s; $buf=''; }
        else { $buf.=$c; }
    }
    if (trim($buf)) $stmts[]=trim($buf);
    $errors=[];
    foreach ($stmts as $stmt) {
        $lines=[];
        foreach(explode("\n",$stmt) as $ln){if(($p=strpos($ln,'--'))!==false)$ln=substr($ln,0,$p);$lines[]=$ln;}
        $stmt=trim(implode("\n",$lines));
        if(!$stmt) continue;
        if(preg_match('/^(CREATE\s+DATABASE|USE\s+|SELECT\s+\')/i',$stmt)) continue;
        try { $pdo->exec($stmt); }
        catch(PDOException $e) {
            $msg=$e->getMessage();
            if(str_contains($msg,'already exists')||str_contains($msg,'Duplicate')||in_array($e->getCode(),['42S01','42S11'])) continue;
            $errors[]=$msg;
        }
    }
    return $errors;
}

function writeConfig(string $h2,string $u,string $p,string $n): void {
    $hh=addslashes($h2); $uu=addslashes($u); $pp=addslashes($p); $nn=addslashes($n);
    $c="<?php\ndefine('DB_HOST','{$hh}');\ndefine('DB_USER','{$uu}');\ndefine('DB_PASS','{$pp}');\ndefine('DB_NAME','{$nn}');\ndefine('DB_CHARSET','utf8mb4');\ndefine('APP_NAME','Moroccan AE System');\ndefine('APP_VERSION','2.1.0');\ndefine('APP_LOCALE','fr_MA');\ndefine('APP_TIMEZONE','Africa/Casablanca');\ndate_default_timezone_set(APP_TIMEZONE);\nsetlocale(LC_TIME,APP_LOCALE.'.UTF-8',APP_LOCALE,'fr_FR.UTF-8','fr_FR');\nfunction getDB():PDO{static \$x=null;if(\$x===null){\$d=sprintf('mysql:host=%s;dbname=%s;charset=%s',DB_HOST,DB_NAME,DB_CHARSET);\$x=new PDO(\$d,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}return \$x;}\n";
    if(!file_put_contents(__DIR__.'/config.php',$c)) throw new RuntimeException("Impossible d'écrire config.php — vérifiez les permissions (chmod 755).");
}

$isUpgrade=false; $existingCfg=[];
if(file_exists(__DIR__.'/config.php')){
    @require_once __DIR__.'/config.php';
    try{$existingCfg=getDB()->query("SELECT * FROM ae_config WHERE id=1")->fetch()?:[];$isUpgrade=true;}catch(\Throwable){}
}

$step=max(1,min(4,(int)($_GET['step']??1)));
$error='';

// STEP 1 — Database
if($_SERVER['REQUEST_METHOD']==='POST'&&$step===1){
    $dbHost=trim($_POST['db_host']??'localhost');
    $dbUser=trim($_POST['db_user']??'');
    $dbPass=trim($_POST['db_pass']??'');
    $dbName=trim($_POST['db_name']??'');
    if(!$dbName||!$dbUser){ $error='Nom de BDD et utilisateur sont obligatoires.'; }
    else {
        try {
            $pdo=new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
            $errs=runSql($pdo,file_get_contents(__DIR__.'/sql/schema.sql'));
            if(file_exists(__DIR__.'/sql/migrate_v2.1_safe.sql')) $errs=array_merge($errs,runSql($pdo,file_get_contents(__DIR__.'/sql/migrate_v2.1_safe.sql')));
            writeConfig($dbHost,$dbUser,$dbPass,$dbName);
            @mkdir(__DIR__.'/uploads/logos/',0755,true);
            @file_put_contents(__DIR__.'/uploads/logos/.htaccess',"Options -Indexes\nRemoveHandler .php .php5 .phtml\nOptions -ExecCGI\n");
            @mkdir(__DIR__.'/uploads/',0755,true);
            @file_put_contents(__DIR__.'/uploads/.htaccess',"Options -Indexes\n");
            $_SESSION['sw']=$errs;
            header('Location: /install.php?step=2'); exit;
        } catch(PDOException $e) {
            $raw=$e->getMessage();
            if(str_contains($raw,'Access denied')) $error="Accès refusé — vérifiez :\n• Nom BDD exact avec préfixe (ex: u123456_finance)\n• Utilisateur MySQL exact (ex: u123456_user)\n• Mot de passe de l'utilisateur MySQL (≠ mot de passe cPanel/Hostinger)\n• L'utilisateur doit avoir ALL PRIVILEGES sur la BDD";
            elseif(str_contains($raw,'Unknown database')) $error="Base «{$dbName}» introuvable.\nCréez-la d'abord : hPanel → Bases de données MySQL → Créer.";
            elseif(str_contains($raw,"Can't connect")||str_contains($raw,'refused')) $error="Impossible de joindre MySQL sur «{$dbHost}».\nSur Hostinger, l'hôte est toujours «localhost».";
            else $error='Erreur MySQL : '.$raw;
        } catch(RuntimeException $e){ $error=$e->getMessage(); }
    }
}

// STEP 2 — Admin
if($_SERVER['REQUEST_METHOD']==='POST'&&$step===2){
    if(!file_exists(__DIR__.'/config.php')){header('Location: /install.php?step=1');exit;}
    require_once __DIR__.'/config.php';
    $u=trim($_POST['username']??'admin'); $p=$_POST['password']??''; $c=$_POST['confirm']??'';
    if(strlen($u)<3) $error="Nom trop court (min 3 car).";
    elseif(strlen($p)<8) $error="Mot de passe trop court (min 8 car.).";
    elseif($p!==$c) $error="Les mots de passe ne correspondent pas.";
    else {
        try {
            $db=getDB(); $hash=password_hash($p,PASSWORD_DEFAULT);
            $st=$db->prepare("SELECT id FROM ae_users WHERE username=?"); $st->execute([$u]);
            if($st->fetchColumn()) $db->prepare("UPDATE ae_users SET password_hash=? WHERE username=?")->execute([$hash,$u]);
            else{$db->prepare("DELETE FROM ae_users")->execute();$db->prepare("INSERT INTO ae_users(username,password_hash)VALUES(?,?)")->execute([$u,$hash]);}
            header('Location: /install.php?step=3'); exit;
        } catch(\Throwable $e){$error='Erreur: '.$e->getMessage();}
    }
}

// STEP 3 — Profile
if($_SERVER['REQUEST_METHOD']==='POST'&&$step===3){
    if(!file_exists(__DIR__.'/config.php')){header('Location: /install.php?step=1');exit;}
    require_once __DIR__.'/config.php';
    try {
        getDB()->prepare("UPDATE ae_config SET owner_name=?,email=?,ice=?,if_fiscal=?,tp=?,cnss_phone=?,address=?,fiscal_year=?,activity_1=?,activity_2=?,activity_3=? WHERE id=1")
            ->execute([trim($_POST['owner_name']??''),trim($_POST['email']??''),trim($_POST['ice']??''),trim($_POST['if_fiscal']??''),trim($_POST['tp']??''),trim($_POST['phone']??''),trim($_POST['address']??''),(int)($_POST['fiscal_year']??date('Y')),trim($_POST['activity_1']??''),trim($_POST['activity_2']??''),trim($_POST['activity_3']??'')]);
        file_put_contents(__DIR__.'/install.lock','v2.1 installed '.date('Y-m-d H:i:s'));
        header('Location: /install.php?step=4'); exit;
    } catch(\Throwable $e){$error='Erreur: '.$e->getMessage();}
}

$cfg=$existingCfg;
$sw=$_SESSION['sw']??[]; unset($_SESSION['sw']);

// Check requirements
$reqs=[
    'PHP 8.1+' => version_compare(PHP_VERSION,'8.1.0','>='),
    'PDO' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'fileinfo' => extension_loaded('fileinfo'),
    'Dossier inscriptible' => is_writable(__DIR__),
    'sql/schema.sql présent' => file_exists(__DIR__.'/sql/schema.sql'),
];
$allOk = !in_array(false,$reqs,true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Installation v2.1 — Moroccan AE System</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{
    colors:{fluent:{blue:'#0078d4','blue-dk':'#106ebe','blue-lt':'#eff6fc',neutral:'#323130',n2:'#605e5c',n3:'#a19f9d',n4:'#c8c6c4',n5:'#edebe9',n6:'#f3f2f1',n7:'#faf9f8',green:'#107c10',red:'#d13438'}},
    fontFamily:{sans:['"Segoe UI Variable"','"Segoe UI"','system-ui','sans-serif']},
    keyframes:{up:{from:{opacity:0,transform:'translateY(12px)'},to:{opacity:1,transform:'translateY(0)'}}},
    animation:{up:'up .28s ease-out'}
}}}
</script>
<style>
body{min-height:100vh;background:linear-gradient(145deg,#0c1829 0%,#0e2744 50%,#1B2A4A 100%);display:flex;align-items:center;justify-content:center;padding:20px;font-family:"Segoe UI",system-ui,sans-serif}
.glass{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;backdrop-filter:blur(12px)}
.card{background:#fff;border-radius:20px;box-shadow:0 24px 64px rgba(0,0,0,.35);overflow:hidden;animation:up .28s ease-out}
.inp{width:100%;padding:11px 14px;font-size:14px;border:1.5px solid #d1d5db;border-radius:10px;outline:none;transition:border-color .15s,box-shadow .15s;background:#fff;box-sizing:border-box;font-family:inherit;color:#323130}
.inp:focus{border-color:#0078d4;box-shadow:0 0 0 3px rgba(0,120,212,.12)}
.inp::placeholder{color:#a19f9d}
.btn{width:100%;padding:12px 20px;background:#0078d4;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;transition:background .15s,transform .1s;font-family:inherit}
.btn:hover{background:#106ebe;transform:translateY(-1px)}
.btn:active{transform:translateY(0)}
label{display:block;font-size:12px;font-weight:600;color:#605e5c;margin-bottom:5px;letter-spacing:.01em}
.field{display:flex;flex-direction:column;gap:4px}
.err{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:10px;padding:12px 14px;color:#991b1b;font-size:13px;white-space:pre-line;line-height:1.6}
.ok{background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:12px 14px;color:#166534;font-size:13px}
.warn{background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:12px 14px;color:#92400e;font-size:12px}
.info{background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:12px 14px;color:#1e40af;font-size:12px;line-height:1.7}
.step-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;transition:all .2s}
.step-done{background:#22c55e;color:#fff}
.step-active{background:#fff;color:#0078d4;box-shadow:0 0 0 3px rgba(255,255,255,.3)}
.step-pending{background:rgba(255,255,255,.15);color:rgba(255,255,255,.45)}
.divider{height:1px;background:rgba(255,255,255,.12);width:20px;margin-bottom:14px}
.section-header{padding:20px 24px 16px;border-bottom:1px solid #f3f2f1}
.section-header h2{font-size:16px;font-weight:700;color:#323130;margin:0 0 3px}
.section-header p{font-size:12px;color:#a19f9d;margin:0}
.form-body{padding:20px 24px 24px;display:flex;flex-direction:column;gap:14px}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
.pw-wrap{position:relative}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#a19f9d;font-size:15px;padding:0;line-height:1}
.check-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid #f3f2f1;font-size:13px}
.check-row:last-child{border-bottom:none}
.badge-ok{color:#107c10;font-weight:600;font-size:12px}
.badge-fail{color:#d13438;font-weight:600;font-size:12px}
.feature-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.feature-item{display:flex;align-items:center;gap:8px;padding:8px 10px;background:#f8faff;border:1px solid #dbeafe;border-radius:8px;font-size:12px;color:#1e40af;font-weight:500}
</style>
</head>
<body>
<div style="width:100%;max-width:520px">

<!-- Brand header -->
<div style="text-align:center;margin-bottom:28px">
    <div style="display:inline-flex;width:60px;height:60px;background:rgba(255,255,255,.1);border-radius:18px;align-items:center;justify-content:center;margin-bottom:14px;border:1px solid rgba(255,255,255,.15)">
        <span style="color:#fff;font-weight:900;font-size:22px;letter-spacing:-1px">AE</span>
    </div>
    <h1 style="color:#fff;font-size:22px;font-weight:800;margin:0;letter-spacing:-.3px">Moroccan AE System</h1>
    <p style="color:rgba(255,255,255,.55);font-size:13px;margin:5px 0 0">
        <?= $isUpgrade ? '🔄 Mise à jour vers' : 'Installation' ?> <strong style="color:rgba(255,255,255,.8)">v2.1</strong>
    </p>
</div>

<!-- Steps -->
<div style="display:flex;align-items:center;justify-content:center;margin-bottom:24px">
    <?php $labels=['','Base de données','Admin','Profil','Terminé'];
    for($i=1;$i<=4;$i++): ?>
    <div style="display:flex;flex-direction:column;align-items:center;gap:5px">
        <div class="step-dot <?= $i<$step?'step-done':($i===$step?'step-active':'step-pending') ?>">
            <?= $i<$step ? '✓' : $i ?>
        </div>
        <span style="font-size:10px;color:rgba(255,255,255,<?= $i<=$step?'.6':'.3' ?>);white-space:nowrap"><?= $labels[$i] ?></span>
    </div>
    <?php if($i<4): ?><div class="divider" style="background:<?= $i<$step?'rgba(74,222,128,.6)':'rgba(255,255,255,.12)' ?>"></div><?php endif; endfor; ?>
</div>

<!-- Card -->
<div class="card">

<?php if($error): ?>
<div style="padding:16px 20px 0"><div class="err">⚠️ <?= h($error) ?></div></div>
<?php endif; ?>

<?php if($step===1): ?>
<!-- ══ STEP 1: DATABASE ══════════════════════════════════════ -->
<div class="section-header">
    <h2>🗄️ Connexion à la base de données</h2>
    <p>Hostinger : hPanel → Bases de données MySQL → créer BDD + utilisateur</p>
</div>
<div class="form-body">

<div class="info">
    <strong>📌 Guide Hostinger :</strong><br>
    1. hPanel → <strong>Bases de données MySQL</strong> → Créer une base<br>
    2. Notez le nom complet avec préfixe : <code style="background:#dbeafe;padding:1px 5px;border-radius:4px;font-family:monospace">u123456_finance</code><br>
    3. Créez un utilisateur → assignez-lui <strong>Tous les privilèges</strong><br>
    4. L'hôte est toujours <code style="background:#dbeafe;padding:1px 5px;border-radius:4px">localhost</code> sur Hostinger<br>
    <strong style="color:#dc2626">⚠️ La BDD doit exister avant — l'installateur ne la crée pas.</strong>
</div>

<form method="POST" style="display:contents" onsubmit="this.querySelector('.btn').textContent='Connexion…'">
    <div class="field">
        <label>Hôte MySQL *</label>
        <input class="inp" type="text" name="db_host" value="<?= h($_POST['db_host']??'localhost') ?>" required placeholder="localhost">
    </div>
    <div class="field">
        <label>Nom de la base de données * <span style="color:#a19f9d;font-weight:400">(avec préfixe Hostinger)</span></label>
        <input class="inp" type="text" name="db_name" value="<?= h($_POST['db_name']??'') ?>" required autofocus placeholder="ex : u123456_finance" style="font-family:monospace">
    </div>
    <div class="field">
        <label>Utilisateur MySQL *</label>
        <input class="inp" type="text" name="db_user" value="<?= h($_POST['db_user']??'') ?>" required placeholder="ex : u123456_user" style="font-family:monospace">
    </div>
    <div class="field">
        <label>Mot de passe MySQL * <span style="color:#a19f9d;font-weight:400">(≠ mot de passe Hostinger)</span></label>
        <div class="pw-wrap">
            <input class="inp" type="password" name="db_pass" id="dbpw" placeholder="Mot de passe de l'utilisateur MySQL" style="padding-right:40px">
            <button class="pw-toggle" type="button" onclick="var e=document.getElementById('dbpw');e.type=e.type==='password'?'text':'password';this.textContent=e.type==='password'?'👁':'🙈'">👁</button>
        </div>
    </div>
    <button class="btn" type="submit">Connecter et installer le schéma v2.1 →</button>
</form>
</div>

<?php elseif($step===2): ?>
<!-- ══ STEP 2: ADMIN ═════════════════════════════════════════ -->
<div style="padding:16px 20px 0">
<?php if(!empty($sw)): ?>
<div class="warn"><strong>⚠️ Avertissements non bloquants :</strong><br><?php foreach(array_slice($sw,0,3) as $w): ?><div style="font-family:monospace;font-size:11px;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($w) ?>"><?= h(substr($w,0,95)) ?></div><?php endforeach; ?><div style="margin-top:5px;font-size:11px">Tables déjà existantes — c'est normal. Continuez.</div></div>
<?php else: ?>
<div class="ok">✅ Schéma v2.1 importé — 14 tables, migration appliquée.</div>
<?php endif; ?>
</div>
<div class="section-header" style="margin-top:16px">
    <h2>👤 Compte Administrateur</h2>
    <p><?= $isUpgrade ? 'Confirmez ou mettez à jour vos identifiants.' : 'Créez votre accès personnel.' ?></p>
</div>
<div class="form-body">
<form method="POST" style="display:contents" onsubmit="return checkPw()">
    <div class="field">
        <label>Nom d'utilisateur *</label>
        <input class="inp" type="text" name="username" value="<?= h($_POST['username']??'admin') ?>" required minlength="3" autofocus>
    </div>
    <div class="field">
        <label>Mot de passe * <span style="color:#a19f9d;font-weight:400">(minimum 8 caractères)</span></label>
        <div class="pw-wrap">
            <input class="inp" type="password" name="password" id="pw1" required minlength="8" oninput="pwStrength(this.value)" style="padding-right:40px">
            <button class="pw-toggle" type="button" onclick="var e=document.getElementById('pw1');e.type=e.type==='password'?'text':'password';this.textContent=e.type==='password'?'👁':'🙈'">👁</button>
        </div>
        <div style="margin-top:5px">
            <div style="height:4px;background:#f3f2f1;border-radius:2px;overflow:hidden"><div id="pwbar" style="height:100%;border-radius:2px;transition:all .3s;width:0;background:#d13438"></div></div>
            <span id="pwlbl" style="font-size:11px;color:#a19f9d"></span>
        </div>
    </div>
    <div class="field">
        <label>Confirmer le mot de passe *</label>
        <div class="pw-wrap">
            <input class="inp" type="password" name="confirm" id="pw2" required style="padding-right:40px">
            <button class="pw-toggle" type="button" onclick="var e=document.getElementById('pw2');e.type=e.type==='password'?'text':'password';this.textContent=e.type==='password'?'👁':'🙈'">👁</button>
        </div>
    </div>
    <button class="btn" type="submit">Créer le compte →</button>
</form>
</div>

<?php elseif($step===3): ?>
<!-- ══ STEP 3: PROFILE ════════════════════════════════════════ -->
<div class="section-header">
    <h2>🏢 Profil Auto-Entrepreneur</h2>
    <p>Ces informations apparaissent sur vos factures et devis. Modifiables dans Paramètres.</p>
</div>
<div class="form-body">
<form method="POST" style="display:contents">
    <div class="field">
        <label>Nom / Raison sociale *</label>
        <input class="inp" type="text" name="owner_name" value="<?= h($_POST['owner_name']??$cfg['owner_name']??'') ?>" placeholder="Ex : Mohamed Benali" required autofocus>
    </div>
    <div class="field">
        <label>Email professionnel</label>
        <input class="inp" type="email" name="email" value="<?= h($_POST['email']??$cfg['email']??'') ?>" placeholder="votre@email.com">
    </div>
    <div class="grid-3">
        <div class="field">
            <label>ICE</label>
            <input class="inp" type="text" name="ice" value="<?= h($_POST['ice']??$cfg['ice']??'') ?>" placeholder="001234...">
        </div>
        <div class="field">
            <label>IF (Identifiant Fiscal)</label>
            <input class="inp" type="text" name="if_fiscal" value="<?= h($_POST['if_fiscal']??$cfg['if_fiscal']??'') ?>" placeholder="12345678">
        </div>
        <div class="field">
            <label>TP (Taxe Prof.)</label>
            <input class="inp" type="text" name="tp" value="<?= h($_POST['tp']??$cfg['tp']??'') ?>" placeholder="12345678">
        </div>
    </div>
    <div class="field">
        <label>Téléphone</label>
        <input class="inp" type="text" name="phone" value="<?= h($_POST['phone']??$cfg['cnss_phone']??'') ?>" placeholder="+212 6XX XXX XXX">
    </div>
    <div class="field">
        <label>Adresse</label>
        <textarea class="inp" name="address" rows="2" placeholder="Rue, Ville, Code Postal" style="resize:none"><?= h($_POST['address']??$cfg['address']??'') ?></textarea>
    </div>
    <div class="field">
        <label>Activités professionnelles <span style="color:#a19f9d;font-weight:400">(depuis rn.ae.gov.ma)</span></label>
        <?php for($i=1;$i<=3;$i++): ?>
        <input class="inp" type="text" name="activity_<?= $i ?>" value="<?= h($_POST["activity_$i"]??$cfg["activity_$i"]??'') ?>"
            placeholder="Activité <?= $i ?><?= $i===1?' (principale)':' (optionnelle)' ?>" style="margin-bottom:6px">
        <?php endfor; ?>
    </div>
    <div class="field">
        <label>Exercice fiscal</label>
        <select class="inp" name="fiscal_year">
            <?php for($y=(int)date('Y')+1;$y>=2020;$y--): ?>
            <option value="<?= $y ?>"<?= ($cfg['fiscal_year']??date('Y'))==$y?' selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <button class="btn" type="submit">Finaliser l'installation →</button>
</form>
</div>

<?php else: ?>
<!-- ══ STEP 4: SUCCESS ════════════════════════════════════════ -->
<div style="padding:36px 28px;text-align:center">
    <div style="width:72px;height:72px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 8px 24px rgba(34,197,94,.3)">
        <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h2 style="font-size:20px;font-weight:800;color:#323130;margin:0 0 6px"><?= $isUpgrade?'Mise à jour réussie !':'Installation réussie !' ?></h2>
    <p style="color:#a19f9d;font-size:13px;margin:0 0 24px">Moroccan AE System v2.1 est prêt à l'emploi.</p>

    <!-- Checklist -->
    <div style="background:#f8faff;border:1px solid #dbeafe;border-radius:12px;padding:16px;margin-bottom:16px;text-align:left">
        <?php foreach(['✅ Base de données connectée','✅ Schéma v2.1 (14 tables)','✅ Migration v2.1 appliquée','✅ uploads/logos/ créé','✅ Compte administrateur','✅ Profil AE configuré','✅ install.lock créé'] as $item): ?>
        <div style="font-size:13px;color:#1e40af;padding:2px 0"><?= $item ?></div>
        <?php endforeach; ?>
    </div>

    <!-- New features -->
    <div style="margin-bottom:16px">
        <div style="font-size:12px;font-weight:600;color:#323130;margin-bottom:8px;text-align:left">🆕 Nouvelles fonctionnalités v2.1</div>
        <div class="feature-grid">
            <?php foreach(['🖼️ Logo sur documents','📧 Email SMTP intégré','🏦 Multi-comptes bancaires','📥 Import CSV 5 banques','📄 PDF serveur (Dompdf)','🎯 Multi-activité IR'] as $f): ?>
            <div class="feature-item"><?= $f ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Security warning -->
    <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:12px;padding:14px;margin-bottom:20px;text-align:left">
        <div style="font-weight:700;color:#991b1b;font-size:13px;margin-bottom:6px">🔒 Action obligatoire — Sécurité</div>
        <div style="font-size:12px;color:#b91c1c;margin-bottom:8px">Supprimez <strong>install.php</strong> et <strong>install.php.bak</strong> immédiatement via le Gestionnaire de Fichiers Hostinger.</div>
        <code style="display:block;background:rgba(220,38,38,.06);border:1px solid #fca5a5;border-radius:7px;padding:8px 12px;font-size:12px;color:#991b1b;font-family:monospace">rm install.php install.php.bak</code>
    </div>

    <a href="/login.php" style="display:block;padding:14px;background:#0078d4;color:#fff;text-align:center;border-radius:10px;font-weight:700;font-size:15px;text-decoration:none;transition:background .15s" onmouseover="this.style.background='#106ebe'" onmouseout="this.style.background='#0078d4'">
        Accéder au Système →
    </a>
</div>
<?php endif; ?>

</div><!-- /card -->

<p style="text-align:center;color:rgba(255,255,255,.25);font-size:11px;margin-top:16px">
    Moroccan AE System v2.1 · Open Source · MIT License
</p>
</div>

<script>
function checkPw(){
    if(document.getElementById('pw1').value!==document.getElementById('pw2').value){
        alert('Les mots de passe ne correspondent pas.');return false;
    }return true;
}
function pwStrength(v){
    let s=0;
    if(v.length>=8)s++;if(v.length>=12)s++;
    if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
    const colors=['#ef4444','#ef4444','#f59e0b','#f59e0b','#22c55e','#22c55e'];
    const labels=['Très faible','Faible','Moyen','Bon','Fort','Très fort'];
    const bar=document.getElementById('pwbar');
    const lbl=document.getElementById('pwlbl');
    if(bar){bar.style.background=colors[s]||'#ef4444';bar.style.width=Math.min(100,s*20)+'%';}
    if(lbl){lbl.textContent=labels[s]||'';lbl.style.color=colors[s]||'#a19f9d';}
}
</script>
</body>
</html>
