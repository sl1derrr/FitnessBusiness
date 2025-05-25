<?php

class WorkoutService
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getWorkoutDetails(int $scheduleId, string $tgId): array
    {
        if (empty($scheduleId)) {
            http_response_code(400);
            return ['error' => 'Не указан ID расписания'];
        }

        try {
            // Получаем информацию о тренировке и тренере
            $stmt = $this->db->prepare("
                SELECT
                    ws.id,
                    ws.start_time,
                    ws.end_time,
                    ws.available_slots,
                    ws.status,
                    w.id AS workout_id,
                    w.name,
                    w.description,
                    w.duration_min,
                    w.max_participants,
                    t.id AS trainer_id,
                    t.first_name,
                    t.last_name,
                    t.specialization,
                    t.photo_url
                FROM
                    workout_schedule ws
                JOIN
                    workouts w ON ws.workout_id = w.id
                JOIN
                    trainers t ON ws.trainer_id = t.id
                WHERE
                    ws.id = ?
            ");
            $stmt->execute([$scheduleId]);
            $workout = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workout) {
                http_response_code(404);
                return ['error' => 'Тренировка не найдена'];
            }

            // Проверяем, записан ли пользователь на эту тренировку
            $isBooked = false;
            $userStmt = $this->db->prepare("SELECT id FROM users WHERE telegram_id = ?");
            $userStmt->execute([$tgId]);
            $userId = $userStmt->fetchColumn();

            if ($userId) {
                $bookingStmt = $this->db->prepare("
                    SELECT id FROM user_workouts
                    WHERE user_id = ? AND schedule_id = ? AND status = 'booked'
                ");
                $bookingStmt->execute([$userId, $scheduleId]);
                $isBooked = (bool)$bookingStmt->fetchColumn();
            }

            $start_time = new DateTime($workout['start_time']);
            $end_time = new DateTime($workout['end_time']);

            http_response_code(200);
            return [
                'workout' => [
                    'id' => $workout['id'],
                    'name' => $workout['name'],
                    'description' => $workout['description'],
                    'duration_min' => $workout['duration_min'],
                    'max_participants' => $workout['max_participants'],
                    'status' => $workout['status']
                ],
                'trainer' => [
                    'id' => $workout['trainer_id'],
                    'first_name' => $workout['first_name'],
                    'last_name' => $workout['last_name'],
                    'specialization' => $workout['specialization'],
                    'photo_url' => $workout['photo_url']
                ],
                'time' => $start_time->format('H:i') . ' - ' . $end_time->format('H:i'),
                'available_slots' => $workout['available_slots'],
                'is_booked' => $isBooked
            ];

        } catch (PDOException $e) {
            error_log("Ошибка при получении деталей тренировки: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Ошибка сервера: ' . $e->getMessage()];
        }
    }

    /**
     * Обрабатывает запрос на запись пользователя на тренировку.
     *
     * @param int $scheduleId ID расписания тренировки.
     * @param string $tgId Telegram ID пользователя.
     * @return array Результат операции (успех/ошибка) и обновленные данные.
     */
    public function bookWorkout(int $scheduleId, string $tgId): array
    {
        if (empty($scheduleId) || empty($tgId)) {
            http_response_code(400);
            return ['success' => false, 'error' => 'Не указан ID расписания или Telegram ID'];
        }

        $this->db->beginTransaction();
        try {
            // 1. Получаем ID пользователя или создаем нового
            $stmt = $this->db->prepare("SELECT id FROM users WHERE telegram_id = ?");
            $stmt->execute([$tgId]);
            $userId = $stmt->fetchColumn();

            if (!$userId) {
                // Если пользователя нет, создаем его
                $stmt = $this->db->prepare("INSERT INTO users (telegram_id) VALUES (?)");
                $stmt->execute([$tgId]);
                $userId = $this->db->lastInsertId();
            }

            // 2. Проверяем наличие свободных мест и статус тренировки
            // Используем FOR UPDATE для предотвращения гонок данных
            $stmt = $this->db->prepare("SELECT available_slots, start_time, status FROM workout_schedule WHERE id = ? FOR UPDATE");
            $stmt->execute([$scheduleId]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$schedule) {
                $this->db->rollBack();
                http_response_code(404);
                return ['success' => false, 'error' => 'Расписание тренировки не найдено'];
            }

            if ($schedule['available_slots'] <= 0) {
                $this->db->rollBack();
                http_response_code(400);
                return ['success' => false, 'error' => 'Нет свободных мест на эту тренировку.'];
            }

            if ($schedule['status'] !== 'scheduled') {
                $this->db->rollBack();
                http_response_code(400);
                return ['success' => false, 'error' => 'Запись на эту тренировку закрыта или она отменена.'];
            }

            // Проверяем, не записан ли пользователь уже на эту тренировку
            $stmt = $this->db->prepare("SELECT id FROM user_workouts WHERE user_id = ? AND schedule_id = ? AND status = 'booked'");
            $stmt->execute([$userId, $scheduleId]);
            if ($stmt->fetch()) {
                $this->db->rollBack();
                http_response_code(400);
                return ['success' => false, 'error' => 'Вы уже записаны на эту тренировку.'];
            }

            // 3. Уменьшаем количество свободных мест
            $stmt = $this->db->prepare("UPDATE workout_schedule SET available_slots = available_slots - 1 WHERE id = ?");
            $stmt->execute([$scheduleId]);

            // 4. Записываем пользователя
            $stmt = $this->db->prepare("INSERT INTO user_workouts (user_id, schedule_id, status) VALUES (?, ?, 'booked')");
            $stmt->execute([$userId, $scheduleId]);

            $this->db->commit();

            // Обновляем количество слотов для ответа
            $schedule['available_slots']--;
            http_response_code(200);
            return [
                'success' => true,
                'message' => 'Вы успешно записаны на тренировку!',
                'available_slots' => $schedule['available_slots']
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Ошибка при записи на тренировку: " . $e->getMessage());
            http_response_code(500);
            return ['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()];
        }
    }
}