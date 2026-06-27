<?php
date_default_timezone_set('Europe/Moscow');
session_start();
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$dbPath = __DIR__ . '/../data/pipasila.db';

$configFile = '/etc/pipasila/admin-config.php';
if (file_exists($configFile)) {
    $config = require $configFile;
    $password = $config['password'] ?? 'admin123';
} else {
    $password = 'admin123';
}

function isAuth() {
    return !empty($_SESSION['admin_logged_in']);
}

function logout() {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
}

if (isset($_GET['logout'])) {
    logout();
    header('Location: /admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: /admin/');
        exit;
    }
    $error = 'Неверный пароль';
}

if (isset($_GET['export']) && isAuth()) {
    try {
        $db = new PDO("sqlite:$dbPath");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->query("SELECT id, email, name, phone, type, product, comment, created_at FROM submissions ORDER BY id DESC");
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pipasila-submissions.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['ID', 'Email', 'Имя', 'Телефон', 'Тип', 'Товар', 'Комментарий', 'Дата']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        exit;
    }
}

if (isset($_GET['backup']) && $_GET['backup'] === 'dl' && isAuth()) {
    $file = $_GET['file'] ?? '';
    if (strpos($file, '/var/backups/pipasila/') !== 0 || !file_exists($file) || is_dir($file)) {
        http_response_code(404);
        exit;
    }
    $name = basename($file);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    readfile($file);
    exit;
}

// handle comment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isAuth()) {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($_POST['action'] === 'update_comment' && isset($_POST['id'])) {
        $comment = trim($_POST['comment'] ?? '');
        $stmt = $db->prepare("UPDATE submissions SET comment = ? WHERE id = ?");
        $stmt->execute([$comment, $_POST['id']]);
        header('Location: /admin/?page=' . intval($_GET['page'] ?? 1));
        exit;
    }
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $stmt = $db->prepare("DELETE FROM submissions WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header('Location: /admin/?page=' . intval($_GET['page'] ?? 1));
        exit;
    }
}

function getStats($db) {
    $total = $db->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
    $todayMsk = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) FROM submissions WHERE date(created_at) = ?");
    $stmt->execute([$todayMsk]);
    $today = $stmt->fetchColumn();
    $subs = $db->query("SELECT COUNT(*) FROM submissions WHERE type = 'subscribe'")->fetchColumn();
    $notif = $db->query("SELECT COUNT(*) FROM submissions WHERE type = 'notify'")->fetchColumn();
    return [$total, $today, $subs, $notif];
}

