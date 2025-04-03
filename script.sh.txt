#!/bin/bash

# Обновление системы
sudo apt update && sudo apt upgrade -y

# Установка базовых компонентов
sudo apt install -y git nginx python3-pip python3-venv certbot python3-certbot-nginx

# Клонирование репозитория
git clone https://github.com/sl1derrr/Telegram-Mini-App.git /var/www/fitness-business-tg
cd /var/www/fitness-business-tg

# Создание Python окружения
python3 -m venv venv
source venv/bin/activate

# Настройка прав
sudo chown -R www-data:www-data /var/www/fitness-business-tg
sudo chmod -R 755 /var/www/fitness-business-tg

# Создание бэкенд-заглушки
cat > /var/www/fitness-business-tg/backend.py <<EOL
from flask import Flask
app = Flask(__name__)

@app.route('/')
def home():
    return "Backend is under construction"

if __name__ == '__main__':
    app.run()
EOL

# Настройка Nginx
sudo rm /etc/nginx/sites-enabled/default
sudo bash -c 'cat > /etc/nginx/sites-available/fitness-business-tg <<EOL
server {
    listen 80;
    server_name fitness-business-tg.ru www.fitness-business-tg.ru;

    root /var/www/fitness-business-tg;
    index index.html;

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location /api/ {
        proxy_pass http://localhost:5000;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
    }

    location /static/ {
        alias /var/www/fitness-business-tg/static/;
        expires 30d;
    }
}
EOL'

sudo ln -s /etc/nginx/sites-available/fitness-business-tg /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx

# Настройка SSL
sudo certbot --nginx -d fitness-business-tg.ru -d www.fitness-business-tg.ru --non-interactive --agree-tos -m maksmakarov331@gmail.com

# Настройка службы для бэкенда
sudo bash -c 'cat > /etc/systemd/system/fitness-backend.service <<EOL
[Unit]
Description=FitnessBusiness TG Backend
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/fitness-business-tg
Environment="PATH=/var/www/fitness-business-tg/venv/bin"
ExecStart=/var/www/fitness-business-tg/venv/bin/python /var/www/fitness-business-tg/backend.py

[Install]
WantedBy=multi-user.target
EOL'

sudo systemctl daemon-reload
sudo systemctl enable fitness-backend
sudo systemctl start fitness-backend

echo "Установка завершена! Сайт доступен по https://fitness-business-tg.ru"