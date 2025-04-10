// Инициализация Telegram WebApp
const tg = window.Telegram.WebApp;
tg.expand(); // Растягиваем на весь экран

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
        
        // Обновляем аватар пользователя
        updateUserAvatar(user);
    } else {
        // Если данные пользователя недоступны
        console.log('Не удалось получить данные пользователя');
    }
    
    // Инициализация основной логики приложения
    setupApp();
}

// Функция обновления аватара пользователя
function updateUserAvatar(user) {
    const userAvatarElement = document.getElementById('user-avatar');
    if (!userAvatarElement) return;
    
    if (user.photo_url) {
        userAvatarElement.style.backgroundImage = `url(${user.photo_url})`;
        userAvatarElement.style.backgroundSize = 'cover';
        userAvatarElement.textContent = '';
    } else {
        // Используем инициалы, если фото нет
        const firstNameLetter = user.first_name ? user.first_name.charAt(0) : '';
        const lastNameLetter = user.last_name ? user.last_name.charAt(0) : '';
        userAvatarElement.textContent = `${firstNameLetter}${lastNameLetter}`;
    }
}

// Функция отправки данных на сервер
async function sendUserDataToServer(userData) {
    try {
        console.log('Данные пользователя для сохранения:', userData);
        // Здесь реализуйте отправку данных на ваш сервер
        // Пример:
        // const response = await fetch('https://your-api.com/save-user', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify(userData)
        // });
    } catch (error) {
        console.error('Ошибка при отправке данных:', error);
    }
}

// Настройка основного функционала приложения
function setupApp() {
    setupNavigation();
    animateStepsProgress();
    setupChartSwitcher();
    setupTouchFeedback();
}

// Анимация прогресса шагов
function animateStepsProgress() {
    const stepCounter = document.getElementById('step-counter');
    const progressRing = document.getElementById('progress-ring');
    
    if (!stepCounter || !progressRing) return;
    
    const maxSteps = 10000;
    const currentSteps = 7500;
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

// Настройка переключения между типами данных в графике
function setupChartSwitcher() {
    const chartButtons = document.querySelectorAll('.chart-button');
    const chartBars = document.querySelectorAll('.chart-bar');
    
    // Данные для разных типов графиков
    const chartData = {
        steps: {
            values: [420, 350, 580, 490, 610, 320, 475],
            unit: '',
            maxValue: 1000
        },
        calories: {
            values: [320, 280, 450, 380, 520, 250, 400],
            unit: '',
            maxValue: 600
        },
        distance: {
            values: [3.2, 1, 0.3, 3.8, 5.2, 2.5, 4.0],
            unit: 'км',
            maxValue: 6
        }
    };
    
    chartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const type = this.dataset.type;
            
            // Удаляем активный класс у всех кнопок
            chartButtons.forEach(btn => btn.classList.remove('active'));
            // Добавляем активный класс текущей кнопке
            this.classList.add('active');
            
            // Обновляем график
            updateChart(type, chartData[type]);
        });
    });
    
    // Инициализируем график с данными о шагах
    updateChart('steps', chartData.steps);
}

// Обновление графика в соответствии с выбранным типом данных
function updateChart(type, data) {
    const chartBars = document.querySelectorAll('.chart-bar');
    
    // Удаляем все классы типов и активный класс
    chartBars.forEach(bar => {
        bar.classList.remove('steps', 'calories', 'distance', 'active');
    });
    
    // Добавляем соответствующие классы и значения
    chartBars.forEach((bar, index) => {
        bar.classList.add(type);
        bar.classList.add('active');
        
        // Обновляем значение
        const valueElement = bar.querySelector('.bar-value');
        if (valueElement) {
            valueElement.textContent = data.values[index] + (data.unit ? data.unit : '');
        }
        
        // Обновляем высоту столбца (проценты от максимального значения)
        const percentage = (data.values[index] / data.maxValue) * 90 + 10; // 10-100%
        bar.style.height = `${percentage}%`;
    });
}

// Настройка визуальной обратной связи при касании
function setupTouchFeedback() {
    const interactiveElements = document.querySelectorAll(
        '.workout-card, .achievement, .chart-bar, .recommendation-action, .nav-item, .card'
    );
    
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

// Запускаем приложение при загрузке страницы
document.addEventListener('DOMContentLoaded', initApp);