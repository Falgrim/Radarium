# Анализ обработки сообщений builders в Radarium

## Куда попадают отобранные сообщения из ветки парсинга builders

### Процесс обработки:

1. **Парсинг сообщений из Telegram** (`app:tg_parse:builder`)
   - Команда: `ParseTelegramBuilderChats`
   - Файл: `app/Console/Commands/ParseTelegramBuilderChats.php`
   - Результат: Создаются записи в таблице `api_channel_posts` со статусом `InQueue`
   - Условие: Каналы должны иметь `is_company = Builder` и `status = Active`

2. **Обработка через ИИ** (`app:ai_parse:builder`)
   - Команда: `AiBuilderPosts`
   - Файл: `app/Console/Commands/AiBuilderPosts.php`
   - Результат: Создаются записи в таблице `builders`
   - Условие: ИИ должен определить тип сообщения как:
     - "резюме"
     - "предоставление услуги"
     - "предложение услуг"

3. **Структура данных:**
   - `api_channels` - источники сообщений (каналы Telegram)
   - `api_channel_posts` - сырые сообщения из каналов
   - `api_post_users` - пользователи (авторы сообщений)
   - `builders` - обработанные ИИ записи строителей

### Промпт для builders:
Промпт хранится в таблице `api_channels` в поле `ai_promt` для каждого канала с типом `is_company = Builder`.

## Можно ли увидеть builders на главной странице поиска?

**НЕТ**, builders НЕ отображаются на главной странице (`/`).

- **Главная страница** (`IndexController::index`):
  - Отображает только `specialists` (специалистов)
  - Файл: `app/Http/Controllers/IndexController.php`
  - Роут: `route('index')` → `/`

- **Страница builders**:
  - Отображает только `builders` (строителей)
  - Контроллер: `BuilderController::builders`
  - Роут: `route('catalog.builders')` → `/builders`
  - Файл: `app/Http/Controllers/BuilderController.php`

## Как проверить количество сообщений по теме builders, обработанных как "предложение услуг"

### SQL запросы для проверки:

#### 1. Общее количество builders с типом "предложение услуг":

```sql
SELECT COUNT(*) as total
FROM builders
WHERE LOWER(ai_type) = 'предложение услуг'
  AND status = 'active';
```

#### 2. Количество builders с типом "предложение услуг" по датам создания:

```sql
SELECT 
    DATE(created_at) as date,
    COUNT(*) as count
FROM builders
WHERE LOWER(ai_type) = 'предложение услуг'
  AND status = 'active'
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

#### 3. Все типы ai_type в builders (для проверки корректности):

```sql
SELECT 
    LOWER(ai_type) as ai_type,
    COUNT(*) as count,
    status
FROM builders
GROUP BY LOWER(ai_type), status
ORDER BY count DESC;
```

#### 4. Количество обработанных сообщений из каналов builders:

```sql
SELECT 
    ac.title as channel_title,
    COUNT(b.id) as builders_count,
    COUNT(CASE WHEN LOWER(b.ai_type) = 'предложение услуг' THEN 1 END) as offer_services_count
FROM api_channels ac
LEFT JOIN api_channel_posts acp ON ac.id = acp.api_channel_id
LEFT JOIN builders b ON acp.id = b.api_channel_post_id
WHERE ac.is_company = 'Builder'
GROUP BY ac.id, ac.title
ORDER BY builders_count DESC;
```

#### 5. Статистика по статусам обработки сообщений builders:

```sql
SELECT 
    acp.ai_parse_status,
    COUNT(*) as count,
    COUNT(CASE WHEN LOWER(b.ai_type) = 'предложение услуг' THEN 1 END) as offer_services_count
FROM api_channel_posts acp
INNER JOIN api_channels ac ON ac.id = acp.api_channel_id
LEFT JOIN builders b ON acp.id = b.api_channel_post_id
WHERE ac.is_company = 'Builder'
GROUP BY acp.ai_parse_status;
```

#### 6. Детальная информация о builders с типом "предложение услуг":

```sql
SELECT 
    b.id,
    b.ai_type,
    b.status,
    b.created_at,
    b.post_date,
    ac.title as channel_title,
    apu.username,
    apu.first_name,
    apu.last_name
FROM builders b
INNER JOIN api_channel_posts acp ON b.api_channel_post_id = acp.id
INNER JOIN api_channels ac ON acp.api_channel_id = ac.id
INNER JOIN api_post_users apu ON b.api_post_user_id = apu.id
WHERE LOWER(b.ai_type) = 'предложение услуг'
  AND b.status = 'active'
ORDER BY b.created_at DESC
LIMIT 100;
```

### Через Laravel Tinker:

```php
// Общее количество builders с типом "предложение услуг"
\App\Models\Builder::whereRaw('LOWER(ai_type) = ?', ['предложение услуг'])
    ->where('status', \App\Enum\ApiPostAiStatusEnum::Active)
    ->count();

// С группировкой по датам
\App\Models\Builder::whereRaw('LOWER(ai_type) = ?', ['предложение услуг'])
    ->where('status', \App\Enum\ApiPostAiStatusEnum::Active)
    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
    ->groupBy('date')
    ->orderBy('date', 'desc')
    ->get();
```

### Через MoonShine админку:

1. Перейти в раздел "Строительство" (BuilderResource)
2. Использовать фильтры:
   - `ai_type` = "предложение услуг"
   - `status` = "Active"

## Важные замечания:

1. **Поле `ai_type`** хранится в таблице `builders` и содержит тип, определенный ИИ
2. **Статус обработки** сообщений хранится в `api_channel_posts.ai_parse_status`:
   - `InQueue` - в очереди на обработку
   - `Complete` - успешно обработано
   - `DontMatch` - не подходит (не тот тип)
   - `Error` - ошибка обработки
3. **Статус builders** в таблице `builders.status`:
   - `Active` - активный (отображается на сайте)
   - `InModeration` - на модерации
   - Другие статусы из `ApiPostAiStatusEnum`
