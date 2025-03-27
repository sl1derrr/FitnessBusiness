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

const list = document.querySelectorAll('.list');
const contents = document.querySelectorAll('.content');

/* Лоудер */ 
document.addEventListener("DOMContentLoaded", function() { /* При загрузке*/

    setTimeout(function() {
        loader.classList.add("hidden");

        setTimeout(function() { /* Скрытие лоудера, появление контента */
            loader.style.display = "none";
            main.style.opacity = "1";
            main.style.visibility = "visible";
            main.home.page.visibility = "visible"
        }, 500); // Завершение анимации
    }, 2000);
});


// Функционал активного элемента меню

function activeLink() { 
    list.forEach(item => item.classList.remove('active'));
    this.classList.add('active');

    const contentId = this.querySelector('a').getAttribute('data-content');

    contents.forEach(content => content.classList.remove('show'));
    document.getElementById(contentId).classList.add('show');
}

list.forEach(item => item.addEventListener('click', activeLink));

// Спарк

document.body.addEventListener("click", (e) => {
    const sparkCount = 12; // Количество частиц
    for (let i = 0; i < sparkCount; i++) {
      const spark = document.createElement("div");
      spark.className = "spark";
      document.body.appendChild(spark);
  
      // Устанавливаем положение и направление
      const angle = (i * 360) / sparkCount;
      const dx = 50 * Math.cos((angle * Math.PI) / 180); // Смещение по X
      const dy = 50 * Math.sin((angle * Math.PI) / 180); // Смещение по Y
      spark.style.setProperty("--dx", `${dx}px`);
      spark.style.setProperty("--dy", `${dy}px`);
  
      // Устанавливаем начальные координаты частиц
      spark.style.left = `${e.pageX}px`;
      spark.style.top = `${e.pageY}px`;
  
      // Удаление частиц после завершения анимации
      spark.addEventListener("animationend", () => spark.remove());
    }
  });


