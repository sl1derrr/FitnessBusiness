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

// Запускаем приложение при загрузке страницы
document.addEventListener('DOMContentLoaded', initApp);

const list = document.querySelectorAll('.list');
const contents = document.querySelectorAll('.content');

document.addEventListener('DOMContentLoaded', function() {
    animateStepsProgress();
    setupTouchFeedback();
  });

document.addEventListener('DOMContentLoaded', function() {
  // Получаем все элементы меню
  const navItems = document.querySelectorAll('.nav-item');
    
  // Получаем все контенты вкладок
  const tabContents = document.querySelectorAll('.tab-content');
    
  // Обработчик клика для каждой кнопки меню
  navItems.forEach(item => {
    item.addEventListener('click', function() {
      // Удаляем активный класс у всех кнопок
      navItems.forEach(navItem => {
        navItem.classList.remove('active');
      });
        
      // Добавляем активный класс текущей кнопке
      this.classList.add('active');
        
      // Скрываем все вкладки
      tabContents.forEach(content => {
        content.classList.remove('active');
      });
        
      // Показываем соответствующую вкладку
      const tabId = this.id.replace('-tab', '-content');
      document.getElementById(tabId)?.classList.add('active');
    });
  });
});

  function animateStepsProgress() {
    const stepCounter = document.getElementById('step-counter');
    const progressRing = document.getElementById('progress-ring');
  
    if (!stepCounter || !progressRing) return;
  
    const maxSteps = 10000;
    const currentSteps = 6916;
    let displayedSteps = 0;
  
    const circumference = 2 * Math.PI * 88;
    progressRing.style.strokeDasharray = circumference;
    progressRing.style.strokeDashoffset = circumference;
  
    const stepInterval = setInterval(() => {
      displayedSteps += Math.ceil(currentSteps / 60);
      if (displayedSteps >= currentSteps) {
        displayedSteps = currentSteps;
        clearInterval(stepInterval);
      }
  
      stepCounter.textContent = displayedSteps.toLocaleString();
  
      const offset = circumference - (displayedSteps / maxSteps) * circumference;
      progressRing.style.strokeDashoffset = offset;
    }, 20);
  }
  
  function setupTouchFeedback() {
    const interactiveElements = document.querySelectorAll('.workout-card, .achievement, .chart-bar, .recommendation-action, .nav-item');
  
    interactiveElements.forEach(element => {
      element.addEventListener('touchstart', () => {
        element.classList.add('touch-active');
      });
  
      ['touchend', 'touchcancel'].forEach(event => {
        element.addEventListener(event, () => {
          element.classList.remove('touch-active');
        });
      });
    });
  }


