// Инициализация Telegram WebApp

const tg = window.Telegram.WebApp;
tg.expand(); // Растягиваем на весь экран

// Элементы DOM
const userDataElement = document.getElementById('user-data');
const userNameElement = document.getElementById('user-name');
const userIdElement = document.getElementById('user-id');
const userAvatarElement = document.getElementById('user-avatar');
const authMessageElement = document.getElementById('auth-message');

// Основная функция инициализации
async function initApp() {
    // Проверяем, инициализированы ли данные пользователя
    if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
        const user = tg.initDataUnsafe.user;
        
        // Подготовка данных для отправки на сервер
        const userData = {
            tg_id: user.id,
            first_name: user.first_name,
            last_name: user.last_name,
            username: user.username,
            photo_url: user.photo_url,
            language_code: user.language_code,
            is_premium: user.is_premium || false
        };
        
        // Отправляем данные на сервер (реализуйте свою функцию)
        await sendUserDataToServer(userData);
        
    } else {
        // Если данные пользователя недоступны
        authMessageElement.textContent = 'Не удалось получить данные пользователя. Пожалуйста, откройте приложение через Telegram.';
    }
    
    // Инициализация основной логики приложения
    setupApp();
}

// Функция отправки данных на сервер
async function sendUserDataToServer(userData) {
    try {
        // Здесь реализуйте отправку данных на ваш сервер
        // Пример:
        // const response = await fetch('https://your-api.com/save-user', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify(userData)
        // });
        
        console.log('Данные пользователя для сохранения:', userData);
    } catch (error) {
        console.error('Ошибка при отправке данных:', error);
    }
}

// Настройка основной логики приложения
function setupApp() {
    // Здесь реализуйте основную логику вашего приложения
    console.log('Приложение инициализировано');
}

// Запускаем приложение при загрузке страницы
document.addEventListener('DOMContentLoaded', initApp);


