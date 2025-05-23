<?php
require 'db.php';

// Создаем таблицу для администраторов, если ее нет
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(50) UNIQUE NOT NULL,
            pass_hash VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB
    ");
    
    // Добавляем администратора по умолчанию, если таблица пуста
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() == 0) {
        $pass_hash = password_hash('123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admins (login, pass_hash) VALUES (?, ?)")
            ->execute(['admin', $pass_hash]);
    }
} catch (PDOException $e) {
    die("Ошибка инициализации БД: " . $e->getMessage());
}

// HTTP-аутентификация
if (empty($_SERVER['PHP_AUTH_USER'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    die('Требуется авторизация');
}

// Проверка логина и пароля
try {
    $stmt = $pdo->prepare("SELECT pass_hash FROM admins WHERE login = ?");
    $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
    $admin = $stmt->fetch();
    
    if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['pass_hash'])) {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        die('Неверные логин или пароль');
    }
} catch (PDOException $e) {
    die("Ошибка аутентификации: " . $e->getMessage());
}

// Обработка действий администратора
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

try {
    // Удаление записи
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$id]);
        header("Location: index.php");
        exit();
    }
    
    // Обновление записи
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE applications SET 
            name = ?, phone = ?, email = ?, birthdate = ?, 
            gender = ?, bio = ?, contract_accepted = ? 
            WHERE id = ?");
        
        $stmt->execute([
            $_POST['name'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['birthdate'],
            $_POST['gender'],
            $_POST['bio'],
            isset($_POST['contract_accepted']) ? 1 : 0,
            $_POST['id']
        ]);
        
        // Обновляем языки
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")
            ->execute([$_POST['id']]);
        
        $lang_stmt = $pdo->prepare("INSERT INTO application_languages 
            (application_id, language_id) SELECT ?, id FROM languages WHERE name = ?");
        
        foreach ($_POST['languages'] as $lang) {
            $lang_stmt->execute([$_POST['id'], $lang]);
        }
        
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    die("Ошибка обработки действия: " . $e->getMessage());
}

// Получение данных для отображения
try {
    // Получаем все заявки
    $applications = $pdo->query("
        SELECT a.*, GROUP_CONCAT(l.name) as languages 
        FROM applications a
        LEFT JOIN application_languages al ON a.id = al.application_id
        LEFT JOIN languages l ON al.language_id = l.id
        GROUP BY a.id
    ")->fetchAll();
    
    // Получаем статистику по языкам
    $stats = $pdo->query("
        SELECT l.name, COUNT(al.application_id) as count
        FROM languages l
        LEFT JOIN application_languages al ON l.id = al.language_id
        GROUP BY l.id
        ORDER BY count DESC
    ")->fetchAll();
    
    // Получаем список всех языков
    $all_languages = $pdo->query("SELECT name FROM languages")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Ошибка получения данных: " . $e->getMessage());
}

// Форма редактирования
$edit_data = null;
if ($action === 'edit' && $id) {
    foreach ($applications as $app) {
        if ($app['id'] == $id) {
            $edit_data = $app;
            $edit_data['languages'] = explode(',', $app['languages']);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .stats-container {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .stat-item {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Админ-панель</h1>
        
        <!-- Статистика по языкам -->
        <div class="stats-container">
            <h3>Статистика по языкам программирования</h3>
            <?php foreach ($stats as $stat): ?>
                <div class="stat-item">
                    <strong><?= htmlspecialchars($stat['name']) ?>:</strong>
                    <?= $stat['count'] ?> пользователей
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Форма редактирования -->
        <?php if ($edit_data): ?>
            <div class="card mb-4">
                <div class="card-header">Редактирование заявки #<?= $edit_data['id'] ?></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                        
                        <div class="form-group">
                            <label>ФИО</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_data['name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Телефон</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($edit_data['phone']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit_data['email']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Дата рождения</label>
                            <input type="date" name="birthdate" class="form-control" value="<?= htmlspecialchars($edit_data['birthdate']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Пол</label>
                            <select name="gender" class="form-control" required>
                                <option value="male" <?= $edit_data['gender'] === 'male' ? 'selected' : '' ?>>Мужской</option>
                                <option value="female" <?= $edit_data['gender'] === 'female' ? 'selected' : '' ?>>Женский</option>
                                <option value="other" <?= $edit_data['gender'] === 'other' ? 'selected' : '' ?>>Другое</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Языки программирования</label>
                            <select name="languages[]" class="form-control" multiple required>
                                <?php foreach ($all_languages as $lang): ?>
                                    <option value="<?= htmlspecialchars($lang) ?>" 
                                        <?= in_array($lang, $edit_data['languages']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lang) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Биография</label>
                            <textarea name="bio" class="form-control" required><?= htmlspecialchars($edit_data['bio']) ?></textarea>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" name="contract_accepted" class="form-check-input" id="contract" 
                                <?= $edit_data['contract_accepted'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="contract">Контракт принят</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a href="index.php" class="btn btn-secondary">Отмена</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Таблица с заявками -->
        <h2>Все заявки</h2>
        <table class="table table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>ФИО</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Языки</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?= $app['id'] ?></td>
                        <td><?= htmlspecialchars($app['name']) ?></td>
                        <td><?= htmlspecialchars($app['email']) ?></td>
                        <td><?= htmlspecialchars($app['phone']) ?></td>
                        <td><?= htmlspecialchars($app['languages']) ?></td>
                        <td>
                            <a href="index.php?action=edit&id=<?= $app['id'] ?>" class="btn btn-sm btn-warning">Редактировать</a>
                            <a href="index.php?action=delete&id=<?= $app['id'] ?>" class="btn btn-sm btn-danger" 
                               onclick="return confirm('Вы уверены?')">Удалить</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
