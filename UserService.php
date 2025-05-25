<?php

class UserService
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Генерирует ежедневный совет по активности на основе статистики пользователя за последнюю неделю.
     *
     * @param int $userId ID пользователя.
     * @return array Массив с текстом совета и статистикой.
     */
    public function generateDailyAdvice(int $userId): array
    {
        $advice = [
            'text' => 'Мы проанализировали вашу активность за последнюю неделю. ',
            'stats' => []
        ];

        try {
            $stmt = $this->db->prepare("
                SELECT
                    SUM(steps) AS total_steps,
                    SUM(distance) AS total_distance,
                    SUM(workout_minutes) AS total_workout,
                    AVG(sleep_time) AS avg_sleep,
                    COUNT(DISTINCT CASE WHEN steps > 0 THEN date END) AS active_days,
                    COUNT(DISTINCT CASE WHEN workout_minutes > 0 THEN date END) AS workout_days,
                    MAX(steps) AS max_steps,
                    MAX(workout_minutes) AS max_workout
                FROM
                    user_activity
                WHERE
                    user_id = ? AND date >= CURRENT_DATE - INTERVAL '7 days'
            ");
            $stmt->execute([$userId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stats || $stats['total_steps'] === null) {
                $advice['text'] .= "У нас пока нет данных о вашей активности за последнюю неделю. Начните отслеживать свои шаги и тренировки!";
                $advice['stats'] = [
                    'steps' => 0, 'distance' => 0, 'workouts' => 0, 'sleep' => 0,
                    'active_days' => 0, 'workout_days' => 0, 'max_steps' => 0, 'max_workout' => 0
                ];
                return $advice;
            }

            $advice['stats'] = [
                'steps' => (int)$stats['total_steps'],
                'distance' => (float)$stats['total_distance'],
                'workouts' => (int)$stats['total_workout'],
                'sleep' => (int)$stats['avg_sleep'],
                'active_days' => (int)$stats['active_days'],
                'workout_days' => (int)$stats['workout_days'],
                'max_steps' => (int)$stats['max_steps'],
                'max_workout' => (int)$stats['max_workout']
            ];

            $avg_daily_steps = $stats['total_steps'] / 7;
            $avg_daily_workout = $stats['total_workout'] / 7;

            // Логика формирования совета
            if ($avg_daily_steps < 5000) {
                $advice['text'] .= "На этой неделе ты был **недостаточно активен**. Попробуй увеличить количество шагов до 7000-10000 в день. Небольшие прогулки каждый день могут существенно улучшить твое самочувствие! ";
            } elseif ($avg_daily_steps >= 5000 && $avg_daily_steps < 10000) {
                $advice['text'] .= "Ты показываешь **хорошую активность**, старайся поддерживать её! Попробуй добавить немного интенсивности или увеличить длительность тренировок. ";
            } else { // avg_daily_steps >= 10000
                $advice['text'] .= "Ты был **очень активен** на этой неделе! Помни о важности восстановления. Возможно, пришло время для активного отдыха или растяжки. ";
            }

            if ($stats['workout_days'] < 2) {
                $advice['text'] .= "Попробуй добавить регулярные тренировки, например, 2-3 раза в неделю. Это поможет укрепить мышцы и улучшить выносливость. ";
            } elseif ($stats['workout_days'] >= 5) {
                $advice['text'] .= "Ты много тренируешься! Не забывай давать своим мышцам достаточно времени на восстановление. Возможно, день отдыха или легкая активность пойдут на пользу. ";
            }

            if ($stats['avg_sleep'] < 420) { // Менее 7 часов
                $advice['text'] .= "Твой средний сон составляет всего " . round($stats['avg_sleep'] / 60, 1) . " часов. Постарайся увеличить время сна до 7-9 часов, это критически важно для восстановления и энергии. ";
            } elseif ($stats['avg_sleep'] >= 420 && $stats['avg_sleep'] <= 540) { // 7-9 часов
                $advice['text'] .= "Ты отлично спишь, это очень важно для твоего здоровья и восстановления! ";
            } else { // Более 9 часов
                $advice['text'] .= "Ты спишь более 9 часов в среднем. Это хорошо, но убедись, что это не признак переутомления или недостатка энергии в течение дня. ";
            }

            return $advice;

        } catch (PDOException $e) {
            error_log("Ошибка при генерации совета: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Ошибка сервера при генерации совета: ' . $e->getMessage()];
        }
    }
}