<?php
date_default_timezone_set('Europe/Moscow');
session_start();

$dbPath = __DIR__ . '/../data/pipasila.db';
$password = 'pipasila2026';

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
        $stmt = $db->query("SELECT id, email, name, phone, type, product, created_at FROM submissions ORDER BY id DESC");
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pipasila-submissions.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['ID', 'Email', 'Имя', 'Телефон', 'Тип', 'Товар', 'Дата']);
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

function getStats($db) {
    $total = $db->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
    $today = $db->query("SELECT COUNT(*) FROM submissions WHERE date(created_at) = date('now')")->fetchColumn();
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

@media (max-width:600px) {
    .admin-wrap { padding:16px; }
    .stats { grid-template-columns:repeat(2,1fr); }
    th, td { padding:8px; font-size:12px; }
    td:nth-child(5), th:nth-child(5) { display:none; }
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
<a href="?logout=1">Выйти</a>
</nav>
</div>

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

<?php if (empty($rows)): ?>
<p style="color:#666;text-align:center;padding:40px 0;">Пока нет записей</p>
<?php else: ?>
<table>
<thead>
<tr>
<th>ID</th><th>Email</th><th>Имя</th><th>Телефон</th><th>Тип</th><th>Товар</th><th>Дата</th>
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
