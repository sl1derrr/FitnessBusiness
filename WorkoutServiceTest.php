<?php

use PHPUnit\Framework\TestCase;
// Подключаем класс, который мы будем тестировать
require_once '/var/www/fitnessbusinesstg.ru/src/WorkoutService.php';

class WorkoutServiceTest extends TestCase
{
    protected $dbMock;
    protected $workoutService;

    protected function setUp(): void
    {
        // Создаем мок-объект для PDO
        $this->dbMock = $this->createMock(PDO::class);
        // Создаем экземпляр тестируемого класса, передавая ему мок PDO
        $this->workoutService = new WorkoutService($this->dbMock);
    }

    /**
     * Модульный тест для WorkoutService::getWorkoutDetails: успешное получение деталей.
     */
    public function testGetWorkoutDetails_success()
    {
        // Мокируем PDOStatement для запроса деталей тренировки
        $stmtWorkoutMock = $this->createMock(PDOStatement::class);
        $stmtWorkoutMock->method('execute')->willReturn(true);
        $stmtWorkoutMock->method('fetch')->willReturn([
            'id' => 1,
            'start_time' => '2025-05-25 10:00:00',
            'end_time' => '2025-05-25 11:00:00',
            'available_slots' => 5,
            'status' => 'scheduled',
            'workout_id' => 101,
            'name' => 'Йога для начинающих',
            'description' => 'Расслабляющая тренировка',
            'duration_min' => 60,
            'max_participants' => 10,
            'trainer_id' => 201,
            'first_name' => 'Анна',
            'last_name' => 'Иванова',
            'specialization' => 'Йога',
            'photo_url' => 'https://example.com/anna.jpg'
        ]);

        // Мокируем PDOStatement для проверки наличия пользователя
        $stmtUserMock = $this->createMock(PDOStatement::class);
        $stmtUserMock->method('execute')->willReturn(true);
        $stmtUserMock->method('fetchColumn')->willReturn(123); // Пользователь найден

        // Мокируем PDOStatement для проверки существующей записи (false, т.к. пользователь не записан)
        $stmtBookingMock = $this->createMock(PDOStatement::class);
        $stmtBookingMock->method('execute')->willReturn(true);
        $stmtBookingMock->method('fetchColumnX')->willReturn(false);

        $this->dbMock->method('prepare')
            ->willReturnMap([
                ["
                SELECT
                    ws.id, ws.start_time, ws.end_time, ws.available_slots, ws.status,
                    w.id AS workout_id, w.name, w.description, w.duration_min, w.max_participants,
                    t.id AS trainer_id, t.first_name, t.last_name, t.specialization, t.photo_url
                FROM workout_schedule ws
                JOIN workouts w ON ws.workout_id = w.id
                JOIN trainers t ON ws.trainer_id = t.id
                WHERE ws.id = ?
            ", $stmtWorkoutMock],
                ["SELECT id FROM users WHERE telegram_id = ?", $stmtUserMock],
                ["
                    SELECT id FROM user_workouts
                    WHERE user_id = ? AND schedule_id = ? AND status = 'booked'
                ", $stmtBookingMock]
            ]);

        // Вызываем метод класса
        $response = $this->workoutService->getWorkoutDetails(1, 'test_tg_id');

        $this->assertArrayHasKey('workout', $response);
        $this->assertEquals('Йога для начинающих', $response['workout']['name']);
        $this->assertEquals('10:00 - 11:00', $response['time']);
        $this->assertFalse($response['is_booked']);
        $this->assertArrayNotHasKey('error', $response); // Убеждаемся, что нет ошибки
    }

    /**
     * Модульный тест для WorkoutService::getWorkoutDetails: отсутствует ID расписания.
     */
    public function testGetWorkoutDetails_missingScheduleId()
    {
        $response = $this->workoutService->getWorkoutDetails(0, 'test_tg_id'); // Передаем 0 как некорректный ID

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Не указан ID расписания', $response['error']);
    }

    /**
     * Модульный тест для WorkoutService::getWorkoutDetails: тренировка не найдена.
     */
    public function testGetWorkoutDetails_workoutNotFound()
    {
        $stmtWorkoutMock = $this->createMock(PDOStatement::class);
        $stmtWorkoutMock->method('execute')->willReturn(true);
        $stmtWorkoutMock->method('fetch')->willReturn(false); // Тренировка не найдена

        $this->dbMock->method('prepare')->willReturn($stmtWorkoutMock);

        $response = $this->workoutService->getWorkoutDetails(999, 'test_tg_id');

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Тренировка не найдена', $response['error']);
    }

    /**
     * Модульный тест для WorkoutService::bookWorkout: успешная запись.
     */
    public function testBookWorkout_success()
    {
        // Ожидаем вызовы beginTransaction и commit
        $this->dbMock->expects($this->once())->method('beginTransaction');
        $this->dbMock->expects($this->once())->method('commit');
        $this->dbMock->expects($this->never())->method('rollBack');

        // Мок для получения ID пользователя (или создания нового)
        $userStmtMock = $this->createMock(PDOStatement::class);
        $userStmtMock->method('execute')->willReturn(true);
        $userStmtMock->method('fetchColumn')->willReturn(123); // Пользователь уже существует

        // Мок для проверки свободных мест и статуса
        $checkScheduleStmtMock = $this->createMock(PDOStatement::class);
        $checkScheduleStmtMock->method('execute')->willReturn(true);
        $checkScheduleStmtMock->method('fetch')->willReturn([
            'available_slots' => 5,
            'start_time' => (new DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            'status' => 'scheduled'
        ]);

        // Мок для проверки существующей записи (пользователь еще не записан)
        $checkBookingStmtMock = $this->createMock(PDOStatement::class);
        $checkBookingStmtMock->method('execute')->willReturn(true);
        $checkBookingStmtMock->method('fetch')->willReturn(false);

        // Мок для обновления количества слотов
        $updateSlotsStmtMock = $this->createMock(PDOStatement::class);  
        $updateSlotsStmtMock->method('execute')->willReturn(true);

        // Мок для вставки записи пользователя
        $insertBookingStmtMock = $this->createMock(PDOStatement::class);
        $insertBookingStmtMock->method('execute')->willReturn(true);

        // Настраиваем PDO-мок для возврата различных стейтментов
        $this->dbMock->method('prepare')
            ->willReturnMap([
                ["SELECT id FROM users WHERE telegram_id = ?", $userStmtMock],
                ["SELECT available_slots, start_time, status FROM workout_schedule WHERE id = ? FOR UPDATE", $checkScheduleStmtMock],
                ["SELECT id FROM user_workouts WHERE user_id = ? AND schedule_id = ? AND status = 'booked'", $checkBookingStmtMock],
                ["UPDATE workout_schedule SET available_slots = available_slots - 1 WHERE id = ?", $updateSlotsStmtMock],
                ["INSERT INTO user_workouts (user_id, schedule_id, status) VALUES (?, ?, 'booked')", $insertBookingStmtMock],
            ]);

        $response = $this->workoutService->bookWorkout(1, 'test_tg_id');

        $this->assertTrue($response['success']);
        $this->assertEquals('Вы успешно записаны на тренировку!', $response['message']);
        $this->assertEquals(4, $response['available_slots']);
    }

    /**
     * Модульный тест для WorkoutService::bookWorkout: нет свободных мест.
     */
    public function testBookWorkout_noAvailabXleSlots()
    {
        $this->dbMock->expects($this->once())->method('beginTransaction');
        $this->dbMock->expects($this->never())->method('commit');
        $this->dbMock->expects($this->once())->method('rollBack');

        $userStmtMock = $this->createMock(PDOStatement::class);
        $userStmtMock->method('execute')->willReturn(true);
        $userStmtMock->method('fetchColumn')->willReturn(123);

        $checkScheduleStmtMock = $this->createMock(PDOStatement::class);
        $checkScheduleStmtMock->method('execute')->willReturn(true);
        $checkScheduleStmtMock->method('fetch')->willReturn([
            'available_slots' => 0, // Нет свободных мест
            'start_time' => (new DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            'status' => 'scheduled'
        ]);

        $this->dbMock->method('prepare')
            ->willReturnMap([
                ["SELECT id FROM users WHERE telegram_id = ?", $userStmtMock],
                ["SELECT available_slots, start_time, status FROM workout_schedule WHERE id = ? FOR UPDATE", $checkScheduleStmtMock],
            ]);

        $response = $this->workoutService->bookWorkout(1, 'test_tg_id');

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Нет свободных мест', $response['error']);
    }

    /**
     * Модульный тест для WorkoutService::bookWorkout: пользователь уже записан.
     */
    public function testBookWorkout_alreadyBooked()
    {
        $this->dbMock->expects($this->once())->method('beginTransaction');
        $this->dbMock->expects($this->never())->method('commit');
        $this->dbMock->expects($this->once())->method('rollBack');

        $userStmtMock = $this->createMock(PDOStatement::class);
        $userStmtMock->method('execute')->willReturn(true);
        $userStmtMock->method('fetchColumn')->willReturn(123);

        $checkScheduleStmtMock = $this->createMock(PDOStatement::class);
        $checkScheduleStmtMock->method('execute')->willReturn(true);
        $checkScheduleStmtMock->method('fetch')->willReturn([
            'available_slots' => 5,
            'start_time' => (new DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            'status' => 'scheduled'
        ]);

        $checkBookingStmtMock = $this->createMock(PDOStatement::class);
        $checkBookingStmtMock->method('execute')->willReturn(true);
        $checkBookingStmtMock->method('fetch')->willReturn(['id' => 1]); // Запись уже существует

        $this->dbMock->method('prepare')
            ->willReturnMap([
                ["SELECT id FROM users WHERE telegram_id = ?", $userStmtMock],
                ["SELECT available_slots, start_time, status FROM workout_schedule WHERE id = ? FOR UPDATE", $checkScheduleStmtMock],
                ["SELECT id FROM user_workouts WHERE user_id = ? AND schedule_id = ? AND status = 'booked'", $checkBookingStmtMock],
            ]);

        $response = $this->workoutService->bookWorkout(1, 'test_tg_id');

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Вы уже записаны на эту тренировку', $response['error']);
    }
}