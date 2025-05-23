<?php
header('Content-Type: application/json');
require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

try {
    switch ($method) {
        case 'POST': // Создание новой записи
            require 'submit.php'; // Переиспользуем логику валидации и сохранения
            break;
            
        case 'PUT': // Обновление существующей записи
            if (empty($_SESSION['login'])) {
                sendResponse(['error' => 'Unauthorized'], 401);
            }
            require 'submit.php';
            break;
            
        case 'GET': // Получение данных
            if (empty($_SESSION['login'])) {
                sendResponse(['error' => 'Unauthorized'], 401);
            }
            
            $stmt = $pdo->prepare("SELECT a.*, GROUP_CONCAT(l.name) as languages 
                FROM applications a
                LEFT JOIN application_languages al ON a.id = al.application_id
                LEFT JOIN languages l ON al.language_id = l.id
                WHERE a.login = ? 
                GROUP BY a.id");
            $stmt->execute([$_SESSION['login']]);
            $user_data = $stmt->fetch();
            
            if ($user_data) {
                $user_data['languages'] = $user_data['languages'] ? explode(',', $user_data['languages']) : [];
                sendResponse($user_data);
            } else {
                sendResponse(['error' => 'User not found'], 404);
            }
            break;
            
        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
