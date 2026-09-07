# Руководство по тестированию Radarium

## 📋 Обзор

В проекте создана полная структура unit и feature тестов для критически важных сервисов. Все тесты находятся в отдельной папке `tests/` и **не влияют на основной код проекта**.

---

## 🗂️ Структура тестов

```
tests/
├── TestCase.php              # Базовый класс для всех тестов
├── Unit/                     # Unit-тесты (изолированные)
│   ├── ExampleTest.php       # Пример от Laravel
│   ├── DictionaryTest.php    # Тесты сервиса Dictionary (13 тестов)
│   ├── TariffTest.php        # Тесты сервиса Tariff (16 тестов)
│   └── ApiAIOllamaTest.php   # Тесты сервиса ApiAIOllama (20 тестов)
└── Feature/                  # Feature-тесты (интеграционные)
    ├── ExampleTest.php       # Пример от Laravel
    ├── ProfileTest.php       # Тесты профиля пользователя
    └── ReadTelegramChatsTest.php # Тесты сервиса ReadTelegramChats (14 тестов)
```

**Итого:** 63 автоматических теста

---

## 🚀 Быстрый старт

### Запуск всех тестов

```bash
cd /workspace
php artisan test
```

### Запуск по типу тестов

```bash
# Только Unit-тесты
php artisan test --testsuite=Unit

# Только Feature-тесты
php artisan test --testsuite=Feature
```

### Запуск отдельных файлов

```bash
# Конкретный файл
php artisan test tests/Unit/DictionaryTest.php

# Конкретный тест внутри файла
php artisan test --filter=test_parse_okco_string_with_code_and_name
```

### Запуск с покрытием кода

```bash
php artisan test --coverage
```

---

## 📦 Установленные зависимости

Для работы тестов требуется PHPUnit (уже установлен в Laravel):

```bash
# Проверка версии PHPUnit
./vendor/bin/phpunit --version

# Если не установлен (маловероятно)
composer install --dev
```

---

## 🧪 Описание тестов

### 1. DictionaryTest (13 тестов)

**Файл:** `tests/Unit/DictionaryTest.php`

**Тестируемый сервис:** `app/Services/Dictionary.php`

**Что проверяется:**

| № | Тест | Описание |
|---|------|----------|
| 1 | `test_parse_okco_string_with_code_and_name` | Парсинг OKCO строки с кодом и названием |
| 2 | `test_parse_okco_string_only_name` | Парсинг OKCO строки только с названием |
| 3 | `test_acronym_generation` | Создание аббревиатуры из названия |
| 4 | `test_acronym_with_null` | Обработка null значения |
| 5 | `test_check_match_by_list_found` | Поиск совпадений по ключевым словам (успех) |
| 6 | `test_check_match_by_list_not_found` | Поиск совпадений без результатов |
| 7 | `test_check_match_by_list_case_insensitive` | Регистронезависимый поиск |
| 8 | `test_get_or_create_new_record` | Создание новой записи словаря |
| 9 | `test_get_or_create_existing_record` | Получение существующей записи |
| 10 | `test_update_relations_for_specialist` | Обновление связей для специалиста |
| 11 | `test_update_relations_remove_all` | Удаление всех связей |
| 12 | `test_get_all_unknown_dictionary` | Обработка неизвестного словаря |
| 13 | `test_get_all_specialities` | Получение всех записей из словаря |

**Пример запуска:**
```bash
php artisan test --filter=DictionaryTest
```

---

### 2. TariffTest (16 тестов)

**Файл:** `tests/Unit/TariffTest.php`

**Тестируемый сервис:** `app/Services/Tariff.php`

**Что проверяется:**

| № | Тест | Описание |
|---|------|----------|
| 1 | `test_check_contact_access_unauthenticated` | Доступ для неавторизованного |
| 2 | `test_check_contact_access_with_open_contact` | Доступ с открытым контактом |
| 3 | `test_check_contact_access_with_left_contacts` | Доступ с остатком платных контактов |
| 4 | `test_check_contact_access_with_free_contacts` | Доступ с бесплатными контактами |
| 5 | `test_check_open_contact_previously_opened` | Проверка ранее открытого контакта |
| 6 | `test_get_open_contact_log_found` | Получение лога (найдено) |
| 7 | `test_get_open_contact_log_not_found` | Получение лога (не найдено) |
| 8 | `test_add_open_contact_log_with_active_tariff` | Логирование с активным тарифом |
| 9 | `test_add_open_contact_log_without_tariff` | Логирование без тарифа (списание) |
| 10 | `test_free_contacts_minus_success` | Списание бесплатного контакта (успех) |
| 11 | `test_free_contacts_minus_zero_balance` | Списание при нулевом балансе |
| 12 | `test_get_invoice_id` | Генерация Invoice ID |
| 13 | `test_get_all_contacts_by_user` | Получение всех контактов пользователя |
| 14 | `test_check_access_to_open_with_left_contacts` | Доступ по остатку контактов |
| 15 | `test_check_access_to_open_no_contacts` | Отсутствие доступа |
| 16 | `test_check_access_to_open_with_free_contacts` | Доступ по бесплатным контактам |

