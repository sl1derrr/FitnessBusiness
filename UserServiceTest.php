<?php

// Файл: /var/www/fitnessbusinesstg.ru/tests/UserServiceTest.php

use PHPUnit\Framework\TestCase;
// Тестируемый класс
require_once '/var/www/fitnessbusinesstg.ru/src/UserService.php';

class UserServiceTest extends TestCase
{
    protected $dbMock;
    protected $userService;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(PDO::class);
        $this->userService = new UserService($this->dbMock);
    }

    /**
     * Модульный тест для UserService::generateDailyAdvice: высокая активность.
     */
    public function testGenerateDailyAdvice_highActivity()
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn([
            'total_steps' => 75000,
            'total_distance' => 60,
            'total_workout' => 200,
            'avg_sleep' => 450,
            'active_days' => 6,
            'workout_days' => 5,
            'max_steps' => 15000,
            'max_workout' => 90
        ]);

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $advice = $this->userService->generateDailyAdvice(1);
        $this->assertStringContainsString('очень активен', $advice['text']);
        $this->assertStringContainsString('активного отдыха или растяжки', $advice['text']);
        $this->assertEquals(75000, $advice['stats']['steps']);
    }

    /**
     * Модульный тест для UserService::generateDailyAdvice: низкая активность.
     */
    public function testGenerateDailyAdvice_lowActivity()
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn([
            'total_steps' => 10000,
            'total_distance' => 5,
            'total_workout' => 30,
            'avg_sleep' => 400,
            'active_days' => 1,
            'workout_days' => 0,
            'max_steps' => 5000,
            'max_workout' => 20
        ]);

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $advice = $this->userService->generateDailyAdvice(1);
        $this->assertStringContainsString('недостаточно активен', $advice['text']);
        $this->assertStringContainsString('добавить регулярные тренировки', $advice['text']);
        $this->assertEquals(10000, $advice['stats']['steps']);
    }

    /**
     * Модульный тест для UserService::generateDailyAdvice: нет данных активности.
     */
    public function testGenerateDailyAdvice_noActivityData()
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false); // Нет данных

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $advice = $this->userService->generateDailyAdvice(1);
        $this->assertStringContainsString('У нас пока нет данных о вашей активности', $advice['text']);
        $this->assertEquals(0, $advice['stats']['steps']);
    }
}