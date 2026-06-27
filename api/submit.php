<?php
date_default_timezone_set('Europe/Moscow');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$dbPath = __DIR__ . '/../data/pipasila.db';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL,
        name TEXT DEFAULT '',
        phone TEXT DEFAULT '',
        type TEXT NOT NULL,
        product TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    $email  = trim($input['email'] ?? '');
    $type   = trim($input['type'] ?? 'subscribe');
    $name   = trim($input['name'] ?? '');
    $phone  = trim($input['phone'] ?? '');
    $product = trim($input['product'] ?? '');

    if ($type === 'notify') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Некорректный email']);
            exit;
        }
    } elseif (empty($phone)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Введите номер телефона']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO submissions (email, name, phone, type, product) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$email, $name, $phone, $type, $product]);

    $tgCfg = '/etc/pipasila/telegram-config.php';
    if (file_exists($tgCfg)) {
        $tg = require $tgCfg;
        if ($type === 'subscribe') {
            $msg = "📦 <b>Новая заявка</b>\nИмя: $name\nТелефон: $phone" . ($product ? "\nТовар: $product" : '');
        } else {
            $msg = "🔔 <b>Уведомление</b>\nEmail: $email\nТовар: $product";
        }
        $ch = curl_init('https://api.telegram.org/bot' . $tg['bot_token'] . '/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $tg['chat_id'], 'text' => $msg, 'parse_mode' => 'HTML']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Ошибка сервера']);
}
