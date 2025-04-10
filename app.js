// Инициализация Telegram WebApp
const tg = window.Telegram.WebApp;
tg.expand();

// Основная функция инициализации
async function initApp() {
    if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
        const user = tg.initDataUnsafe.user;
        updateUserAvatar(user);
    }
    setupApp();
}

function updateUserAvatar(user) {
    const userAvatarElement = document.getElementById('user-avatar');
    if (!userAvatarElement) return;
    
    if (user.photo_url) {
        userAvatarElement.style.backgroundImage = `url(${user.photo_url})`;
        userAvatarElement.style.backgroundSize = 'cover';
        userAvatarElement.textContent = '';
    } else {
        const firstNameLetter = user.first_name ? user.first_name.charAt(0) : '';
        const lastNameLetter = user.last_name ? user.last_name.charAt(0) : '';
        userAvatarElement.textContent = `${firstNameLetter}${lastNameLetter}`;
    }
}

function setupApp() {
    setupFixedNavigation();
    animateStepsProgress();
    setupChartSwitcher();
    setupTouchFeedback();
}

function setupFixedNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = this.id;
            
            // Обновляем активный пункт меню
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
            
            // Прокрутка к соответствующему разделу
            let targetElement;
            if (tabId === 'home-tab') {
                // Прокрутка к самому верху страницы
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            } else if (tabId === 'workout-tab') {
                targetElement = document.querySelector('.section-title');
            } else if (tabId === 'stats-tab') {
                targetElement = document.querySelector('.achievements');
            }
            
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Фиксируем меню и добавляем отступ
    const bottomNav = document.querySelector('.bottom-nav');
    if (bottomNav) {
        bottomNav.style.position = 'fixed';
        bottomNav.style.bottom = '0';
    }
    
    document.querySelector('.container').style.paddingBottom = '80px';
}

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
        displayedSteps += Math.ceil((currentSteps - displayedSteps) / 10);
        if (displayedSteps >= currentSteps) {
            displayedSteps = currentSteps;
            clearInterval(stepInterval);
        }
        
        stepCounter.textContent = displayedSteps.toLocaleString();
        
        const offset = circumference - (displayedSteps / maxSteps) * circumference;
        progressRing.style.strokeDashoffset = offset;
    }, 20);
}

function setupChartSwitcher() {
    const chartButtons = document.querySelectorAll('.chart-button');
    const chartBars = document.querySelectorAll('.chart-bar');
    
    const chartData = {
        steps: { values: [420, 350, 580, 490, 610, 320, 475], unit: '', maxValue: 1000 },
        calories: { values: [320, 280, 450, 380, 520, 250, 400], unit: '', maxValue: 600 },
        distance: { values: [3.2, 1, 0.3, 3.8, 5.2, 2.5, 4.0], unit: 'км', maxValue: 6 }
    };
    
    chartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const type = this.dataset.type;
            chartButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            updateChart(type, chartData[type]);
        });
    });
    
    updateChart('steps', chartData.steps);
}

function updateChart(type, data) {
    const chartBars = document.querySelectorAll('.chart-bar');
    
    chartBars.forEach((bar, index) => {
        bar.classList.remove('steps', 'calories', 'distance');
        bar.classList.add(type);
        
        const valueElement = bar.querySelector('.bar-value');
        if (valueElement) {
            valueElement.textContent = data.values[index] + (data.unit ? data.unit : '');
        }
        
        const percentage = (data.values[index] / data.maxValue) * 90 + 10;
        bar.style.height = `${percentage}%`;
    });
}

function setupTouchFeedback() {
    const interactiveElements = document.querySelectorAll('.card, .chart-button, .workout-card, .achievement, .challenge-button');
    
    interactiveElements.forEach(element => {
        element.addEventListener('touchstart', function() {
            this.classList.add('touch-active');
        });
        
        element.addEventListener('touchend', function() {
            this.classList.remove('touch-active');
        });
    });
}

// Инициализация приложения при загрузке DOM
document.addEventListener('DOMContentLoaded', initApp);
