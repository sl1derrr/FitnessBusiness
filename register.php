<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Подключение к базе данных
$db = new PDO("pgsql:host=localhost;dbname=fitness_db", "fitness_user", "527229ii");

// Получаем данные из формы
$telegram_id = $_POST['telegram_id'] ?? null;
$firstName = $_POST['firstName'] ?? null;
$lastName = $_POST['lastName'] ?? null;
$height = $_POST['height'] ?? null;
$weight = $_POST['weight'] ?? null;
$goal = $_POST['goal'] ?? null;

// Валидация данных
if (!$telegram_id || !$firstName || !$lastName || !$height || !$weight || !$goal) {
    header("Location: /sign_in.html?error=missing_fields&tg_id=" . urlencode($telegram_id));
    exit();
}

if ($height < 100 || $height > 250 || $weight < 30 || $weight > 200) {
    header("Location: /sign_in.html?error=invalid_data&tg_id=" . urlencode($telegram_id));
    exit();
}

try {
    // Вставляем данные пользователя
    $stmt = $db->prepare("
        INSERT INTO users (telegram_id, first_name, last_name, height, weight, goal, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON CONFLICT (telegram_id)
        DO UPDATE SET
            first_name = EXCLUDED.first_name,
            last_name = EXCLUDED.last_name,
            height = EXCLUDED.height,
            weight = EXCLUDED.weight,
            goal = EXCLUDED.goal
    ");

    $stmt->execute([$telegram_id, $firstName, $lastName, $height, $weight, $goal]);

    // Устанавливаем куки
    setcookie('tg_id', $telegram_id, time() + 30 * 24 * 60 * 60, '/');

    // Перенаправляем на главную страницу
    header("Location: /index.php?tg_id=" . urlencode($telegram_id));
    exit();

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: /sign_in.html?error=db_error&tg_id=" . urlencode($telegram_id));
    exit();
}