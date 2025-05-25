// Инициализация Telegram WebApp
const tg = window.Telegram?.WebApp;
if (tg) {
    tg.expand();
} else {
    console.warn('Telegram WebApp not detected - running in standalone mode');
}

// Конфигурация приложения
const APP_CONFIG = {
    stepGoal: 10000,
    chartData: {
        steps: { maxValue: 20000, unit: '' },
        calories: { maxValue: 600, unit: '' },
        distance: { maxValue: 6, unit: 'км' }
    }
};

// Основная функция инициализации
document.addEventListener('DOMContentLoaded', function() {
    initApp();
});

async function initApp() {
    try {
        await getTelegramId();
        setupApp();
        setupAdvice();
        setupWorkout();

        if (tg?.initDataUnsafe?.user){
            updateUserAvatar(tg.initDataUnsafe.user);
        }
        // Инициализация прогресса шагов
        animateStepsProgress();
        // Инициализация переключения графиков
        updateChart('steps');
    } catch (error) {
        console.error('Initialization error:', error);
    }
}

function updateUserAvatar(user) {
    const userAvatarElement = document.getElementById('user-avatar');
    if (!userAvatarElement) return;

    if (user.photo_url) {
        userAvatarElement.style.backgroundImage = `url(${user.photo_url})`;
        userAvatarElement.style.backgroundSize = 'cover';
        userAvatarElement.textContent = '';
    } else {
        const firstNameLetter = user.first_name?.charAt(0) || '';
        const lastNameLetter = user.last_name?.charAt(0) || '';
        userAvatarElement.textContent = `${firstNameLetter}${lastNameLetter}`;
    }
}



// Функция для анимации заполнения круга шагов
function animateStepsProgress() {
    const stepCounter = document.getElementById('step-counter');
    const progressRing = document.getElementById('progress-ring');

    if (!stepCounter || !progressRing) return;

    const currentSteps = parseInt(stepCounter.textContent.replace(/\s/g, '')) || 0;
    const goalSteps = APP_CONFIG.stepGoal;
    const circumference = 552; // 2 * π * 88 ≈ 552

    // Форматируем число с пробелами
    stepCounter.textContent = currentSteps.toLocaleString('ru-RU');

    // Вычисляем прогресс (не более 100%)
    const progress = Math.min(currentSteps / goalSteps, 1);
    const dashValue = progress * circumference;

    // Сбрасываем анимацию
    progressRing.style.transition = 'none';
    progressRing.style.strokeDasharray = '0 552';

    // Запускаем анимацию после небольшой задержки
    setTimeout(() => {
        progressRing.style.transition = 'stroke-dasharray 1s ease-out';
        progressRing.style.strokeDasharray = `${dashValue} 552`;
    }, 50);

    console.log(`Animating steps: ${currentSteps}, dashValue: ${dashValue}`);
}


// Остальные функции остаются без изменений
async function getTelegramId() {
    const urlParams = new URLSearchParams(window.location.search);
    let tgId = urlParams.get('tg_id');

    if (!tgId && typeof Telegram !== 'undefined' && Telegram.WebApp.initData) {
        try {
            const initData = new URLSearchParams(Telegram.WebApp.initData);
            const user = JSON.parse(initData.get('user'));
            if (user && user.id) {
                tgId = user.id;
                if (!urlParams.has('tg_id')) {
                    urlParams.set('tg_id', tgId);
                    window.history.replaceState({}, '', `${window.location.pathname}?${urlParams.toString()}`);
                }
            }
        } catch (e) {
            console.error('Error parsing initData:', e);
        }
    }

    if (!tgId) {
        tgId = getCookie('tg_id');
    }

    if (!tgId) {
        console.error('Не удалось получить Telegram ID');
        return;
    }

    setCookie('tg_id', tgId, 30);
    document.getElementById('tgIdDisplay').textContent = `Telegram ID: ${tgId}`;
    return tgId;
}

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
}

function setCookie(name, value, days) {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    const expires = `expires=${date.toUTCString()}`;
    document.cookie = `${name}=${value}; ${expires}; path=/`;
}

function setupApp() {
    setupFixedNavigation();
    setupChartSwitcher();
    setupTouchFeedback();
}

function setupFixedNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    const bottomNav = document.querySelector('.bottom-nav');
    const container = document.querySelector('.container');

    if (!navItems.length || !bottomNav || !container) return;

    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');

            const tabId = this.id;
            const scrollTarget = {
                'home-tab': { top: 0 },
                'workout-tab': { selector: '.section-title' },
                'stats-tab': { selector: '.achievements' }
            }[tabId];

            if (scrollTarget) {
                if (scrollTarget.selector) {
                    const targetElement = document.querySelector(scrollTarget.selector);
                    targetElement?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }
        });
    });

    bottomNav.style.position = 'fixed';
    bottomNav.style.bottom = '0';
    container.style.paddingBottom = '80px';
}

function setupChartSwitcher() {
    const chartButtons = document.querySelectorAll('.chart-button');
    if (!chartButtons.length) return;

    chartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const type = this.dataset.type;
            if (!type) return;

            chartButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            updateChart(type);
        });
    });
}