**Пример запуска:**
```bash
php artisan test --filter=TariffTest
```

---

### 3. ApiAIOllamaTest (20 тестов)

**Файл:** `tests/Unit/ApiAIOllamaTest.php`

**Тестируемый сервис:** `app/Services/ApiAIOllama.php`

**Что проверяется:**

| № | Тест | Описание |
|---|------|----------|
| 1 | `test_set_config` | Установка конфигурации |
| 2 | `test_set_config_with_defaults` | Конфигурация со значениями по умолчанию |
| 3 | `test_set_promt` | Установка промпта |
| 4 | `test_set_text` | Установка текста |
| 5 | `test_generate_url` | Генерация URL API |
| 6 | `test_generate_url_with_trailing_slash` | URL с trailing slash |
| 7 | `test_generate_json` | Генерация JSON запроса |
| 8 | `test_parse_response_success` | Парсинг успешного ответа |
| 9 | `test_parse_response_with_markdown` | Парсинг с markdown блоком |
| 10 | `test_parse_response_without_choices` | Ответ без choices (ошибка) |
| 11 | `test_parse_response_without_content` | Ответ без content (ошибка) |
| 12 | `test_parse_response_empty_content` | Пустой ответ (ошибка) |
| 13 | `test_get_key_rows_company` | Ключевые поля для Company |
| 14 | `test_get_key_rows_specialist` | Ключевые поля для Specialist |
| 15 | `test_get_key_rows_builder` | Ключевые поля для Builder |
| 16 | `test_process_price_fields` | Обработка ценовых полей |
| 17 | `test_process_array_string_fields` | Обработка array_string полей |
| 18 | `test_process_null_values` | Обработка null значений |
| 19 | `test_logging_info` | Логирование информации |
| 20 | `test_logging_error` | Логирование ошибки |

**Пример запуска:**
```bash
php artisan test --filter=ApiAIOllamaTest
```

---

### 4. ReadTelegramChatsTest (14 тестов)

**Файл:** `tests/Feature/ReadTelegramChatsTest.php`

**Тестируемый сервис:** `app/Services/ReadTelegramChats.php`

**Что проверяется:**

| № | Тест | Описание |
|---|------|----------|
| 1 | `test_read_channel_without_api_config` | Чтение без конфигурации API |
| 2 | `test_read_channel_with_only_api_id` | Чтение только с api_id |
| 3 | `test_read_channel_with_only_api_hash` | Чтение только с api_hash |
| 4 | `test_filter_messages_by_min_length` | Фильтрация по длине сообщения |
| 5 | `test_filter_messages_without_message_field` | Фильтрация без поля message |
| 6 | `test_filter_messages_from_channels` | Фильтрация сообщений от каналов |
| 7 | `test_filter_reply_to_messages` | Фильтрация reply_to сообщений |
| 8 | `test_filter_reply_to_topic_one` | Фильтрация топика 1 |
| 9 | `test_filter_reply_to_multiple_ids` | Фильтрация по нескольким ID |
| 10 | `test_duplicate_detection_logic` | Логика дедупликации постов |
| 11 | `test_get_error_messages` | Получение сообщений об ошибках |
| 12 | `test_unset_messages` | Очистка сообщений |
| 13 | `test_with_null_settings` | Работа с null настройками |

**Пример запуска:**
```bash
php artisan test --filter=ReadTelegramChatsTest
```

---

## 🔧 Использование моков

Тесты используют следующие подходы к изоляции:

### 1. HTTP Fake (для API запросов)

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    '*/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => '{"key": "value"}']]],
    ], 200),
]);
```

### 2. Mockery (для сложных объектов)

```php
$userMock = \Mockery::mock($user)->makePartial();
$userMock->shouldReceive('getLeftContacts')
    ->andReturn(['count_contacts_left' => 5]);

Auth::setUser($userMock);
```

### 3. Reflection (для protected методов)

```php
$reflection = new \ReflectionClass($service);
$method = $reflection->getMethod('protectedMethodName');
$method->setAccessible(true);
$result = $method->invokeArgs($service, [$arg1, $arg2]);
```

### 4. RefreshDatabase (для работы с БД)

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyTest extends TestCase
{
    use RefreshDatabase;
    
    // База данных сбрасывается перед каждым тестом
}
```

---

## 📊 Покрытие кода

### Генерация отчёта о покрытии

```bash
# HTML отчёт
php artisan test --coverage-html=coverage-report

# Текстовый отчёт
php artisan test --coverage-text

# Clover XML (для CI/CD)
php artisan test --coverage-clover=coverage.xml
```

### Просмотр HTML отчёта