function getSubmissions($db, $page = 1, $perPage = 50) {
    $offset = ($page - 1) * $perPage;
    $stmt = $db->prepare("SELECT * FROM submissions ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->execute([$perPage, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PIPA — Админка</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0a0a0a; color: #eee; min-height: 100vh; }
a { color: #E53935; text-decoration: none; }
a:hover { text-decoration: underline; }

.login-wrap { display:flex; align-items:center; justify-content:center; min-height:100vh; }
.login-box { background:#1a1a1a; padding:40px; border-radius:12px; width:360px; }
.login-box h1 { margin-bottom:24px; font-size:24px; color:#fff; text-align:center; }
.login-box input { width:100%; padding:12px 16px; background:#222; border:1px solid #333; border-radius:8px; color:#fff; font-size:16px; }
.login-box input:focus { outline:none; border-color:#E53935; }
.login-box button { width:100%; padding:12px; background:#E53935; border:none; border-radius:8px; color:#fff; font-size:16px; font-weight:600; cursor:pointer; margin-top:16px; }
.login-box .error { color:#E53935; font-size:14px; text-align:center; margin-top:12px; }

.admin-wrap { max-width:1200px; margin:0 auto; padding:24px; }
.admin-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:32px; padding-bottom:16px; border-bottom:1px solid #222; flex-wrap:wrap; gap:12px; }
.admin-header h1 { font-size:22px; font-weight:700; }
.admin-header nav { display:flex; gap:16px; font-size:14px; }
.badge { background:#222; padding:4px 10px; border-radius:6px; font-size:13px; }

.stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:24px; }
.stat-card { background:#1a1a1a; padding:16px; border-radius:8px; }
.stat-card .num { font-size:28px; font-weight:700; color:#fff; }
.stat-card .label { font-size:12px; color:#888; margin-top:4px; }

.backup-bar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:24px; padding:12px 16px; background:#1a1a1a; border-radius:8px; font-size:13px; }
.backup-bar .btn { padding:6px 14px; background:#E53935; border:0; border-radius:6px; color:#fff; font-weight:600; cursor:pointer; font-size:13px; text-decoration:none; }
.backup-bar .btn:hover { background:#c62828; }
.backup-dot { width:6px; height:6px; border-radius:50%; display:inline-block; margin-right:4px; }
.backup-dot-green { background:#4CAF50; }
.backup-dot-red { background:#E53935; }

table { width:100%; border-collapse:collapse; font-size:13px; }
th { text-align:left; padding:10px 12px; background:#1a1a1a; color:#888; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.05em; }
td { padding:10px 12px; border-bottom:1px solid #1a1a1a; }
tr:hover td { background:#111; }
.pagination { display:flex; gap:8px; justify-content:center; margin-top:24px; font-size:14px; }
.pagination a { padding:6px 12px; background:#1a1a1a; border-radius:6px; }
.pagination .active { background:#E53935; color:#fff; }

/* modal */
.modal { display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.7); align-items:center; justify-content:center; }
.modal-content { background:#1a1a1a; padding:32px; border-radius:12px; width:400px; max-width:90vw; position:relative; }
.modal-close { position:absolute; right:20px; top:16px; font-size:24px; cursor:pointer; color:#888; }
.modal-close:hover { color:#fff; }

@media (max-width:600px) {
    .admin-wrap { padding:16px; }
    .stats { grid-template-columns:repeat(2,1fr); }
    th, td { padding:8px; font-size:12px; }
    td:nth-child(5), th:nth-child(5) { display:none; }
    td:nth-child(7), th:nth-child(7) { display:none; }
    td:nth-child(8), th:nth-child(8) { display:none; }
}
</style>
</head>
<body>
<?php if (!isAuth()): ?>
<div class="login-wrap">
<div class="login-box">
<h1>PIPA Админка</h1>
<form method="post">
<input type="password" name="password" placeholder="Пароль" autofocus>
<button type="submit">Войти</button>
</form>
<?php if (isset($error)): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
</div>
</div>
<?php exit; endif;

$pwMsg = '';
$page = max(1, intval($_GET['page'] ?? 1));
try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    [$total, $today, $subs, $notif] = getStats($db);
    $rows = getSubmissions($db, $page);
} catch (Exception $e) {
    $total = $today = $subs = $notif = 0;
    $rows = [];
}
?>
<div class="admin-wrap">
<div class="admin-header">
<h1>PIPA Админка</h1>
<nav>
<a href="?export=1">CSV</a>
<a href="#" onclick="showPwModal();return false">Сменить пароль</a>
<a href="?logout=1">Выйти</a>
</nav>
</div>
<?php if ($pwMsg): ?><div style="text-align:center;margin-bottom:16px;font-size:14px;"><?=$pwMsg?></div><?php endif; ?>

<div class="stats">
<div class="stat-card"><div class="num"><?=$total?></div><div class="label">Всего</div></div>
<div class="stat-card"><div class="num"><?=$today?></div><div class="label">Сегодня</div></div>
<div class="stat-card"><div class="num"><?=$subs?></div><div class="label">Подписки</div></div>
<div class="stat-card"><div class="num"><?=$notif?></div><div class="label">Ожидания</div></div>
</div>

<?php
$backupDir = '/var/backups/pipasila';
$backupFiles = is_dir($backupDir) ? glob("$backupDir/pipasila-*") : [];
rsort($backupFiles);
$ydConf = '/etc/pipasila/yandex-backup.conf';
$ydOk = file_exists($ydConf) && !empty(parse_ini_file($ydConf)['TOKEN'] ?? '');
?>
<div class="backup-bar">
<span><span class="backup-dot <?=$ydOk?'backup-dot-green':'backup-dot-red'?>"></span>Яндекс.Диск: <?=$ydOk?'работает':'не подключён'?></span>
<span>Бэкапов: <?=count($backupFiles)?></span>
<?php if ($backupFiles): $last = basename($backupFiles[0]); ?>
<a href="?backup=dl&file=<?=rawurlencode($backupFiles[0])?>" class="btn">Скачать последний</a>
<?php endif; ?>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_pass'])) {
    $new_pass = trim($_POST['new_pass']);
    $confirm = trim($_POST['confirm_pass'] ?? '');
    if (!empty($_POST['gen'])) {
        $new_pass = bin2hex(random_bytes(4));
        $pwMsg = '<span style="color:#4CAF50;">Сгенерирован: <b>' . htmlspecialchars($new_pass) . '</b></span>';
    } elseif ($new_pass !== $confirm) {
        $pwMsg = '<span style="color:#E53935;">Пароли не совпадают</span>';
    } elseif (strlen($new_pass) < 6) {
        $pwMsg = '<span style="color:#E53935;">Минимум 6 символов</span>';
    } else {
        file_put_contents($configFile, '<?php return ' . var_export(['password' => $new_pass], true) . ';' . "\n");
        $password = $new_pass;
        $pwMsg = '<span style="color:#4CAF50;">Пароль сохранён: <b>' . htmlspecialchars($new_pass) . '</b></span>';
    }
}
?>

<div id="pwModal" class="modal">
<div class="modal-content">
<span class="modal-close" onclick="hidePwModal()">&times;</span>
<h2 style="margin-bottom:20px;font-size:18px;">Смена пароля</h2>
<form method="post" onsubmit="return validatePw()">
<div style="margin-bottom:12px;">
<label style="display:block;font-size:13px;color:#888;margin-bottom:4px;">Новый пароль</label>
<input type="password" name="new_pass" id="pwNew" placeholder="минимум 6 символов" minlength="6" required
       style="width:100%;padding:10px 12px;background:#222;border:1px solid #333;border-radius:6px;color:#fff;font-size:14px;outline:none;"
       onfocus="this.style.borderColor='#E53935'" onblur="this.style.borderColor='#333'">
</div>
<div style="margin-bottom:20px;">
<label style="display:block;font-size:13px;color:#888;margin-bottom:4px;">Подтвердить пароль</label>
<input type="password" name="confirm_pass" id="pwConfirm" placeholder="повторите пароль" required
       style="width:100%;padding:10px 12px;background:#222;border:1px solid #333;border-radius:6px;color:#fff;font-size:14px;outline:none;"
       onfocus="this.style.borderColor='#E53935'" onblur="this.style.borderColor='#333'">
</div>
<div id="pwError" style="color:#E53935;font-size:13px;margin-bottom:12px;display:none;"></div>
<div style="display:flex;gap:10px;">
<button type="submit" class="btn" style="padding:10px 20px;font-size:14px;flex:1;">Сохранить</button>
<button type="button" onclick="genPw()" class="btn" style="padding:10px 20px;font-size:14px;flex:1;background:#555;">Сгенерировать</button>
</div>
</form>
</div>
</div>

<script>
function showPwModal() {
    document.getElementById('pwModal').style.display = 'flex';
    document.getElementById('pwNew').value = '';
    document.getElementById('pwConfirm').value = '';
    document.getElementById('pwError').style.display = 'none';
}
function hidePwModal() {
    document.getElementById('pwModal').style.display = 'none';
}
function genPw() {
    var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    var pw = '';
    for (var i = 0; i < 8; i++) {
        pw += chars[Math.floor(Math.random() * chars.length)];
    }
    document.getElementById('pwNew').value = pw;
    document.getElementById('pwConfirm').value = pw;
    document.getElementById('pwError').style.display = 'none';
}
function validatePw() {
    var a = document.getElementById('pwNew').value;
    var b = document.getElementById('pwConfirm').value;
    var err = document.getElementById('pwError');
    if (a !== b) {
        err.textContent = 'Пароли не совпадают';
        err.style.display = 'block';
        return false;
    }
    if (a.length < 6) {
        err.textContent = 'Минимум 6 символов';
        err.style.display = 'block';
        return false;
    }
    return true;
}
document.getElementById('pwModal').addEventListener('click', function(e) {
    if (e.target === this) hidePwModal();
});
</script>

<?php if (empty($rows)): ?>
<p style="color:#666;text-align:center;padding:40px 0;">Пока нет записей</p>
<?php else: ?>
<table>
<thead>
<tr>
<th>ID</th><th>Email</th><th>Имя</th><th>Телефон</th><th>Тип</th><th>Товар</th><th>Комментарий</th><th>Действия</th><th>Дата</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><?=htmlspecialchars($r['id'])?></td>
<td><?=htmlspecialchars($r['email'])?></td>
<td><?=htmlspecialchars($r['name'])?></td>
<td><?=htmlspecialchars($r['phone'])?></td>
<td><?=htmlspecialchars($r['type'])?></td>
<td><?=htmlspecialchars($r['product'])?></td>
<td>
<form method="post" style="display:flex;gap:4px;align-items:center;">
<input type="hidden" name="action" value="update_comment">
<input type="hidden" name="id" value="<?=htmlspecialchars($r['id'])?>">
<input type="text" name="comment" value="<?=htmlspecialchars($r['comment'] ?? '')?>" placeholder="Комментарий" style="width:100%;min-width:120px;padding:4px 6px;background:#222;border:1px solid #333;border-radius:4px;color:#fff;font-size:12px;">
<button type="submit" style="padding:4px 8px;background:#333;border:1px solid #555;border-radius:4px;color:#fff;cursor:pointer;font-size:11px;">Сохранить</button>
</form>
</td>
<td>
<form method="post" onsubmit="return confirm('Удалить заказ #<?=htmlspecialchars($r['id'])?>?')">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=htmlspecialchars($r['id'])?>">
<button type="submit" style="padding:4px 8px;background:#c62828;border:none;border-radius:4px;color:#fff;cursor:pointer;font-size:11px;">Удалить</button>
</form>
</td>
<td><?=htmlspecialchars($r['created_at'])?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="pagination">
<?php $pages = max(1, ceil($total / 50)); for ($i = 1; $i <= $pages; $i++): ?>
<a href="?page=<?=$i?>" class="<?=$i === $page ? 'active' : ''?>"><?=$i?></a>
<?php endfor; ?>
</div>
<?php endif; ?>
</div>
</body>
</html>
