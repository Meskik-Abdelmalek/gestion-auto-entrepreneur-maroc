<?php
/** Moroccan AE System — Web Installer. DELETE AFTER INSTALL! */
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

$host = addslashes(trim($_POST['db_host'] ?? 'localhost'));
$user = addslashes(trim($_POST['db_user'] ?? ''));
$pass = addslashes(trim($_POST['db_pass'] ?? ''));
$name = addslashes(trim($_POST['db_name'] ?? 'moroccan_ae'));

$step = (int)($_GET['step'] ?? 1);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    $host=trim($_POST['db_host']??'localhost');$user=trim($_POST['db_user']??'');$pass=trim($_POST['db_pass']??'');$name=trim($_POST['db_name']??'moroccan_ae');
    try {
        $pdo=new PDO("mysql:host=$host;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");
        foreach(array_filter(array_map('trim',explode(';',file_get_contents(__DIR__.'/sql/schema.sql')))) as $q) if($q)$pdo->exec($q);
        $cfg="<?php\ndefine('DB_HOST','$host');\ndefine('DB_USER','$user');\ndefine('DB_PASS','$pass');\ndefine('DB_NAME','$name');\ndefine('DB_CHARSET','utf8mb4');\ndefine('APP_NAME','Moroccan AE System');\ndefine('APP_VERSION','2.0.0');\ndefine('APP_LOCALE','fr_MA');\ndefine('APP_TIMEZONE','Africa/Casablanca');\ndate_default_timezone_set(APP_TIMEZONE);\nsetlocale(LC_TIME,APP_LOCALE.'.UTF-8',APP_LOCALE,'fr_FR.UTF-8','fr_FR');\nfunction getDB():PDO{static \$p=null;if(\$p===null){\$d=sprintf('mysql:host=%s;dbname=%s;charset=%s',DB_HOST,DB_NAME,DB_CHARSET);\$p=new PDO(\$d,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}return \$p;}\n";
        file_put_contents(__DIR__.'/config.php',$cfg);
        header('Location: /install.php?step=2'); exit;
    } catch(Throwable $e) { $error='Erreur: '.$e->getMessage(); }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && $step===2) {
    require_once __DIR__.'/config.php';
    $u=trim($_POST['username']??'admin'); $p=$_POST['password']??''; $c=$_POST['confirm']??'';
    if(strlen($u)<3) $error="Nom trop court (min. 3 car).";
    elseif(strlen($p)<8) $error="Mot de passe trop court (min. 8 car).";
    elseif($p!==$c) $error="Les mots de passe ne correspondent pas.";
    else {
        $db=getDB(); $db->prepare("DELETE FROM ae_users WHERE 1")->execute();
        $db->prepare("INSERT INTO ae_users (username,password_hash) VALUES (?,?)")->execute([$u,password_hash($p,PASSWORD_DEFAULT)]);
        header('Location: /install.php?step=3'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && $step===3) {
    require_once __DIR__.'/config.php';
    $db=getDB();
    $db->prepare("UPDATE ae_config SET owner_name=?,email=?,ice=?,if_fiscal=?,tp=?,cnss_phone=?,address=?,fiscal_year=?,activity_1=?,activity_2=?,activity_3=? WHERE id=1")
       ->execute([trim($_POST['owner_name']??''),trim($_POST['email']??''),trim($_POST['ice']??''),trim($_POST['if_fiscal']??''),trim($_POST['tp']??''),trim($_POST['phone']??''),trim($_POST['address']??''),(int)($_POST['fiscal_year']??date('Y')),trim($_POST['activity_1']??''),trim($_POST['activity_2']??''),trim($_POST['activity_3']??'')]);
    header('Location: /install.php?step=4'); exit;
}

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Installation — Moroccan AE System</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{fluent:{blue:'#0078d4','blue-dk':'#106ebe',neutral:'#323130','n2':'#605e5c','n3':'#a19f9d','n4':'#c8c6c4','n5':'#edebe9','n6':'#f3f2f1','n7':'#faf9f8'}},fontFamily:{sans:['"Segoe UI"','system-ui','sans-serif']}}}}</script>
<style>.inp:focus{outline:none;border-color:#0078d4;box-shadow:0 0 0 1.5px #0078d4}@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}.anim{animation:fadeUp .25s ease-out}.bg-grad{background:#0078d4;background-image:radial-gradient(circle at 20% 80%,rgba(255,255,255,.1) 0%,transparent 50%)}</style>
</head>
<body class="min-h-screen bg-fluent-n7 font-sans flex items-center justify-center p-4">
<div class="fixed inset-0 bg-grad -z-10"></div>
<div class="w-full max-w-md anim">
    <div class="text-center mb-5">
        <div class="inline-flex w-14 h-14 rounded-2xl bg-white/20 items-center justify-center mb-3 shadow-lg"><span class="text-white font-bold text-xl">AE</span></div>
        <h1 class="text-xl font-bold text-white">Moroccan AE System</h1>
        <p class="text-white/70 text-sm">Installation · Étape <?= $step ?>/4</p>
    </div>
    <!-- Progress -->
    <div class="flex items-center justify-center gap-1.5 mb-5">
        <?php for($i=1;$i<=4;$i++): $cls=$i<$step?'bg-green-400 text-white':($i===$step?'bg-white text-fluent-blue font-bold':'bg-white/20 text-white/50'); ?>
        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs <?= $cls ?>"><?= $i<$step?'✓':$i ?></div>
        <?php if($i<4): ?><div class="w-6 h-px <?= $i<$step?'bg-green-400':'bg-white/20' ?>"></div><?php endif; endfor; ?>
    </div>
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
        <?php if($error): ?>
        <div class="px-6 pt-5"><div class="px-4 py-3 bg-red-50 text-red-700 rounded-xl text-sm border border-red-200">⚠️ <?= h($error) ?></div></div>
        <?php endif; ?>

        <?php if($step===1): ?>
        <div class="px-6 py-5 border-b border-fluent-n5"><h2 class="font-bold text-fluent-neutral">Connexion MySQL</h2><p class="text-xs text-fluent-n3 mt-0.5">La base de données sera créée automatiquement.</p></div>
        <form method="POST" class="px-6 py-5 space-y-3">
            <?php foreach([['db_host','Hôte','localhost','text'],['db_name','Base de données','moroccan_ae','text'],['db_user','Utilisateur','root','text'],['db_pass','Mot de passe','','password']] as[$n,$l,$ph,$t]): ?>
            <div><label class="block text-xs font-medium text-fluent-n2 mb-1"><?=$l?></label><input type="<?=$t?>" name="<?=$n?>" value="<?=$t!=='password'?h($_POST[$n]??$ph):''?>" placeholder="<?=$ph?>" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 rounded-xl inp bg-white"></div>
            <?php endforeach; ?>
            <button type="submit" class="w-full py-3 bg-fluent-blue text-white rounded-xl font-semibold text-sm hover:bg-fluent-blue-dk mt-1">Installer →</button>
        </form>

        <?php elseif($step===2): ?>
        <div class="px-6 py-5 border-b border-fluent-n5"><h2 class="font-bold text-fluent-neutral">Compte Administrateur</h2><p class="text-xs text-fluent-n3 mt-0.5">Créez votre compte de connexion personnel.</p></div>
        <form method="POST" class="px-6 py-5 space-y-3">
            <div><label class="block text-xs font-medium text-fluent-n2 mb-1">Nom d'utilisateur *</label><input type="text" name="username" value="<?=h($_POST['username']??'admin')?>" required minlength="3" autofocus class="w-full px-3 py-2.5 text-sm border border-fluent-n4 rounded-xl inp bg-white"></div>
            <div><label class="block text-xs font-medium text-fluent-n2 mb-1">Mot de passe * <span class="text-fluent-n3">(min. 8 car.)</span></label>
                <input type="password" name="password" id="pw-inp" required minlength="8" autocomplete="new-password" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 rounded-xl inp bg-white" oninput="pwStr(this.value)">
                <div class="mt-1 h-1 bg-fluent-n5 rounded-full overflow-hidden"><div id="pw-bar" class="h-full rounded-full transition-all" style="width:0"></div></div>
            </div>
            <div><label class="block text-xs font-medium text-fluent-n2 mb-1">Confirmer *</label><input type="password" name="confirm" id="pw-cf" required autocomplete="new-password" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 rounded-xl inp bg-white"></div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-700">🔐 Choisissez un mot de passe fort — ce système sera hébergé en ligne.</div>
            <button type="submit" onclick="return pwCheck()" class="w-full py-3 bg-fluent-blue text-white rounded-xl font-semibold text-sm hover:bg-fluent-blue-dk">Créer le Compte →</button>
        </form>

        <?php elseif($step===3): ?>
        <div class="px-6 py-5 border-b border-fluent-n5"><h2 class="font-bold text-fluent-neutral">Profil Auto-Entrepreneur</h2><p class="text-xs text-fluent-n3 mt-0.5">Informations qui apparaîtront sur vos documents.</p></div>
        <form method="POST" class="px-6 py-5 space-y-2.5">
            <?php foreach([['owner_name','Nom complet *','Prénom NOM','text'],['email','Email','email@example.com','email'],['ice','ICE','003938XXXXXXXXX','text'],['if_fiscal','Identifiant Fiscal','12345678','text'],['tp','TP','12345678','text'],['phone','Téléphone','0600000000','text']] as[$n,$l,$ph,$t]): ?>
            <div><label class="block text-xs font-medium text-fluent-n2 mb-0.5"><?=$l?></label><input type="<?=$t?>" name="<?=$n?>" placeholder="<?=$ph?>" class="w-full px-3 py-2 text-sm border border-fluent-n4 rounded-xl inp bg-white placeholder-fluent-n3"></div>
            <?php endforeach; ?>
            <div class="pt-2 border-t border-fluent-n5">
                <p class="text-xs font-semibold text-fluent-n2 mb-1">🎯 Activités (depuis <a href="https://rn.ae.gov.ma/" target="_blank" class="text-fluent-blue underline">rn.ae.gov.ma</a>)</p>
                <?php for($i=1;$i<=3;$i++): ?>
                <div class="mb-1.5"><input type="text" name="activity_<?=$i?>" placeholder="Activité <?=$i?><?=$i===1?' (principale)':' (optionnel)'?>" class="w-full px-3 py-2 text-sm border border-fluent-n4 rounded-xl inp bg-white placeholder-fluent-n3"></div>
                <?php endfor; ?>
            </div>
            <div><label class="block text-xs font-medium text-fluent-n2 mb-0.5">Exercice fiscal</label>
                <select name="fiscal_year" class="w-full px-3 py-2 text-sm border border-fluent-n4 rounded-xl inp bg-white">
                    <?php for($y=(int)date('Y')+1;$y>=2020;$y--): ?><option value="<?=$y?>" <?=$y===(int)date('Y')?'selected':''?>><?=$y?></option><?php endfor; ?>
                </select></div>
            <button type="submit" class="w-full py-3 bg-fluent-blue text-white rounded-xl font-semibold text-sm hover:bg-fluent-blue-dk mt-1">Finaliser →</button>
        </form>

        <?php else: ?>
        <div class="px-6 py-10 text-center">
            <div class="text-5xl mb-3">🎉</div>
            <h2 class="text-lg font-bold text-fluent-neutral mb-2">Installation Réussie !</h2>
            <div class="text-left bg-fluent-n7 rounded-xl p-3 mb-4 space-y-1 text-xs text-fluent-n2">
                <div class="text-green-600">✅ Base de données installée</div>
                <div class="text-green-600">✅ Compte administrateur créé</div>
                <div class="text-green-600">✅ Profil AE configuré</div>
                <div class="text-red-600 font-bold">⚠️ Supprimez install.php maintenant !</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-xs text-red-700 text-left">
                <strong>Sécurité :</strong> Supprimez <code class="font-mono bg-white px-1 rounded">install.php</code> via FTP/SSH/cPanel avant d'utiliser le système.
            </div>
            <a href="/login.php" class="block w-full py-3 bg-fluent-blue text-white rounded-xl font-semibold text-sm hover:bg-fluent-blue-dk">Accéder au Système →</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
function pwStr(v){let s=0;if(v.length>=8)s++;if(v.length>=12)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;const b=document.getElementById('pw-bar');if(!b)return;b.className='h-full rounded-full transition-all '+(['bg-red-500','bg-red-500','bg-amber-400','bg-amber-400','bg-green-500','bg-green-500'][s]||'bg-red-500');b.style.width=Math.min(100,s*20)+'%';}
function pwCheck(){const p=document.getElementById('pw-inp')?.value;const c=document.getElementById('pw-cf')?.value;if(p!==c){alert('Les mots de passe ne correspondent pas.');return false;}return true;}
</script>
</body></html>
