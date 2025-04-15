import psycopg2
import os
from dotenv import load_dotenv

load_dotenv()

def get_connection():
    """Подключение к БД"""
    return psycopg2.connect(
        dbname=os.getenv('DB_NAME'),
        user=os.getenv('DB_USER'),
        password=os.getenv('DB_PASS'),
        host=os.getenv('DB_HOST')
    )

def test_connection():
    """Проверка подключения"""
    try:
        conn = get_connection()
        print("✅ Успешное подключение к PostgreSQL!")
        conn.close()
    except Exception as e:
        print(f"❌ Ошибка: {e}")

# Добавленный блок:
if __name__ == "__main__":
    test_connection()
    input("Нажмите Enter для выхода...")