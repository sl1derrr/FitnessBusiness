import psycopg2
from dotenv import load_dotenv
import os

# Загрузка переменных окружения
load_dotenv()

# Параметры подключения из .env
DB_PARAMS = {
    "dbname": os.getenv("DB_NAME", "fitness_db"),
    "user": os.getenv("DB_USER", "fitness_user"),
    "password": os.getenv("DB_PASS", "password"),
    "host": os.getenv("DB_HOST", "localhost")
}

def create_tables():
    """Создает все таблицы в базе данных"""
    commands = [
        """
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            telegram_id BIGINT UNIQUE NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            username VARCHAR(100),
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )
        """,
        """
        CREATE TABLE IF NOT EXISTS daily_stats (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            date DATE NOT NULL,
            steps INTEGER DEFAULT 0,
            calories INTEGER DEFAULT 0,
            walking_minutes INTEGER DEFAULT 0,
            distance_km DECIMAL(5,2) DEFAULT 0,
            UNIQUE(user_id, date)
        )
        """,
        """
        CREATE TABLE IF NOT EXISTS workouts (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            duration_min INTEGER NOT NULL,
            max_participants INTEGER,
            is_active BOOLEAN DEFAULT TRUE
        )
        """,
        """
        CREATE TABLE IF NOT EXISTS workout_schedule (
            id SERIAL PRIMARY KEY,
            workout_id INTEGER REFERENCES workouts(id) ON DELETE CASCADE,
            trainer_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            start_time TIMESTAMP NOT NULL,
            end_time TIMESTAMP NOT NULL,
            available_slots INTEGER NOT NULL,
            status VARCHAR(20) DEFAULT 'scheduled' 
            CHECK (status IN ('scheduled', 'completed', 'cancelled'))
        )
        """,
        """
        CREATE TABLE IF NOT EXISTS user_workouts (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            schedule_id INTEGER REFERENCES workout_schedule(id) ON DELETE CASCADE,
            daily_activity_id INTEGER REFERENCES daily_stats(id) ON DELETE SET NULL,
            status VARCHAR(20) DEFAULT 'booked' 
            CHECK (status IN ('booked', 'attended', 'cancelled')),
            created_at TIMESTAMP DEFAULT NOW()
        )
        """,
        """
        CREATE INDEX IF NOT EXISTS idx_daily_stats_user_date 
        ON daily_stats(user_id, date)
        """,
        """
        CREATE INDEX IF NOT EXISTS idx_workout_schedule_time 
        ON workout_schedule(start_time)
        """,
        """
        CREATE INDEX IF NOT EXISTS idx_user_workouts_user 
        ON user_workouts(user_id)
        """
    ]

    try:
        # Подключение к базе данных
        with psycopg2.connect(**DB_PARAMS) as conn:
            with conn.cursor() as cur:
                # Выполнение каждой команды по очереди
                for command in commands:
                    cur.execute(command)
                print("✅ Все таблицы успешно созданы")
                
    except Exception as e:
        print(f"❌ Ошибка при создании таблиц: {e}")

if __name__ == "__main__":
    create_tables()
