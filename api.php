<?php
header('Content-Type: application/json');
session_start();
require 'db.php';

function sendResponse($success, $data = [], $errors = []) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'errors' => $errors
    ]);
    exit;
}

// Проверка метода запроса
$method = $_SERVER['REQUEST_METHOD'];

// Обработка разных форматов входных данных
if ($method == 'POST' || $method == 'PUT') {
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
    } elseif (strpos($contentType, 'application/xml') !== false) {
        $xml = simplexml_load_string(file_get_contents('php://input'));
        $input = json_decode(json_encode($xml), true);
    } else {
        $input = $_POST;
    }
} else {
    $input = [];
}

// Валидационные правила
$validation_rules = [
    'name' => [
        'pattern' => '/^[a-zA-Zа-яА-ЯёЁ\s]{1,150}$/u',
        'message' => 'ФИО должно содержать только буквы и пробелы (макс. 150 символов)'
    ],
    'phone' => [
        'pattern' => '/^\+?\d[\d\s\-\(\)]{6,}\d$/',
        'message' => 'Неверный формат телефона'
    ],
    'email' => [
        'pattern' => '/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i',
        'message' => 'Введите корректный email'
    ],
    'birthdate' => [
        'pattern' => '/^\d{4}-\d{2}-\d{2}$/',
        'message' => 'Дата должна быть в формате ГГГГ-ММ-ДД'
    ],
    'gender' => [
        'pattern' => '/^(male|female|other)$/',
        'message' => 'Выберите пол из предложенных вариантов'
    ],
    'languages' => [
        'message' => 'Выберите хотя бы один язык программирования'
    ],
    'bio' => [
        'pattern' => '/^[\s\S]{10,2000}$/',
        'message' => 'Биография должна содержать от 10 до 2000 символов'
    ],
    'contract_accepted' => [
        'pattern' => '/^1$/',
        'message' => 'Необходимо принять условия контракта'
    ]
];

// Обработка запросов
try {
    switch ($method) {
        case 'POST':
            // Создание новой записи
            $errors = [];
            $data = [];
            
            foreach ($validation_rules as $field => $rule) {
                $value = $input[$field] ?? '';
                
                if ($field === 'contract_accepted') {
                    $value = isset($input['contract_accepted']) ? '1' : '0';
                }
                
                if ($field === 'languages') {
                    if (empty($input['languages'])) {
                        $errors[$field] = $rule['message'];
                        continue;
                    }
                    $data[$field] = $input['languages'];
                    continue;
                }
                
                $data[$field] = $value;
                
                if (!preg_match($rule['pattern'], $value)) {
                    $errors[$field] = $rule['message'];
                }
            }
            
            if (!empty($errors)) {
                sendResponse(false, [], $errors);
            }
            
            // Создание пользователя
            $login = uniqid('user_');
            $pass = bin2hex(random_bytes(4));
            $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO applications 
                (name, phone, email, birthdate, gender, bio, contract_accepted, login, pass_hash) 
                VALUES (:name, :phone, :email, :birthdate, :gender, :bio, :contract_accepted, :login, :pass_hash)");
            
            $data['login'] = $login;
            $data['pass_hash'] = $pass_hash;
            $stmt->execute($data);
            $app_id = $pdo->lastInsertId();
            
            // Добавляем языки
            $lang_stmt = $pdo->prepare("INSERT INTO application_languages 
                (application_id, language_id) SELECT ?, id FROM languages WHERE name = ?");
            
            foreach ($data['languages'] as $lang) {
                $lang_stmt->execute([$app_id, $lang]);
            }
            
            sendResponse(true, [
                'login' => $login,
                'password' => $pass,
                'profile_url' => 'index.php?login=' . urlencode($login)
            ]);
            break;
            
        case 'PUT':
            // Обновление существующей записи
            if (empty($_SESSION['login'])) {
                sendResponse(false, [], ['auth' => 'Требуется авторизация']);
            }
            
            $errors = [];
            $data = [];
            
            foreach ($validation_rules as $field => $rule) {
                $value = $input[$field] ?? '';
                
                if ($field === 'contract_accepted') {
                    $value = isset($input['contract_accepted']) ? '1' : '0';
                }
                
                if ($field === 'languages') {
                    if (empty($input['languages'])) {
                        $errors[$field] = $rule['message'];
                        continue;
                    }
                    $data[$field] = $input['languages'];
                    continue;
                }
                
                $data[$field] = $value;
                
                if (!preg_match($rule['pattern'], $value)) {
                    $errors[$field] = $rule['message'];
                }
            }
            
            if (!empty($errors)) {
                sendResponse(false, [], $errors);
            }
            
            // Обновление данных
            $stmt = $pdo->prepare("UPDATE applications SET 
                name = :name, phone = :phone, email = :email, 
                birthdate = :birthdate, gender = :gender, 
                bio = :bio, contract_accepted = :contract_accepted 
                WHERE id = :id");
            
            $data['id'] = $_SESSION['uid'];
            $stmt->execute($data);
            
            // Обновляем языки
            $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")
                ->execute([$_SESSION['uid']]);
            
            $lang_stmt = $pdo->prepare("INSERT INTO application_languages 
                (application_id, language_id) SELECT ?, id FROM languages WHERE name = ?");
            
            foreach ($data['languages'] as $lang) {
                $lang_stmt->execute([$_SESSION['uid'], $lang]);
            }
            
            sendResponse(true, ['message' => 'Данные успешно обновлены']);
            break;
            
        default:
            sendResponse(false, [], ['method' => 'Метод не поддерживается']);
    }
} catch (PDOException $e) {
    sendResponse(false, [], ['database' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
