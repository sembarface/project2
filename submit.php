<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Проверка существования таблицы applications с нужными полями
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS applications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                login VARCHAR(50) UNIQUE,
                pass_hash VARCHAR(255),
                name VARCHAR(150) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                email VARCHAR(100) NOT NULL,
                birthdate DATE NOT NULL,
                gender ENUM('male','female','other') NOT NULL,
                bio TEXT,
                contract_accepted BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");
    } catch (PDOException $e) {
        die("Ошибка создания таблицы: " . $e->getMessage());
    }

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

    $errors = [];
    $data = [];
    
    foreach ($validation_rules as $field => $rule) {
        if ($field === 'languages') {
            if (empty($_POST['languages'])) {
                $errors[$field] = $rule['message'];
                setcookie($field.'_error', $rule['message'], time() + 24 * 60 * 60);
            } else {
                $data[$field] = $_POST['languages'];
            }
            continue;
        }
        
        $value = $_POST[$field] ?? '';
        
        if ($field === 'contract_accepted') {
            $value = isset($_POST['contract_accepted']) ? '1' : '0';
        }
        
        $data[$field] = $value;
        
        if ($field !== 'languages' && !preg_match($rule['pattern'], $value)) {
            $errors[$field] = $rule['message'];
            setcookie($field.'_error', $rule['message'], time() + 24 * 60 * 60);
        } else {
            setcookie($field.'_value', $value, time() + 30 * 24 * 60 * 60);
        }
    }

    if (!empty($errors)) {
        setcookie('form_errors', json_encode($errors), time() + 24 * 60 * 60, '/');
        header('Location: index.php');
        exit();
    }

    try {
        // Подготовка данных для сохранения
        $db_data = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'birthdate' => $data['birthdate'],
            'gender' => $data['gender'],
            'bio' => $data['bio'],
            'contract_accepted' => $data['contract_accepted']
        ];

        if (!empty($_SESSION['login'])) {
            // Обновление существующей записи
            $db_data['id'] = $_SESSION['uid'];
            $stmt = $pdo->prepare("UPDATE applications SET 
                name = :name, phone = :phone, email = :email, 
                birthdate = :birthdate, gender = :gender, 
                bio = :bio, contract_accepted = :contract_accepted 
                WHERE id = :id");
            $stmt->execute($db_data);
            
            // Удаляем старые языки
            $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")
                ->execute([$_SESSION['uid']]);
        } else {
            // Создание новой записи
            $login = uniqid('user_');
            $pass = bin2hex(random_bytes(4));
            $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
            
            $db_data['login'] = $login;
            $db_data['pass_hash'] = $pass_hash;
            
            $stmt = $pdo->prepare("INSERT INTO applications 
                (name, phone, email, birthdate, gender, bio, contract_accepted, login, pass_hash) 
                VALUES (:name, :phone, :email, :birthdate, :gender, :bio, :contract_accepted, :login, :pass_hash)");
            $stmt->execute($db_data);
            $app_id = $pdo->lastInsertId();
            
            setcookie('login', $login, time() + 24 * 60 * 60);
            setcookie('pass', $pass, time() + 24 * 60 * 60);
        }
        
        // Добавляем выбранные языки
        $app_id = $_SESSION['uid'] ?? $app_id;
        $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) 
            SELECT ?, id FROM languages WHERE name = ?");
        
        foreach ($data['languages'] as $lang) {
            $lang_stmt->execute([$app_id, $lang]);
        }
        
        setcookie('save', '1', time() + 24 * 60 * 60);
        header('Location: index.php?success=1');
        exit();
    } catch (PDOException $e) {
        die("Ошибка сохранения данных: " . $e->getMessage());
    }
}
?>
