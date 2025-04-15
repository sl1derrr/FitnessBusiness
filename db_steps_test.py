import psycopg2
from datetime import date

def debug_daily_stats(user_id):
    try:
        conn = psycopg2.connect(**DB_PARAMS)
        cur = conn.cursor()
        
        # 1. Проверяем существование пользователя
        cur.execute("SELECT id FROM users WHERE id = %s", (user_id,))
        user_exists = cur.fetchone()
        print(f"1. Пользователь существует: {'Да' if user_exists else 'Нет'}")
        
        if not user_exists:
            return

        # 2. Проверяем существующие данные
        test_date = date(2023, 11, 1)
        cur.execute("""
            SELECT steps FROM daily_stats 
            WHERE user_id = %s AND date = %s
        """, (user_id, test_date))
        existing = cur.fetchone()
        print(f"2. Существующая запись: {existing}")
        
        # 3. Пробуем вставить данные
        insert_sql = """
            INSERT INTO daily_stats 
            (user_id, date, steps, calories, walking_minutes, distance_km)
            VALUES (%s, %s, %s, %s, %s, %s)
            ON CONFLICT (user_id, date) DO UPDATE SET
                steps = EXCLUDED.steps
            RETURNING id
        """
        cur.execute(insert_sql, 
            (user_id, test_date, 15000, 500, 90, 10.5))
        
        print(f"3. Затронуто строк: {cur.rowcount}")
        print(f"4. Возвращенный ID: {cur.fetchone()}")
        
        conn.commit()
        
        # 4. Проверяем результат
        cur.execute("SELECT steps FROM daily_stats WHERE user_id = %s", (user_id,))
        print(f"5. Текущие данные: {cur.fetchall()}")
        
    except Exception as e:
        print(f"Ошибка: {e}")
        conn.rollback()
    finally:
        conn.close()

# Использование:
debug_daily_stats(1)  # Подставьте реальный user_id
