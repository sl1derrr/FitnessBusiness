<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Подключение к базе данных
$db = new PDO("pgsql:host=localhost;dbname=fitness_db", "fitness_user", "527229ii");

// Получаем Telegram ID
$tg_id = $_GET['tg_id'] ?? $_COOKIE['tg_id'] ?? null;

// Если нет Telegram ID - редирект на страницу входа
if (!$tg_id) {
    header("Location: /sign_in.html");
    exit();
}

// Проверяем наличие пользователя в базе
$userStmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE telegram_id = ?");
$userStmt->execute([$tg_id]);
$userData = $userStmt->fetch();

Если пользователя нет - редирект на страницу регистрации
    if (!$userData) {
    header("Location: /sign_in.html?tg_id=" . urlencode($tg_id));
    exit();
}

// Функция для генерации совета на основе недельной статистики
function generateDailyAdvice($db, $userId) {
    // Получаем данные за последние 7 дней
    $weekStmt = $db->prepare("
        SELECT
            SUM(steps) as total_steps,
            SUM(distance) as total_distance,
            SUM(workout_minutes) as total_workout,
            AVG(sleep_time) as avg_sleep,
            COUNT(CASE WHEN steps >= 10000 THEN 1 END) as active_days,
            COUNT(CASE WHEN workout_minutes >= 30 THEN 1 END) as workout_days,
            MAX(steps) as max_steps,
            MAX(workout_minutes) as max_workout
        FROM user_activity
        WHERE user_id = ? AND date >= CURRENT_DATE - INTERVAL '6 days'
    ");
    $weekStmt->execute([$userId]);
    $weekData = $weekStmt->fetch(PDO::FETCH_ASSOC);

    // Нормализуем данные
    $totalSteps = $weekData['total_steps'] ?? 0;
    $totalDistance = $weekData['total_distance'] ?? 0;
    $totalWorkout = $weekData['total_workout'] ?? 0;
    $avgSleep = $weekData['avg_sleep'] ?? 0;
    $activeDays = $weekData['active_days'] ?? 0;
    $workoutDays = $weekData['workout_days'] ?? 0;
    $maxSteps = $weekData['max_steps'] ?? 0;
    $maxWorkout = $weekData['max_workout'] ?? 0;

    // Определяем уровень активности
    $activityLevel = 'low';
    if ($totalSteps > 50000 || $totalWorkout > 180) $activityLevel = 'high';
    elseif ($totalSteps > 30000 || $totalWorkout > 90) $activityLevel = 'medium';

    // Определяем качество сна
    $sleepQuality = 'poor';
    if ($avgSleep >= 480) $sleepQuality = 'excellent'; // 8+ часов
    elseif ($avgSleep >= 420) $sleepQuality = 'good'; // 7+ часов
    elseif ($avgSleep >= 360) $sleepQuality = 'fair'; // 6+ часов

    // Генерируем совет на основе анализа
    if ($activityLevel == 'high' && $workoutDays >= 5) {
        $advice = "Ты был в ударе на этой неделе. Тебе стоит отдохнуть или растянуть свои мышцы для восстановления.";
    }
    elseif ($activityLevel == 'low' && $activeDays < 3) {
        $advice = "На этой неделе ты был недостаточно активен. Сегодня отличный день для прогулки или легкой тренировки!";
    }
    elseif ($totalSteps < 30000 && $totalWorkout < 60) {
        $advice = "Твоя активность на этой неделе была умеренной. Попробуй сегодня сделать 30-минутную тренировку или пройти 8000 шагов.";
    }
    elseif ($workoutDays == 0) {
        $advice = "На этой неделе у тебя не было тренировок. Сегодня отличный день для начала! Попробуй нашу 20-минутную тренировку для начинающих.";
    }
    elseif ($totalWorkout > 240) {
        $advice = "Более 4 часов тренировок на этой неделе - это впечатляет! Сегодня можешь повторить свою любимую тренировку.";
    }
    elseif ($sleepQuality == 'poor' && $activityLevel == 'high') {
        $advice = "Ты много тренировался, но мало спал. Сегодня постарайся лечь спать на час раньше и сделай легкую растяжку.";
    }
    elseif ($sleepQuality == 'excellent' && $activityLevel == 'low') {
        $advice = "Ты хорошо высыпаешься, но мало двигаешься. Используй свою энергию для активной прогулки сегодня!";
    }
    elseif ($totalDistance >= 20 && $totalDistance < 40) {
        $advice = "Ты уже преодолел более 20 км на этой неделе! Сегодня поставь цель пройти еще 5 км.";
    }
    elseif ($totalSteps > 70000) {
        $advice = "Более 70,000 шагов за неделю - это отлично! Сегодня можешь немного снизить темп и сосредоточиться на растяжке.";
    }
    elseif ($avgSleep < 360 && $totalWorkout > 120) {
        $advice = "Твои тренировки интенсивны, но сна недостаточно. Сегодня удели внимание восстановлению - попробуй йогу.";
    }
    elseif ($workoutDays >= 5 && $activeDays >= 5) {
        $advice = "Ты тренировался 5+ дней на этой неделе - это отличная последовательность! Продолжай в том же духе.";
    }
    elseif ($maxSteps > 15000) {
        $advice = "На этой неделе у тебя был день с более чем 15,000 шагов! Сегодня можешь повторить это достижение.";
    }
    elseif ($totalWorkout > 0 && $totalWorkout < 60) {
        $advice = "Ты делаешь первые шаги в тренировках. Сегодня попробуй увеличить продолжительность тренировки на 10-15 минут.";
    }
    elseif ($totalSteps > 40000 && $totalSteps < 60000) {
        $advice = "Твоя недельная активность на хорошем уровне. Поставь сегодня цель пройти на 2000 шагов больше.";
    }
    else {
        $advice = "Твои показатели за неделю сбалансированы. Сегодня можешь сделать легкую тренировку или активную прогулку.";
    }

    return [
        'text' => $advice,
        'stats' => [
            'steps' => $totalSteps,
            'distance' => round($totalDistance, 1),
            'workout' => $totalWorkout,
            'sleep' => $avgSleep,
            'active_days' => $activeDays,
            'workout_days' => $workoutDays
        ]
    ];
}

// Генерируем совет
$dailyAdvice = generateDailyAdvice($db, $userData['id']);

// Получаем данные активности за сегодня
$todayStmt = $db->prepare("
    SELECT
        COALESCE(SUM(steps), 0) as steps,
        COALESCE(SUM(distance), 0) as distance,
        COALESCE(SUM(calories), 0) as calories,
        COALESCE(SUM(workout_minutes), 0) as workout_minutes,
        COALESCE(SUM(sleep_time), 0) as sleep_time_minutes
    FROM user_activity
    WHERE user_id = ? AND date = CURRENT_DATE
");
$todayStmt->execute([$userData['id']]);
$todayActivity = $todayStmt->fetch(PDO::FETCH_ASSOC);

// Конвертируем минуты сна в часы и минуты
$sleepMinutes = $todayActivity['sleep_time_minutes'] ?? 0;
$sleepHours = floor($sleepMinutes / 60);
$remainingMinutes = $sleepMinutes % 60;
$todayActivity['sleep_time'] = $sleepHours . 'ч ' . $remainingMinutes . 'м';
$todayActivity['sleep_score'] = min(100, floor(($sleepMinutes / (8 * 60)) * 100));

// Получаем данные активности за последние 7 дней
$activityStmt = $db->prepare("
    SELECT
        date,
        COALESCE(SUM(steps), 0) as steps,
        COALESCE(SUM(distance), 0) as distance,
        COALESCE(SUM(calories), 0) as calories
    FROM user_activity
    WHERE user_id = ? AND date >= CURRENT_DATE - INTERVAL '6 days'
    GROUP BY date
    ORDER BY date
");
$activityStmt->execute([$userData['id']]);
$rawWeekData = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

// Заполняем недостающие дни нулевыми значениями
$weekData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $found = false;

    foreach ($rawWeekData as $day) {
        if ($day['date'] == $date) {
            $weekData[] = $day;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $weekData[] = [
            'date' => $date,
            'steps' => 0,
            'distance' => 0,
            'calories' => 0
        ];
    }
}

// Функция для получения короткого названия дня недели
function getShortDayName($date) {
    $days = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    return $days[date('w', strtotime($date))];
}

// Получаем статистику для достижений
$achievementsStmt = $db->prepare("
    SELECT
        COUNT(DISTINCT date) as workout_days,
        SUM(steps) as total_steps,
        SUM(distance) as total_distance,
        SUM(workout_minutes) as total_workout_minutes,
        COUNT(CASE WHEN steps >= 10000 THEN 1 END) as days_10000_steps,
        COUNT(CASE WHEN workout_minutes >= 60 THEN 1 END) as days_60_min_workout,
        MAX(steps) as max_steps_day
    FROM user_activity
    WHERE user_id = ?
");
$achievementsStmt->execute([$userData['id']]);
$achievementsData = $achievementsStmt->fetch(PDO::FETCH_ASSOC);

// Определяем, какие достижения разблокированы
$unlockedAchievements = [
    'early_bird' => false,
    'step_master' => ($achievementsData['days_10000_steps'] ?? 0) >= 3,
    'marathon' => ($achievementsData['total_distance'] ?? 0) >= 42,
    'yoga_master' => ($achievementsData['total_workout_minutes'] ?? 0) >= 300,
    'night_owl' => false,
    'streak' => ($achievementsData['workout_days'] ?? 0) >= 5
];


// Получаем все тренировки на сегодня из workout_schedule
$todayWorkoutsStmt = $db->prepare("
    SELECT workout_schedule.id, workout_schedule.start_time, workout_schedule.end_time,
            workouts.name, workouts.duration_min
    FROM workout_schedule
    JOIN workouts ON workout_schedule.workout_id = workouts.id
    WHERE DATE(workout_schedule.start_time) = CURRENT_DATE
    AND workout_schedule.status = 'scheduled'
    ORDER BY workout_schedule.start_time
");
$todayWorkoutsStmt->execute();
$todayWorkouts = $todayWorkoutsStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8"/>
        <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
        <title>
            FitnessBusiness
        </title>
        <link href="style.css" rel="stylesheet"/>
        <script src="https://telegram.org/js/telegram-web-app.js">
        </script>
    </head>
    <body>
        <div class="phone-screen">
            <div class="container">
                <div class="tab-content active" id="home-content">
                    <div class="app-header">
                        <h1 class="app-title">
                            FitnessBusiness
                        </h1>
                        <div class="user-avatar" id="user-avatar">
                            MM
                        </div>
                    </div>
                    <div class="progress-section">
                        <div class="progress-container">
                            <svg height="200" viewbox="0 0 200 200" width="200">
                                <circle class="progress-circle progress-bg" cx="100" cy="100" r="88">
                                </circle>
                                <circle class="progress-circle progress-fill" cx="100" cy="100" id="progress-ring" r="88"
                                    stroke-dasharray="<?php echo min(($todayActivity['steps'] / 10000) * 552, 552); ?> 552">
                                </circle>
                            </svg>
                            <div class="progress-text">
                                <div class="step-count" id="step-counter">
                                    <?php echo number_format($todayActivity['steps'], 0, '', ' '); ?>
                                </div>
                                <div class="step-label">
                                    шагов
                                </div>
                                <div class="step-goal">
                                    Цель: 10,000
                                </div>
                            </div>
                        </div>

                        <div class="progress-section">
                            <div class="progress-container">
                                <svg height="200" viewbox="0 0 200 200" width="200">
                                    <circle class="progress-circle progress-bg" cx="100" cy="100" r="88"></circle>
                                    <circle class="progress-circle progress-fill" cx="100" cy="100" id="progress-ring" r="88"
                                        stroke-dasharray="<?php echo min(($todayActivity['steps'] / 10000) * 552, 552); ?> 552">
                                    </circle>
                                </svg>
                                <div class="progress-text">
                                    <div class="step-count" id="step-counter">
                                        <?php echo number_format($todayActivity['steps'], 0, '', ' '); ?>
                                    </div>
                                    <div class="step-label">шагов</div>
                                    <div class="step-goal">Цель: 10,000</div>
                                </div>
                            </div>
                            <div class="activity-stats">
                                <div class="activity-stat">
                                    <div class="stat-value"><?php echo number_format($todayActivity['distance'], 1); ?></div>
                                    <div class="stat-label">км</div>
                                </div>
                                <div class="activity-stat">
                                    <div class="stat-value"><?php echo number_format($todayActivity['calories'], 0, '', ' '); ?></div>
                                    <div class="stat-label">кал</div>
                                </div>
                                <div class="activity-stat">
                                    <div class="stat-value"><?php echo number_format($todayActivity['workout_minutes'], 0, '', ' '); ?></div>
                                    <div class="stat-label">мин</div>
                                </div>
                            </div>
                        </div>

                        <div class="activity-stats">
                            <div class="activity-stat">
                                <div class="stat-value">
                                    <?php echo number_format($todayActivity['distance'], 1); ?>
                                </div>
                                <div class="stat-label">
                                    км
                                </div>
                            </div>
                            <div class="activity-stat">
                                <div class="stat-value">
                                    <?php echo number_format($todayActivity['calories'], 0, '', ' '); ?>
                                </div>
                                <div class="stat-label">
                                    кал
                                </div>
                            </div>
                            <div class="activity-stat">
                                <div class="stat-value">
                                    <?php echo number_format($todayActivity['workout_minutes'], 0, '', ' '); ?>
                                </div>
                                <div class="stat-label">
                                    мин
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="weekly-chart">
                        <div class="chart-header">
                            <div class="chart-title">
                                Недельная активность
                            </div>
                            <div class="chart-controls">
                                <button class="chart-button steps active" data-type="steps">
                                    Шаги
                                </button>
                                <button class="chart-button calories" data-type="calories">
                                    Калории
                                </button>
                                <button class="chart-button distance" data-type="distance">
                                    Дистанция
                                </button>
                            </div>
                        </div>
                        <div class="chart-container" id="chart-container">
                            <?php foreach ($weekData as $index => $day): ?>
                            <div class="chart-bar steps" data-calories="<?php echo $day['calories']; ?>" data-distance="<?php echo $day['distance']; ?>" data-steps="<?php echo $day['steps']; ?>">
                                <div class="bar-value">
                                    <?php echo number_format($day['steps'], 0, '', ' '); ?>
                                </div>
                                <div class="day-label">
                                    <?php echo getShortDayName($day['date']); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="section-title">
                        <svg fill="none" height="18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="18">
                            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z">
                            </path>
                            <polyline points="14 2 14 8 20 8">
                            </polyline>
                        </svg>
                        Тренировки сегодня
                    </div>
                    <div class="workout-list">
                        <?php if (!empty($todayWorkouts)): ?>
                        <?php foreach ($todayWorkouts as $workout):
                            $startTime = new DateTime($workout['start_time']);
                            $endTime = new DateTime($workout['end_time']);
                            $duration = $endTime->diff($startTime);
                        ?>
                        <div class="workout-card">
                            <div class="workout-icon">
                                <svg fill="none" height="24" stroke="#4CC9F0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="24">
                                    <path d="M4 17l6-6-4-4 6-6m4 18v-8m4 4h-8"></path>
                                </svg>
                            </div>
                            <div class="workout-info">
                                <div class="workout-name">
                                    <?php echo htmlspecialchars($workout['name']); ?>
                                </div>
                                <div class="workout-meta">
                                    <span><?php echo $startTime->format('H:i'); ?> - <?php echo $endTime->format('H:i'); ?></span>
                                    <span><?php echo $duration->format('%h ч %i мин'); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="workout-card">
                            <div class="workout-icon">
                                <svg fill="none" height="24" stroke="#4CC9F0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="24">
                                    <path d="M4 17l6-6-4-4 6-6m4 18v-8m4 4h-8"></path>
                                </svg>
                            </div>
                            <div class="workout-info">
                                <div class="workout-name">Нет тренировок на сегодня</div>
                                <div class="workout-meta">
                                    <span>Проверьте расписание на другие дни</span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="section-title">
                        <svg fill="none" height="18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="18">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2">
                            </path>
                        </svg>
                        Данные о здоровье
                    </div>
                    <div class="card sleep-card">
                        <div class="sleep-header">
                            <div class="sleep-icon-container">
                                <svg fill="none" height="20" stroke="#4361EE" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="20">
                                    <path d="M12 2a8 8 0 0 0-8 8c0 5.2 3.4 8.2 8 14 4.6-5.8 8-8.8 8-14a8 8 0 0 0-8-8z">
                                    </path>
                                    <path d="M12 6v4">
                                    </path>
                                    <path d="M12 14h.01">
                                    </path>
                                </svg>
                            </div>
                            <div class="sleep-info">
                                <div class="sleep-title">
                                    Качество сна
                                </div>
                                <div class="sleep-time">
                                    Последняя ночь: <?php echo $todayActivity['sleep_time']; ?>
                                </div>
                            </div>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar sleep-progress" style="width: <?php echo $todayActivity['sleep_score']; ?>%">
                            </div>
                        </div>
                        <div class="sleep-score">
                            <span class="score-label">
                                Оценка сна
                            </span>
                            <span class="score-value">
                                <?php echo $todayActivity['sleep_score']; ?>/100
                                (<?php
                                    if ($todayActivity['sleep_score'] >= 85) echo 'Отличный';
                                    elseif ($todayActivity['sleep_score'] >= 70) echo 'Хороший';
                                    elseif ($todayActivity['sleep_score'] >= 50) echo 'Средний';
                                    else echo 'Плохой';
                                ?>)
                            </span>
                        </div>
                    </div>
                    <div class="section-title">
                        <svg fill="none" height="18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="18">
                            <path d="M15.5 2H8.6c-.4 0-.8.2-1.1.5-.3.3-.5.7-.5 1.1v12.8c0 .4.2.8.5 1.1.3.3.7.5 1.1.5h9.8c.4 0 .8-.2 1.1-.5.3-.3.5-.7.5-1.1V6.5L15.5 2z">
                            </path>
                            <path d="M3 7.6v12.8c0 .4.2.8.5 1.1.3.3.7.5 1.1.5h9.8">
                            </path>
                            <path d="M15 2v5h5">
                            </path>
                        </svg>
                        Для тебя
                    </div>
                    <div class="card recommendation-card" data-distance="<?php echo $dailyAdvice['stats']['distance']; ?>" data-sleep="<?php echo $dailyAdvice['stats']['sleep']; ?>" data-steps="<?php echo $dailyAdvice['stats']['steps']; ?>" data-workout="<?php echo $dailyAdvice['stats']['workout']; ?>">
                        <div class="recommendation-icon">
                            <svg fill="none" height="24" stroke="#4CC9F0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="24">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10">
                                </path>
                            </svg>
                        </div>
                        <div class="recommendation-content">
                            <div class="recommendation-title">
                                Совет на день
                            </div>
                            <div class="recommendation-text">
                                <?php echo htmlspecialchars($dailyAdvice['text']); ?>
                            </div>
                            <button class="advice-button">Посмотреть итоги последней недели</button>
                        </div>
                    </div>
                </div>
                <div class="card weekly-challenge">
                    <div class="challenge-header">
                        <div class="challenge-title">
                            Недельное испытание
                        </div>
                        <div class="challenge-days-left">
                            ОСТАЛОСЬ 3 ДНЯ
                        </div>
                    </div>
                    <div class="challenge-description">
                        Заверши 5 тренировок и заработай 500 бонусов
                    </div>
                    <div class="challenge-progress-container">
                        <div class="challenge-progress-bar">
                            <div class="challenge-progress-fill">
                            </div>
                        </div>
                        <div class="challenge-progress-text">
                            3/5
                        </div>
                    </div>
                    <button class="challenge-button">
                        Открыть испытание
                    </button>
                </div>
                <div class="section-title">
                    <svg fill="none" height="18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="18">
                        <path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12">
                        </path>
                        <circle cx="12" cy="8" r="7">
                        </circle>
                    </svg>
                    Достижения
                </div>
                <div class="achievements">
                    <div class="achievement <?php echo $unlockedAchievements['early_bird'] ? 'unlocked' : ''; ?>" id="early-bird">
                        <div class="achievement-icon">
                            <svg fill="none" height="22" stroke="#4CC9F0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22">
                                <circle cx="12" cy="12" r="10">
                                </circle>
                                <polyline points="12 6 12 12 16 14">
                                </polyline>
                            </svg>
                        </div>
                        <div class="achievement-name">
                            Жаворонок
                        </div>
                    </div>
                    <div class="achievement <?php echo $unlockedAchievements['step_master'] ? 'unlocked' : ''; ?>" id="step-master">
                        <div class="achievement-icon">
                            <svg fill="none" height="22" stroke="#4CC9F0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22">
                                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
                                </path>
                            </svg>
                        </div>
                        <div class="achievement-name">
                            Кардио-машина
                        </div>
                    </div>
                    <div class="achievement <?php echo $unlockedAchievements['marathon'] ? 'unlocked' : ''; ?>" id="marathon">
                        <div class="achievement-icon">
                            <svg fill="none" height="22" stroke="#4CC9F0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2">
                                </polygon>
                            </svg>
                        </div>
                        <div class="achievement-name">
                            Марафонист
                        </div>
                    </div>
                    <div class="achievement <?php echo $unlockedAchievements['yoga_master'] ? 'unlocked' : ''; ?>" id="yoga-master">
                        <div class="achievement-icon">
                            <svg fill="none" height="22" stroke="#4CC9F0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22">
                                <path d="M7 10v12">
                                </path>
                                <path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2h0a3.13 3.13 0 0 1 3 3.88Z">
                                </path>
                            </svg>
                        </div>
                        <div class="achievement-name">
                            Достойный спортсмен
                        </div>
                    </div>
                    <div class="achievement <?php echo $unlockedAchievements['night_owl'] ? 'unlocked' : ''; ?>" id="night-owl">
                        <div class="achievement-icon">
                            <svg fill="none" height="22" stroke="#4CC9F0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22">
                                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z">
                                </path>
                            </svg>
                        </div>
                        <div class="achievement-name">
                            Ночная сова
                        </div>
                    </div>
                    <div class="achievement <?php echo $unlockedAchievements['streak'] ? 'unlocked' : ''; ?>" id="streak">
                        <div class="achievement-icon">
                            <svg fill="none" height="22" stroke="#4CC9F0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22">
                                <path d="M21 3v5">
                                </path>
                                <path d="M18 3h6">
                                </path>
                                <path d="M3 16v5">
                                </path>
                                <path d="M6 21H0">
                                </path>
                                <path d="m12 3-9 9 9 9">
                                </path>
                            </svg>
                        </div>
                        <div class="achievement-name">
                            Держусь неделю
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="bottom-nav">
            <div class="nav-item active" id="home-tab">
                <svg class="nav-icon" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
                    </path>
                    <polyline points="9 22 9 12 15 12 15 22">
                    </polyline>
                </svg>
                <span class="nav-label">
                    Главная
                </span>
            </div>
            <div class="nav-item" id="workout-tab">
                <svg class="nav-icon" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22">
                    <path d="M20.57 14.86L22 13.43 20.57 12 17 15.57 8.43 7 12 3.43 10.57 2 9.14 3.43 7.71 2 5.57 4.14 4.14 2.71 2.71 4.14l1.43 1.43L2 7.71l1.43 1.43L2 10.57 3.43 12 7 8.43 15.57 17 12 20.57 13.43 22l1.43-1.43L16.29 22l2.14-2.14 1.43 1.43 1.43-1.43-1.43-1.43L22 16.29z">
                    </path>
                </svg>
                <span class="nav-label">
                    Тренировки
                </span>
            </div>
            <div class="nav-item" id="stats-tab">
                <svg class="nav-icon" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22">
                    <line x1="18" x2="18" y1="20" y2="10">
                    </line>
                    <line x1="12" x2="12" y1="20" y2="4">
                    </line>
                    <line x1="6" x2="6" y1="20" y2="14">
                    </line>
                </svg>
                <span class="nav-label">
                    Достижения
                </span>
            </div>
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 0; pointer-events: none;">
                <div id="tgIdDisplay" style="display: none;">
                    Telegram ID: загрузка...
                </div>
            </div>
            <script src="app.js">
            </script>
            <script src="https://telegram.org/js/telegram-web-app.js">
            </script>
        </div>
    </body>
</html>