function updateChart(type) {
    const chartBars = document.querySelectorAll('.chart-bar');
    if (!chartBars.length) return;

    const config = APP_CONFIG.chartData[type];
    if (!config) return;

    chartBars.forEach(bar => {
        bar.classList.remove('steps', 'calories', 'distance');
        bar.classList.add(type);

        const valueElement = bar.querySelector('.bar-value');
        if (valueElement) {
            const value = bar.dataset[type] || 0;
            valueElement.textContent = config.unit ? `${value}${config.unit}` : value;
        }

        const value = parseFloat(bar.dataset[type]) || 0;
        const percentage = (value / config.maxValue) * 90 + 10;
        bar.style.height = `${Math.min(percentage, 100)}%`;
    });
}

function setupTouchFeedback() {
    const interactiveElements = document.querySelectorAll(
        '.card, .chart-button, .workout-card, .achievement, .challenge-button'
    );

    interactiveElements.forEach(element => {
        element.addEventListener('touchstart', () => element.classList.add('touch-active'));
        element.addEventListener('touchend', () => element.classList.remove('touch-active'));
        element.addEventListener('touchcancel', () => element.classList.remove('touch-active'));
    });
}

function setupAdvice() {
    const adviceButton = document.querySelector('.advice-button');
    if (!adviceButton) return;

    adviceButton.addEventListener('click', function() {
        const recommendationCard = document.querySelector('.recommendation-card');
        if (!recommendationCard) return;

        const steps = recommendationCard.dataset.steps || 0;
        const distance = recommendationCard.dataset.distance || 0;
        const workout = recommendationCard.dataset.workout || 0;
        const sleep = recommendationCard.dataset.sleep || 0;

        const modalHTML = `
            <div class="modal-overlay">
                <div class="advice-modal">
                    <div class="advice-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#29fd53">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <div class="advice-title">Ваша недельная статистика</div>
                    <div class="advice-stats">
                        <div class="stat-item">
                            <div class="stat-label">Шаги</div>
                            <div class="stat-value">${Math.round(steps).toLocaleString('ru-RU')}</div>
                            <div class="progress-bar">
                                <div class="progress" style="width: ${Math.min(steps / 70000 * 100, 100)}%;"></div>
                            </div>
                            <div class="stat-status">Норма: 70,000</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Дистанция</div>
                            <div class="stat-value">${parseFloat(distance).toFixed(1)} км</div>
                            <div class="progress-bar">
                                <div class="progress" style="width: ${Math.min(distance / 42 * 100, 100)}%;"></div>
                            </div>
                            <div class="stat-status">Норма: 42 км</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Время активности</div>
                            <div class="stat-value">${Math.round(workout)} мин</div>
                            <div class="progress-bar">
                                <div class="progress" style="width: ${Math.min(workout / 300 * 100, 100)}%;"></div>
                            </div>
                            <div class="stat-status">Норма: 300 мин</div>
                        </div>
                    </div>
                    <button class="modal-close">Закрыть</button>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        const modal = document.querySelector('.modal-overlay');
        const closeButton = document.querySelector('.modal-close');

        setTimeout(() => {
            modal.classList.add('active');
        }, 10);

        closeButton.addEventListener('click', () => {
            modal.remove();
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    });
}

// Модальное окно для тренировок

// Функция для настройки модального окна тренировок
function setupWorkout() {
    const workoutCards = document.querySelectorAll('.workout-card');
    if (!workoutCards.length) return;

    workoutCards.forEach(card => {
        card.addEventListener('click', async function() {
            const workoutName = this.querySelector('.workout-name');
            if (!workoutName || workoutName.textContent === 'Нет тренировок на сегодня') return;

            const scheduleId = this.dataset.scheduleId;
            if (!scheduleId) return;

            try {
                // Показываем индикатор загрузки
                this.classList.add('loading');

                const response = await fetch(`?get_workout_details=1&schedule_id=${scheduleId}`);
                if (!response.ok) throw new Error('Ошибка загрузки данных');

                const workoutData = await response.json();

                // Создаем модальное окно
                createWorkoutModal(workoutData, scheduleId);

            } catch (error) {
                console.error('Error loading workout details:', error);
                alert('Не удалось загрузить информацию о тренировке');
            } finally {
                const card = this;
                setTimeout(() => card.classList.remove('loading'), 500);
            }
        });
    });
}

function createWorkoutModal(workoutData, scheduleId) {
    const now = new Date();
    const startTime = new Date(workoutData.start_time);
    const isWorkoutStarted = startTime <= now;
    const isBooked = workoutData.is_booked; // Предполагаем, что сервер передает флаг, записан ли пользователь

    let modalButtonsHTML = '';
    if (isWorkoutStarted) {
        modalButtonsHTML = `<div class="booking-closed">Тренировка уже началась</div>`;
    } else if (isBooked) {
        modalButtonsHTML = `<div class="booking-closed">Вы уже записаны</div>`;
    } else if (workoutData.available_slots > 0) {
        modalButtonsHTML = `<button class="modal-btn signup-btn" data-schedule-id="${scheduleId}" data-start-time="${workoutData.start_time}">Записаться</button>`;
    } else {
        modalButtonsHTML = `<div class="booking-closed">Нет свободных мест</div>`;
    }

    const modalHTML = `
        <div class="modal-overlay">
            <div class="advice-modal">
                <div class="advice-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#29fd53">
                        <path d="M4 17l6-6-4-4 6-6m4 18v-8m4 4h-8"/>
                    </svg>
                </div>
                <div class="advice-title">${workoutData.workout.name}</div>
                <div class="advice-stats">
                    <div class="stat-item">
                        <div class="stat-label">Тренер</div>
                        <div class="stat-value">${workoutData.trainer.first_name} ${workoutData.trainer.last_name}</div>
                        ${workoutData.trainer.specialization ?
                          `<div class="stat-status">${workoutData.trainer.specialization}</div>` : ''}
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Время тренировки</div>
                        <div class="stat-value">${workoutData.duration} мин</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Время проведения</div>
                        <div class="stat-value">${workoutData.time}</div>
                        ${isWorkoutStarted ? `<div class="stat-status">Тренировка началась в ${startTime.toLocaleTimeString()}</div>` : ''}
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Доступные места</div>
                        <div class="stat-value">${workoutData.available_slots} из ${workoutData.max_participants || '∞'}</div>
                        <div class="progress-bar">
                            <div class="progress" style="width: ${workoutData.max_participants ?
                              (workoutData.available_slots / workoutData.max_participants * 100) : 100}%;"></div>
                        </div>
                    </div>
                    ${workoutData.workout.description ? `
                    <div class="stat-item">
                        <div class="stat-label">Описание</div>
                        <div class="stat-text">${workoutData.workout.description}</div>
                    </div>
                    ` : ''}
                </div>
                <div class="modal-buttons">
                    ${modalButtonsHTML}
                    <button class="modal-btn modal-close">Закрыть</button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    if (!isWorkoutStarted && !isBooked && workoutData.available_slots > 0) {
        setupModalEvents();
    } else {
        setupModalCloseOnly();
    }
}

function getStatusText(status) {
    const statusMap = {
        'scheduled': 'Запланирована',
        'completed': 'Завершена',
        'cancelled': 'Отменена'
    };
    return statusMap[status] || status;
}

function getStatusDescription(status) {
    const descriptionMap = {
        'completed': 'Тренировка уже прошла',
        'cancelled': 'Тренировка отменена'
    };
    return descriptionMap[status] || '';
}

function setupModalCloseOnly() {
    const modal = document.querySelector('.modal-overlay');
    const closeButton = document.querySelector('.modal-close');

    setTimeout(() => {
        modal.classList.add('active');
    }, 10);

    closeButton.addEventListener('click', () => {
        modal.remove();
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}


function setupModalEvents() {
    const modal = document.querySelector('.modal-overlay');
    const closeButton = document.querySelector('.modal-close');
    const signupButton = document.querySelector('.signup-btn');

    setTimeout(() => {
        modal.classList.add('active');
    }, 10);

    closeButton.addEventListener('click', () => {
        modal.remove();
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });

    signupButton?.addEventListener('click', async () => {
        const scheduleId = signupButton.dataset.scheduleId;
        const startTime = signupButton.dataset.startTime; // Получаем время начала
        const now = new Date();
        let startTimeDate;

        // Попытаемся распарсить время начала. Подстрой формат под свой!
        try {
            startTimeDate = new Date(startTime);
        } catch (error) {
            console.error('Ошибка парсинга времени начала:', error);
            alert('Произошла ошибка при проверке времени тренировки');
            return;
        }

        // Проверяем, прошла ли уже тренировка (сравниваем с текущим временем)
        if (startTimeDate <= now) {
            alert('Извините, эта тренировка уже началась.');
            return;
        }

        const tgId = await getTelegramId();

        if (!scheduleId || !tgId) {
            alert('Ошибка: не удалось получить необходимые данные');
            return;
        }

        try {
            signupButton.disabled = true;
            signupButton.textContent = 'Записываем...';

            const response = await fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    book_workout: '1',
                    schedule_id: scheduleId,
                    tg_id: tgId
                })
            });

            const result = await response.json();

            if (result.error) {
                throw new Error(result.error);
            }

            // Обновляем UI
            const availableSlotsElement = modal.querySelector('.stat-value:last-child');
            if (availableSlotsElement) {

                // Обновляем прогресс-бар
                const progressBar = modal.querySelector('.progress-bar .progress');
                if (progressBar) {
                    const maxParticipants = parseInt(availableSlotsElement.textContent.split(' из ')[1]) || 1;
                    progressBar.style.width = `${(result.available_slots / maxParticipants) * 100}%`;
                }
            }

            signupButton.textContent = 'Записано!';
            signupButton.classList.add('booked');

            setTimeout(() => {
                modal.remove();
            }, 2000);

        } catch (error) {
            alert(error.message);
            signupButton.disabled = false;
            signupButton.textContent = 'Записаться';
        }
    });
}