```bash
# Откройте в браузере
open coverage-report/index.html  # macOS
xdg-open coverage-report/index.html  # Linux
start coverage-report/index.html  # Windows
```

---

## 🎯 Best Practices

### 1. Naming Convention

```php
// ✅ Правильно
public function test_check_contact_access_unauthenticated()
public function test_parse_okco_string_with_code_and_name()

// ❌ Неправильно
public function testCheckContact()
public function checkAccess()
```

### 2. Arrange-Act-Assert Pattern

```php
public function test_example()
{
    // Arrange (Подготовка)
    $user = User::factory()->create();
    
    // Act (Действие)
    $result = $service->checkAccess($user);
    
    // Assert (Проверка)
    $this->assertTrue($result);
}
```

### 3. Test Isolation

```php
// ✅ Каждый тест независим
use RefreshDatabase;

public function test_one() { /* ... */ }
public function test_two() { /* ... */ }

// ❌ Тесты зависят друг от друга
public function test_one() { /* создаёт данные */ }
public function test_two() { /* использует данные из test_one */ }
```

### 4. Descriptive Assertions

```php
// ✅ Понятное сообщение об ошибке
$this->assertEquals(
    expected: 10000,
    actual: $result['min_price'],
    message: 'Цена должна быть умножена на 100'
);

// ❌ Непонятно что пошло не так
$this->assertEquals(10000, $result['min_price']);
```

---

## 🐛 Отладка тестов

### Вывод отладочной информации

```php
public function test_debug_example()
{
    $result = $service->getData();
    
    // Вывод в консоль при запуске с -v
    var_dump($result);
    dump($result);
    
    $this->assertTrue(true);
}
```

### Запуск с подробным выводом

```bash
# Подробный вывод
php artisan test --verbose

# Вывод только неудачных тестов
php artisan test --stop-on-failure

# Вывод каждого теста
php artisan test --testdox
```

### Отдельный лог для тестов

Добавьте в `phpunit.xml`:

```xml
<php>
    <env name="LOG_CHANNEL" value="testing"/>
</php>
```

---

## 🔄 CI/CD Интеграция

### GitHub Actions

Создайте `.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        
    - name: Install Dependencies
      run: composer install --no-interaction
      
    - name: Run Tests
      run: php artisan test
      
    - name: Generate Coverage
      run: php artisan test --coverage-clover=coverage.xml
      
    - name: Upload Coverage
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
```

### GitLab CI

Создайте `.gitlab-ci.yml`:

```yaml
tests:
  image: php:8.2-cli
  script:
    - composer install --no-interaction
    - php artisan test --coverage-clover=coverage.xml
  artifacts:
    reports:
      coverage_report:
        coverage_format: clover
        path: coverage.xml
```

---

## 📈 Расширение набора тестов

### Добавление нового теста

1. Создайте файл в соответствующей папке:
   - `tests/Unit/` для unit-тестов
   - `tests/Feature/` для feature-тестов

2. Назовите файл `{ServiceName}Test.php`

3. Структура файла:

```php
<?php

namespace Tests\Unit; // или Tests\Feature

use App\Services\YourService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YourServiceTest extends TestCase
{
    use RefreshDatabase;

    protected YourService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new YourService();
    }

    public function test_example(): void
    {
        $this->assertTrue(true);
    }
}
```

### Шаблон для быстрого создания

```bash
# Создать unit тест
php artisan make:test YourServiceTest --unit

# Создать feature тест
php artisan make:test YourFeatureTest
```

---

## ⚠️ Важные замечания

### 1. Тесты не влияют на production код

- Все тесты находятся в папке `tests/`
- Используют тестовую базу данных (in-memory или отдельная БД)
- Не изменяют файлы вне папки tests/
- Можно удалить всю папку tests/ без влияния на проект

### 2. Изоляция тестов

- Каждый тест работает со свежей БД (RefreshDatabase)
- Моки используются для внешних зависимостей
- Нет глобального состояния между тестами

### 3. Производительность

```bash
# Запустить только быстрые тесты
php artisan test --exclude-group=integration,slow

# Запустить медленные тесты отдельно
php artisan test --group=slow
```

### 4. Environment переменные для тестов

В `phpunit.xml` уже настроены:

```xml
<env name="APP_ENV" value="testing"/>
<env name="CACHE_STORE" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
```

---

## 📚 Дополнительные ресурсы

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/manual/current/en/)
- [Mockery Documentation](https://docs.mockery.io/)
- [Testing Helper Methods](https://laravel.com/docs/http-tests)

---

## ✅ Чеклист перед коммитом

- [ ] Все тесты проходят: `php artisan test`
- [ ] Нет предупреждений в выводе
- [ ] Новые тесты добавлены для нового функционала
- [ ] Покрытие кода не уменьшилось значительно
- [ ] Тесты следуют naming convention
- [ ] Используется Arrange-Act-Assert pattern

---

**Дата создания:** 2025-04-02  
**Версия:** 1.0  
**Автор:** AI Assistant
