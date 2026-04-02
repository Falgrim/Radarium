# План внедрения Docker Compose для Radarium

**Статус:** Отложено (требует обоснования)  
**Приоритет:** P2 (Средний)  
**Дата создания:** 2025-06-18

---

## 📋 Обоснование внедрения Docker Compose

### Текущая ситуация

Проект Radarium развёрнут на сервере с прямой установкой зависимостей:
- PHP 8.2 + Laravel 11
- MariaDB 10.11
- Redis (опционально)
- Ollama + Qwen2.5
- MadelineProto для Telegram API

**Проблемы текущего подхода:**

1. **Сложность развёртывания**
   - Ручная установка всех зависимостей
   - Конфликты версий PHP расширений
   - Различия между dev/staging/production окружениями

2. **Воспроизводимость окружения**
   - "Работает на моей машине" проблема
   - Сложности при onboarding новых разработчиков
   - Дрейф конфигураций между серверами

3. **Масштабирование**
   - Трудности горизонтального масштабирования парсеров
   - Сложности с балансировкой нагрузки
   - Отсутствие изоляции сервисов

4. **Резервное копирование и миграция**
   - Нет единой точки бэкапа
   - Сложности переноса на другой сервер
   - Зависимость от конкретной ОС и её версии

### Преимущества Docker Compose

| Преимущество | Описание | Выгода для Radarium |
|-------------|----------|---------------------|
| **Изоляция** | Каждый сервис в отдельном контейнере | Конфликты зависимостей исключены |
| **Портативность** | Одинаковое окружение везде | Dev = Staging = Production |
| **Масштабирование** | Легко запустить N инстансов | Параллельные парсеры без конфликтов |
| **Управление** | Единая точка управления | `docker-compose up/down/restart` |
| **Бэкап** | Тома данных легко бэкапить | Простое резервное копирование БД |
| **CI/CD** | Интеграция с GitHub Actions | Автоматическое тестирование и деплой |

---

## 🏗️ Архитектура Docker Compose

### Диаграмма компонентов

```
┌─────────────────────────────────────────────────────────┐
│                    Docker Compose                        │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │    App       │  │     DB       │  │    Redis     │  │
│  │  (PHP/Laravel)│  │  (MariaDB)   │  │   (Cache)    │  │
│  │   Port: 80   │  │   Port: 3306 │  │   Port: 6379 │  │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  │
│         │                  │                  │          │
│         └──────────────────┼──────────────────┘          │
│                            │                              │
│  ┌──────────────┐  ┌──────▼───────┐  ┌──────────────┐  │
│  │   Ollama     │  │    Nginx     │  │  E5 API      │  │
│  │   (AI)       │  │   (Proxy)    │  │  (Future)    │  │
│  │ Port: 11434  │  │   Port: 443  │  │  Port: 8000  │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 📝 Пошаговый план внедрения

### Этап 1: Подготовка (2 часа)

#### Шаг 1.1: Анализ текущих зависимостей

```bash
# Проверить версию PHP
php -v

# Проверить расширения
php -m | grep -E 'pdo|mbstring|xml|curl|redis'

# Проверить версию MariaDB
mysql --version

# Проверить Redis
redis-cli --version

# Проверить Ollama
ollama --version
```

#### Шаг 1.2: Создание структуры проекта

```bash
cd /workspace

# Создать директорию для Docker конфигурации
mkdir -p docker/{nginx,php,mysql,redis}

# Создать файлы конфигурации
touch docker-compose.yml
touch docker/php/Dockerfile
touch docker/nginx/default.conf
touch docker/mysql/init.sql
touch .dockerignore
```

---

### Этап 2: Создание Dockerfile для PHP (3 часа)

#### Шаг 2.1: Базовый Dockerfile

**Файл:** `docker/php/Dockerfile`

```dockerfile
FROM php:8.2-fpm-alpine

# Установить системные зависимости
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    redis \
    pecl \
    icu-dev \
    g++ \
    make

