<?php
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

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Ошибка сервера']);
}