# Очистить кэш
RUN rm -rf /var/cache/apk/*

# Установить PHP расширения
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Установить Redis расширение
RUN pecl install redis && docker-php-ext-enable redis

# Установить Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Создать пользователя для Laravel
RUN addgroup -g 1000 laravel && adduser -G laravel -g laravel -s /bin/sh -D laravel

# Установить рабочие директории
WORKDIR /var/www/html

# Копировать существующий composer.json
COPY composer.* ./

# Установить зависимости Laravel
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Копировать файлы приложения
COPY --chown=laravel:laravel . .

# Установить права доступа
RUN chown -R laravel:laravel \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

# Сделать скрипты исполняемыми
RUN chmod +x artisan

# Переключиться на пользователя laravel
USER laravel

# Expose port 9000 для PHP-FPM
EXPOSE 9000

# Запустить PHP-FPM
CMD ["php-fpm"]
```

#### Шаг 2.2: Создание .dockerignore

**Файл:** `.dockerignore`

```dockerignore
node_modules
npm-debug.log
vendor
storage/*.log
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
bootstrap/cache/*
.htaccess
.env
.env.local
.env.*.local
.git
.gitignore
README.md
composer.lock
.phpunit.result.cache
.phpactor.json
.idea
.vscode
*.swp
*.swo
*~
.DS_Store
Thumbs.db
```

---

### Этап 3: Настройка Nginx (1 час)

#### Шаг 3.1: Конфигурация Nginx

**Файл:** `docker/nginx/default.conf`

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;

    client_max_body_size 50M;
    client_body_timeout 300s;
    client_header_timeout 300s;
    send_timeout 300s;
    proxy_read_timeout 300s;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        
        fastcgi_read_timeout 300s;
        fastcgi_send_timeout 300s;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    # Disable access to sensitive files
    location ~ /\.env {
        deny all;
    }

    # Logging
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;
}
```

#### Шаг 3.2: Dockerfile для Nginx

**Файл:** `docker/nginx/Dockerfile`

```dockerfile
FROM nginx:alpine

# Копировать конфигурацию
COPY default.conf /etc/nginx/conf.d/default.conf

# Создать директорию для логов
RUN mkdir -p /var/log/nginx

# Expose port 80
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
```

---

### Этап 4: Настройка базы данных (1 час)

#### Шаг 4.1: Инициализация MySQL

**Файл:** `docker/mysql/init.sql`

```sql
-- Создать базу данных
CREATE DATABASE IF NOT EXISTS radarium 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Создать пользователя
CREATE USER IF NOT EXISTS 'radarium'@'%' IDENTIFIED BY 'radarium_password';

-- Предоставить права
GRANT ALL PRIVILEGES ON radarium.* TO 'radarium'@'%';
FLUSH PRIVILEGES;

-- Оптимизировать для Laravel
SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));
```

#### Шаг 4.2: Конфигурация MySQL

**Файл:** `docker/mysql/my.cnf`

```ini
[mysqld]
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
innodb_buffer_pool_size=256M
innodb_log_file_size=64M
max_connections=200
wait_timeout=300
interactive_timeout=300

[client]
default-character-set=utf8mb4
```

---

### Этап 5: Создание docker-compose.yml (3 часа)

**Файл:** `docker-compose.yml`

```yaml
version: '3.8'

services:
  # PHP-FPM Service
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: radarium_php
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html:delegated
      - ./storage:/var/www/html/storage
    depends_on:
      - mysql
      - redis
    networks:
      - radarium_network
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
      - DB_HOST=mysql
      - DB_PORT=3306
      - DB_DATABASE=radarium
      - DB_USERNAME=radarium
      - DB_PASSWORD=radarium_password
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - OLLAMA_HOST=http://ollama:11434
    extra_hosts:
      - "host.docker.internal:host-gateway"

  # Nginx Service
  nginx:
    build:
      context: ./docker/nginx
      dockerfile: Dockerfile
    container_name: radarium_nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www/html:delegated
      - ./docker/nginx/logs:/var/log/nginx
    depends_on:
      - php
    networks:
      - radarium_network

  # MariaDB Service
  mysql:
    image: mariadb:10.11
    container_name: radarium_mysql
    restart: unless-stopped
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/01_init.sql
      - ./docker/mysql/my.cnf:/etc/mysql/conf.d/custom.cnf
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: radarium
      MYSQL_USER: radarium
      MYSQL_PASSWORD: radarium_password
    networks:
      - radarium_network
    command: --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci

  # Redis Service
  redis:
    image: redis:alpine
    container_name: radarium_redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    command: redis-server --appendonly yes
    networks:
      - radarium_network

  # Ollama Service (AI)
  ollama:
    image: ollama/ollama:latest
    container_name: radarium_ollama
    restart: unless-stopped
    ports:
      - "11434:11434"
    volumes:
      - ollama_data:/root/.ollama
    networks:
      - radarium_network
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              count: all
              capabilities: [gpu]
    # Для CPU-only версии убрать секцию deploy
    # и использовать: ollama/ollama:latest

  # Laravel Scheduler (Cron)
  scheduler:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: radarium_scheduler
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html:delegated
    depends_on:
      - mysql
      - redis
    networks:
      - radarium_network
    command: >
      sh -c "while true; do
        php artisan schedule:run --verbose --force;
        sleep 60;
      done"

  # Laravel Queue Worker
  queue:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: radarium_queue
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html:delegated
    depends_on:
      - mysql
      - redis
    networks:
      - radarium_network
    command: php artisan queue:work --tries=3 --timeout=300

  # E5 API Server (Future)
  e5-api:
    build:
      context: ./e5-api-server
      dockerfile: Dockerfile
    container_name: radarium_e5_api
    restart: unless-stopped
    ports:
      - "8000:8000"
    volumes:
      - e5_models:/app/models
    networks:
      - radarium_network
    profiles:
      - e5  # Запускать только с флагом --profile e5
    deploy:
      resources:
        limits:
          memory: 4G

networks:
  radarium_network:
    driver: bridge

volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local
  ollama_data:
    driver: local
  e5_models:
    driver: local
```

---

### Этап 6: Создание вспомогательных скриптов (2 часа)

#### Шаг 6.1: Makefile для упрощения работы

**Файл:** `Makefile`

```makefile
.PHONY: help up down restart logs shell test fresh migrate seed

# Цвета для вывода
GREEN := \033[0;32m
NC := \033[0m # No Color

help: ## Показать эту справку
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-20s$(NC) %s\n", $$1, $$2}'

up: ## Запустить все контейнеры
	docker-compose up -d
	@echo "$(GREEN)Контейнеры запущены!$(NC)"
	@echo "Доступно по адресу: http://localhost"

down: ## Остановить все контейнеры
	docker-compose down

restart: ## Перезапустить все контейнеры
	docker-compose restart

logs: ## Показать логи всех контейнеров
	docker-compose logs -f

logs-app: ## Показать логи PHP приложения
	docker-compose logs -f php

logs-db: ## Показать логи базы данных
	docker-compose logs -f mysql

logs-ollama: ## Показать логи Ollama
	docker-compose logs -f ollama

shell: ## Войти в контейнер PHP
	docker-compose exec php sh

shell-root: ## Войти в контейнер PHP как root
	docker-compose exec --user root php sh

shell-db: ## Войти в контейнер MySQL
	docker-compose exec mysql sh

test: ## Запустить тесты
	docker-compose exec php php artisan test

test-coverage: ## Запустить тесты с покрытием
	docker-compose exec php php artisan test --coverage

fresh: ## Пересоздать базу данных и запустить миграции
	docker-compose exec php php artisan migrate:fresh --seed

migrate: ## Запустить миграции
	docker-compose exec php php artisan migrate

seed: ## Выполнить сидеры
	docker-compose exec php php artisan db:seed

install: ## Установить зависимости
	docker-compose exec php composer install
	docker-compose exec php npm install && npm run build

clear: ## Очистить кэши
	docker-compose exec php php artisan cache:clear
	docker-compose exec php php artisan config:clear
	docker-compose exec php php artisan route:clear
	docker-compose exec php php artisan view:clear

backup: ## Создать бэкап базы данных
	docker-compose exec mysql mysqldump -uradarium -pradium_password radarium > backup_$$(date +%Y%m%d_%H%M%S).sql

restore: ## Восстановить базу данных из бэкапа
	@read -p "Введите имя файла бэкапа: " file; \
	docker-compose exec -T mysql mysql -uradarium -pradium_password radarium < $$file

ollama-pull: ## Загрузить модель в Ollama
	docker-compose exec ollama ollama pull qwen2.5:7b-instruct-q4_K_M

ollama-list: ## Показать загруженные модели
	docker-compose exec ollama ollama list

build: ## Пересобрать контейнеры
	docker-compose build --no-cache

ps: ## Показать статус контейнеров
	docker-compose ps

stats: ## Показать использование ресурсов
	docker stats --no-stream

prune: ## Очистить неиспользуемые ресурсы Docker
	docker system prune -a --volumes

# Development команды
dev: up ## Запустить в режиме разработки
	@echo "$(GREEN)Dev режим запущен!$(NC)"

prod: ## Запустить в production режиме
	docker-compose -f docker-compose.prod.yml up -d
```

#### Шаг 6.2: Скрипт первоначальной настройки

**Файл:** `docker/setup.sh`

```bash
#!/bin/bash

set -e

echo "🚀 Настройка Docker окружения для Radarium..."

# Проверить наличие Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker не найден. Пожалуйста, установите Docker."
    exit 1
fi

# Проверить наличие Docker Compose
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose не найден. Пожалуйста, установите Docker Compose."
    exit 1
fi

echo "✅ Docker и Docker Compose найдены"

# Создать .env из .env.example если не существует
if [ ! -f .env ]; then
    echo "📝 Создание .env файла..."
    cp .env.example .env
    echo "✅ .env файл создан"
else
    echo "ℹ️  .env файл уже существует"
fi

# Сгенерировать APP_KEY если не установлен
if grep -q "APP_KEY=$" .env || ! grep -q "APP_KEY=" .env; then
    echo "🔑 Генерация APP_KEY..."
    docker-compose run --rm php php artisan key:generate --show
    read -p "Вставьте сгенерированный ключ в .env файл и нажмите Enter..."
fi

# Запустить контейнеры
echo "🐳 Запуск контейнеров..."
docker-compose up -d

# Подождать пока MySQL будет готов
echo "⏳ Ожидание готовности MySQL..."
sleep 10

# Запустить миграции
echo "📊 Запуск миграций..."
docker-compose exec -T php php artisan migrate --force

# Очистить кэши
echo "🧹 Очистка кэшей..."
docker-compose exec -T php php artisan optimize:clear

# Загрузить модель Ollama
echo "🤖 Загрузка модели Ollama..."
docker-compose exec -T ollama ollama pull qwen2.5:7b-instruct-q4_K_M

echo ""
echo "✅ Настройка завершена!"
echo ""
echo "📍 Приложение доступно по адресу: http://localhost"
echo "📊 MoonShine админка: http://localhost/admin"
echo ""
echo "Полезные команды:"
echo "  make help     - Показать все доступные команды"
echo "  make logs     - Просмотр логов"
echo "  make shell    - Войти в контейнер PHP"
echo "  make down     - Остановить контейнеры"
```

Сделать скрипт исполняемым:
```bash
chmod +x docker/setup.sh
```

---

### Этап 7: Документация (2 часа)

#### Шаг 7.1: Обновить README.md

Добавить раздел в начало `README.md`:

```markdown
## 🐳 Docker быстрая настройка

### Требования

- Docker 20.10+
- Docker Compose 2.0+
- 8 GB RAM (минимум)
- 20 GB свободного места

### Быстрый старт

```bash
# Клонировать репозиторий
git clone https://github.com/your-org/radarium.git
cd radarium

# Запустить скрипт настройки
./docker/setup.sh

# Или вручную:
make up
make install
make migrate
make ollama-pull
```

### Доступные команды

```bash
make help          # Показать все команды
make up            # Запустить контейнеры
make down          # Остановить контейнеры
make logs          # Просмотреть логи
make shell         # Войти в контейнер PHP
make test          # Запустить тесты
make fresh         # Пересоздать БД
make backup        # Создать бэкап
```

### Структура контейнеров

| Сервис | Порт | Описание |
|--------|------|----------|
| nginx | 80, 443 | Web сервер |
| php | 9000 | PHP-FPM процессор |
| mysql | 3306 | База данных MariaDB |
| redis | 6379 | Кэш и сессии |
| ollama | 11434 | AI модель Qwen2.5 |
| scheduler | - | Laravel планировщик |
| queue | - | Обработчик очередей |

### Работа с базой данных

```bash
# Подключиться к MySQL
docker-compose exec mysql mysql -uradarium -pradium_password radarium

# Создать бэкап
make backup

# Восстановить из бэкапа
make restore
```

### Масштабирование

```bash
# Запустить 3 инстанса очереди
docker-compose up -d --scale queue=3

# Запустить 5 параллельных парсеров
docker-compose up -d --scale worker=5
```

### Troubleshooting

#### Контейнер не запускается
```bash
make logs  # Проверить логи
make down && make up  # Перезапустить
```

#### Проблемы с правами доступа
```bash
docker-compose exec php chown -R laravel:laravel /var/www/html/storage
```

#### Ollama медленно работает
Проверьте наличие GPU поддержки или используйте квантованную модель:
```bash
make ollama-pull  # Загружает qwen2.5:7b-instruct-q4_K_M
```
```

#### Шаг 7.2: Создать docker/README.md

**Файл:** `docker/README.md`

```markdown
# Docker документация для Radarium

## Архитектура

Проект использует микросервисную архитектуру на базе Docker Compose:

```
┌─────────────┐
│    Nginx    │ Port 80, 443
└──────┬──────┘
       │
┌──────▼──────┐     ┌─────────────┐
│  PHP-FPM    │────▶│    Redis    │
└──────┬──────┘     └─────────────┘
       │
       ├─────────────┐
       │             │
┌──────▼──────┐ ┌───▼────┐
│    MySQL    │ │ Ollama │
└─────────────┘ └────────┘
```

## Переменные окружения

Основные переменные задаются в `.env` файле:

```bash
# Приложение
APP_ENV=local
APP_DEBUG=true

# База данных
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=radarium
DB_USERNAME=radarium
DB_PASSWORD=radarium_password

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Ollama
OLLAMA_HOST=http://ollama:11434
```

## Профили запуска

### Стандартный режим
```bash
docker-compose up -d
```

### С E5 API (требует больше ресурсов)
```bash
docker-compose --profile e5 up -d
```

### Production режим
```bash
docker-compose -f docker-compose.prod.yml up -d
```

## Мониторинг

### Проверка статуса
```bash
make ps
make stats
```

### Логи
```bash
make logs           # Все логи
make logs-app       # Логи приложения
make logs-db        # Логи БД
make logs-ollama    # Логи AI
```

## Бэкапы

### Автоматический бэкап (cron)
```bash
# Добавить в crontab
0 2 * * * cd /path/to/radarium && make backup
```

### Восстановление
```bash
make restore < backup_20250618_120000.sql
```

## Производительность

### Оптимизация для production

1. Изменить `docker-compose.prod.yml`:
   - Включить OPcache
   - Увеличить память PHP
   - Настроить MySQL buffers

2. Использовать volumes для статических файлов

3. Включить HTTP/2 в Nginx

### GPU поддержка для Ollama

Для использования GPU добавьте в `docker-compose.yml`:

```yaml
deploy:
  resources:
    reservations:
      devices:
        - driver: nvidia
          count: all
          capabilities: [gpu]
```

## Безопасность

1. Никогда не коммитьте `.env` файл
2. Используйте secrets для чувствительных данных
3. Регулярно обновляйте образы
4. Ограничьте доступ к портам в production

## CI/CD интеграция

Пример GitHub Actions workflow см. в `.github/workflows/docker.yml`
```

---

### Этап 8: Production конфигурация (2 часа)

#### Шаг 8.1: Production docker-compose

**Файл:** `docker-compose.prod.yml`

```yaml
version: '3.8'

services:
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      args:
        - APP_ENV=production
        - APP_DEBUG=false
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    command: php-fpm --nodaemonize
    deploy:
      replicas: 2
      resources:
        limits:
          cpus: '1.0'
          memory: 512M

  nginx:
    build:
      context: ./docker/nginx
      dockerfile: Dockerfile
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/ssl:/etc/nginx/ssl
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 256M

  mysql:
    image: mariadb:10.11
    volumes:
      - mysql_data:/var/lib/mysql
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G
    command: >
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
      --innodb-buffer-pool-size=1G
      --max-connections=500

  redis:
    image: redis:alpine
    command: redis-server --appendonly yes --maxmemory 256mb --maxmemory-policy allkeys-lru
    volumes:
      - redis_data:/data

  ollama:
    image: ollama/ollama:latest
    volumes:
      - ollama_data:/root/.ollama
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              count: all
              capabilities: [gpu]

  queue:
    deploy:
      replicas: 3
      resources:
        limits:
          cpus: '0.5'
          memory: 256M

networks:
  radarium_network:
    driver: overlay

volumes:
  mysql_data:
  redis_data:
  ollama_data:
```

#### Шаг 8.2: SSL сертификат (опционально)

Создать директорию и сгенерировать самоподписанный сертификат для разработки:

```bash
mkdir -p docker/nginx/ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout docker/nginx/ssl/nginx.key \
  -out docker/nginx/ssl/nginx.crt \
  -subj "/C=RU/ST=Moscow/L=Moscow/O=Radarium/CN=localhost"
```

---

## 📊 Оценка ресурсов

### Минимальные требования

| Компонент | CPU | RAM | Disk |
|-----------|-----|-----|------|
| PHP + Nginx | 1 core | 512 MB | 2 GB |
| MariaDB | 1 core | 1 GB | 10 GB |
| Redis | 0.5 core | 256 MB | 1 GB |
| Ollama (CPU) | 2 cores | 4 GB | 5 GB |
| **Итого** | **4.5 cores** | **5.7 GB** | **18 GB** |

### Рекомендуемые требования

| Компонент | CPU | RAM | Disk |
|-----------|-----|-----|------|
| PHP + Nginx (2 replicas) | 2 cores | 1 GB | 2 GB |
| MariaDB | 2 cores | 2 GB | 20 GB |
| Redis | 1 core | 512 MB | 2 GB |
| Ollama (GPU) | 4 cores + GPU | 8 GB | 10 GB |
| Queue workers (3) | 1.5 cores | 768 MB | - |
| **Итого** | **10.5 cores + GPU** | **12.3 GB** | **34 GB** |

---

## ✅ Чеклист внедрения

- [ ] Docker и Docker Compose установлены
- [ ] Dockerfile для PHP создан
- [ ] Конфигурация Nginx готова
- [ ] MySQL init скрипт создан
- [ ] docker-compose.yml настроен
- [ ] Makefile создан
- [ ] Setup скрипт работает
- [ ] README обновлён
- [ ] Тесты проходят в Docker
- [ ] Production конфигурация готова
- [ ] Бэкап/восстановление протестировано
- [ ] Документация полная

---

## ⚠️ Риски и митигация

| Риск | Вероятность | Митигация |
|------|-------------|-----------|
| Потеря данных | Низкая | Регулярные бэкапы, volumes |
| Простои при миграции | Средняя | Поэтапное внедрение, rollback план |
| Производительность | Низкая | Monitoring, scaling, оптимизация |
| Сложность отладки | Средняя | Логирование, debug инструменты |

---

## 📝 Примечания

- Не начинать внедрение до завершения приоритетных задач (пункты 7-11)
- Требуется тестирование на staging окружении перед production
- Рекомендуется постепенная миграция: сначала dev, потом staging, затем production
- Для GPU поддержки Ollama требуется NVIDIA Container Toolkit

---

**Дата создания:** 2025-06-18  
**Статус:** Ожидает реализации  
**Ответственный:** TBD
