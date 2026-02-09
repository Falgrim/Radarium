# Техническое задание: Замена Yandex.GPT на локальную модель e5-base-multilingual

## 1. Общее описание задачи

**Цель:** Заменить использование Yandex.GPT на локальную модель `intfloat/e5-base-multilingual` для проверки сообщений из Telegram каналов на соответствие критериям, указанным в промпте Yandex.GPT.

**Текущая ситуация:**
- Проект использует Yandex.GPT для анализа постов из Telegram каналов
- Анализ выполняется в командах: `AiSpecialistPosts`, `AiCompanyPosts`, `AiBuilderPosts`
- Используется сервис `ApiAIYandex` для работы с Yandex.GPT API

**Требуемый результат:**
- Добавить поддержку локальной модели e5-base-multilingual как альтернативного источника API
- Сохранить возможность использования Yandex.GPT (параллельная работа)
- Обеспечить проверку сообщений на соответствие критериям из промпта

---

## 2. Архитектурные особенности

### 2.1. Особенности модели e5-base-multilingual

**Важно:** Модель e5-base-multilingual - это embedding модель, которая:
- Не генерирует текст напрямую
- Создает векторные представления (embeddings) текста
- Используется для семантического сравнения текстов

**Подход к реализации:**
1. Использовать e5 для проверки соответствия критериям через семантическое сравнение embeddings
2. Для извлечения структурированных данных потребуется дополнительный локальный LLM (например, через Ollama)

### 2.2. Требования к локальному API серверу

Локальный API сервер должен предоставлять следующие endpoints:

#### POST /embed
Получение embeddings для текста.

**Запрос:**
```json
{
  "text": "Текст для получения embeddings"
}
```

**Ответ:**
```json
{
  "embedding": [0.123, -0.456, ...],
  "dimension": 768
}
```

#### POST /complete
Извлечение структурированных данных из текста на основе промпта.

**Запрос:**
```json
{
  "prompt": "Промпт с критериями проверки",
  "text": "Текст сообщения для анализа",
  "max_tokens": 1200,
  "temperature": 0.3
}
```

**Ответ:**
```json
{
  "result": "JSON строка с извлеченными данными"
}
```

---

## 3. Пошаговое описание изменений

### Шаг 1: Добавление нового источника API в enum

**Файл:** `app/Enum/ApiAiSourceEnum.php`

**Действия:**
1. Добавить новый case в enum:
```php
case E5Local = 'e5local';
```

2. Обновить метод `toString()`:
```php
public function toString(): ?string
{
    return match ($this) {
        self::YandexGTP4    => 'Яндекс GPT 4',
        self::E5Local      => 'E5 Base Multilingual (локально)',
    };
}
```

3. Обновить метод `getColor()`:
```php
public function getColor(): ?string
{
    return match ($this) {
        self::YandexGTP4    => 'success',
        self::E5Local      => 'info',
    };
}
```

**Полный код после изменений:**
```php
enum ApiAiSourceEnum:string {
    case YandexGTP4 = 'yandexgtp4';
    case E5Local = 'e5local';

    public function toString(): ?string
    {
        return match ($this) {
            self::YandexGTP4    => 'Яндекс GPT 4',
            self::E5Local      => 'E5 Base Multilingual (локально)',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::YandexGTP4    => 'success',
            self::E5Local      => 'info',
        };
    }
}
```

---

### Шаг 2: Создание сервиса для работы с локальной моделью

**Файл:** `app/Services/ApiAIE5.php` (новый файл)

**Структура сервиса:**

1. **Класс и свойства:**
```php
namespace App\Services;

use App\Enum\ApiDataTypeEnum;
use danog\MadelineProto\Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiAIE5
{
    protected string $apiUrl;
    protected string $promt;
    protected string $text;
    protected bool $aiDebug = false;
    protected bool $aiLogging = false;
    protected float $similarityThreshold = 0.7;
}
```

2. **Метод `getKeyRows()`** - скопировать из `ApiAIYandex.php` (без изменений)

3. **Метод `setConfig()`:**
```php
public function setConfig(array $config)
{
    if (!isset($config['api_url'])) {
        throw new Exception('Не указан api_url параметр (URL локального API сервера с моделью)');
    }

    $this->apiUrl = rtrim($config['api_url'], '/');
    
    // Опциональный параметр для настройки порога similarity
    if (isset($config['similarity_threshold'])) {
        $this->similarityThreshold = (float)$config['similarity_threshold'];
    }
}
```

4. **Метод `setPromt()`:**
```php
public function setPromt(string $promt)
{
    $this->promt = $promt;
}
```

5. **Метод `setText()`:**
```php
public function setText(string $text)
{
    $this->text = $text;
}
```

6. **Метод `getEmbeddings()`:**
```php
protected function getEmbeddings(string $text): array
{
    $response = Http::timeout(30)->post($this->apiUrl . '/embed', [
        'text' => $text,
    ]);

    if ($response->status() !== 200) {
        throw new \Exception('Не удалось получить embeddings: ' . $response->body());
    }

    $data = $response->json();
    if (!isset($data['embedding'])) {
        throw new \Exception('В ответе отсутствует параметр embedding: ' . json_encode($data));
    }

    return $data['embedding'];
}
```

7. **Метод `checkCriteria()`:**
```php
protected function checkCriteria(): array
{
    // Получаем embeddings для сообщения
    $messageEmbedding = $this->getEmbeddings($this->text);
    
    // Получаем embeddings для критериев из промпта
    $criteriaEmbedding = $this->getEmbeddings($this->promt);
    
    // Вычисляем косинусное сходство
    $similarity = $this->cosineSimilarity($messageEmbedding, $criteriaEmbedding);
    
    return [
        'similarity' => $similarity,
        'matches' => $similarity > $this->similarityThreshold,
    ];
}
```

8. **Метод `cosineSimilarity()`:**
```php
protected function cosineSimilarity(array $a, array $b): float
{
    if (count($a) !== count($b)) {
        throw new \Exception('Векторы должны иметь одинаковую размерность');
    }

    $dotProduct = 0;
    $normA = 0;
    $normB = 0;

    for ($i = 0; $i < count($a); $i++) {
        $dotProduct += $a[$i] * $b[$i];
        $normA += $a[$i] * $a[$i];
        $normB += $b[$i] * $b[$i];
    }

    $normA = sqrt($normA);
    $normB = sqrt($normB);

    if ($normA == 0 || $normB == 0) {
        return 0;
    }

    return $dotProduct / ($normA * $normB);
}
```

9. **Метод `extractStructuredData()`:**
```php
protected function extractStructuredData(): string
{
    // Используем локальный LLM API для извлечения структурированных данных
    $response = Http::timeout(60)->post($this->apiUrl . '/complete', [
        'prompt' => $this->promt,
        'text' => $this->text,
        'max_tokens' => 1200,
        'temperature' => 0.3,
    ]);

    if ($response->status() !== 200) {
        throw new \Exception('Не удалось извлечь данные: ' . $response->body());
    }

    $data = $response->json();
    if (!isset($data['result'])) {
        throw new \Exception('В ответе отсутствует параметр result: ' . json_encode($data));
    }

    return $data['result'];
}
```

10. **Метод `getResult()`** - скопировать логику из `ApiAIYandex.php`, но с вызовом `checkCriteria()` и `extractStructuredData()`

11. **Метод `logging()`** - скопировать из `ApiAIYandex.php`

**Полный код файла:** См. приложение А

---

### Шаг 3: Обновление команды AiSpecialistPosts

**Файл:** `app/Console/Commands/AiSpecialistPosts.php`

**Действия:**

1. Добавить use для нового сервиса:
```php
use App\Services\ApiAIE5;
```

2. В методе `handle()`, в блоке обработки постов, после проверки `YandexGTP4` добавить:
```php
} elseif ($post->channel->apiAi->api_source === ApiAiSourceEnum::E5Local) {
    $ApiAIE5 = new ApiAIE5;
    $ApiAIE5->logging('Анализ поста ID: '.$post->id);
    $ApiAIE5->setConfig($options);
    $ApiAIE5->setPromt($promt);
    $ApiAIE5->setText($post->post);
    $result = $ApiAIE5->getResult(ApiDataTypeEnum::Specialist);
```

3. Обновить обработку ошибок в блоках `catch`:
```php
} catch (\TypeError $e) {
    $this->error($e->getMessage());
    if (isset($ApiAIYandex)) {
        $ApiAIYandex->logging($e->getMessage(), true);
    } elseif (isset($ApiAIE5)) {
        $ApiAIE5->logging($e->getMessage(), true);
    }
    // ... остальной код
} catch (\Exception $e) {
    $this->error($e->getMessage());
    if (isset($ApiAIYandex)) {
        $ApiAIYandex->logging($e->getMessage(), true);
    } elseif (isset($ApiAIE5)) {
        $ApiAIE5->logging($e->getMessage(), true);
    }
    // ... остальной код
}
```

---

### Шаг 4: Обновление команды AiCompanyPosts

**Файл:** `app/Console/Commands/AiCompanyPosts.php`

**Действия:** Аналогично шагу 3, но использовать `ApiDataTypeEnum::Company`

---

### Шаг 5: Обновление команды AiBuilderPosts

**Файл:** `app/Console/Commands/AiBuilderPosts.php`

**Действия:** Аналогично шагу 3, но использовать `ApiDataTypeEnum::Builder`

---

### Шаг 6: Обновление ресурса MoonShine

**Файл:** `app/MoonShine/Resources/ApiAiResource.php`

**Действия:**

В методе `formFields()`, обновить подсказку для поля `options`:
```php
$fields[] = Json::make('Опции для запуска', 'options')
    ->hint('Технические параметры для доп. настройки<br />Для '.ApiAiSourceEnum::YandexGTP4->toString().' обязательны параметры: API_KEY_TOKEN и Folder_id (подробнее: https://yandex.cloud/ru/docs/foundation-models/quickstart/yandexgpt#api_2)<br />Для '.ApiAiSourceEnum::E5Local->toString().' обязателен параметр: api_url (URL локального API сервера с моделью, например: http://localhost:8000)')
    ->keyValue();
```

---

## 4. Настройка локального API сервера

### 4.1. Установка зависимостей Python

```bash
pip install torch transformers flask flask-cors sentencepiece
```

### 4.2. Пример сервера API

Создать файл `e5_api_server.py`:

```python
from flask import Flask, request, jsonify
from flask_cors import CORS
from transformers import AutoTokenizer, AutoModel
import torch

app = Flask(__name__)
CORS(app)

# Загрузка модели e5-base-multilingual
print("Загрузка модели e5-base-multilingual...")
tokenizer = AutoTokenizer.from_pretrained('intfloat/e5-base-multilingual-v2')
model = AutoModel.from_pretrained('intfloat/e5-base-multilingual-v2')
model.eval()
print("Модель загружена успешно!")

def get_embeddings(text):
    """Получение embeddings для текста"""
    if not text.startswith("query: ") and not text.startswith("passage: "):
        text = f"query: {text}"
    
    inputs = tokenizer(text, return_tensors="pt", padding=True, truncation=True, max_length=512)
    
    with torch.no_grad():
        outputs = model(**inputs)
        embeddings = outputs.last_hidden_state.mean(dim=1).squeeze()
    
    return embeddings.tolist()

@app.route('/embed', methods=['POST'])
def embed():
    try:
        data = request.get_json()
        text = data.get('text', '')
        
        if not text:
            return jsonify({'error': 'Текст не указан'}), 400
        
        embedding = get_embeddings(text)
        
        return jsonify({
            'embedding': embedding,
            'dimension': len(embedding)
        })
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/complete', methods=['POST'])
def complete():
    """
    Извлечение структурированных данных из текста на основе промпта
    Требует локальный LLM (например, через Ollama)
    """
    try:
        data = request.get_json()
        prompt = data.get('prompt', '')
        text = data.get('text', '')
        max_tokens = data.get('max_tokens', 1200)
        temperature = data.get('temperature', 0.3)
        
        if not prompt or not text:
            return jsonify({'error': 'Промпт и текст обязательны'}), 400
        
        # TODO: Интеграция с локальным LLM (Ollama, transformers и т.д.)
        # Пример для Ollama:
        # import requests
        # response = requests.post('http://localhost:11434/api/generate', json={
        #     'model': 'llama2',
        #     'prompt': f"{prompt}\n\nТекст для анализа:\n{text}\n\nИзвлеки структурированные данные в формате JSON:",
        #     'stream': False
        # })
        # result = response.json()['response']
        
        # Временная заглушка
        result = '{"type": "резюме", "reason": "Сообщение соответствует критериям"}'
        
        return jsonify({
            'result': result.strip()
        })
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        'status': 'ok',
        'model': 'intfloat/e5-base-multilingual-v2'
    })

if __name__ == '__main__':
    print("Запуск API сервера на http://localhost:8000")
    app.run(host='0.0.0.0', port=8000, debug=True)
```

### 4.3. Запуск сервера

```bash
python e5_api_server.py
```

---

## 5. Настройка в админ-панели

### 5.1. Создание нового сервиса ИИ

1. Перейти в раздел "Сервисы ИИ" в админ-панели MoonShine
2. Создать новый сервис или отредактировать существующий
3. Заполнить поля:
   - **Название:** E5 Local Model
   - **Описание:** Локальная модель e5-base-multilingual для анализа постов
   - **API сервис:** Выбрать "E5 Base Multilingual (локально)"
   - **Статус:** Active
   - **Опции для запуска:**
     ```json
     {
       "api_url": "http://localhost:8000",
       "similarity_threshold": 0.7
     }
     ```

### 5.2. Настройка канала

1. Перейти в раздел каналов
2. Выбрать канал для редактирования
3. В поле "API ИИ" выбрать созданный сервис E5 Local Model
4. Сохранить изменения

---

## 6. Тестирование

### 6.1. Проверка работы API сервера

```bash
# Проверка health endpoint
curl http://localhost:8000/health

# Проверка embed endpoint
curl -X POST http://localhost:8000/embed \
  -H "Content-Type: application/json" \
  -d '{"text": "Тестовый текст"}'
```

### 6.2. Запуск команд анализа

```bash
# Анализ постов специалистов
php artisan app:ai_parse:specialist

# Анализ постов компаний
php artisan app:ai_parse:company

# Анализ постов строителей
php artisan app:ai_parse:builder
```

### 6.3. Проверка логов

Логи работы с локальной моделью сохраняются в канал `ai_debug` (если включено логирование в конфиге `services.ai.logging`).

---

## 7. Важные замечания

### 7.1. Порог similarity

- По умолчанию установлен порог 0.7
- Значения близкие к 1.0 означают высокое сходство
- Значения близкие к 0.0 означают низкое сходство
- Рекомендуемый диапазон: 0.6 - 0.8
- Можно настроить через параметр `similarity_threshold` в опциях сервиса

### 7.2. Извлечение структурированных данных

Для полной замены функциональности Yandex.GPT требуется:
- Локальный LLM для извлечения структурированных данных
- Варианты: Ollama, transformers с LLM моделями, другие серверы инференса

### 7.3. Производительность

- Модель e5-base-multilingual требует значительных ресурсов
- Рекомендуется использовать GPU для ускорения
- Можно использовать кэширование embeddings для повторяющихся текстов

---

## 8. Приложения

### Приложение А: Полный код сервиса ApiAIE5

[Здесь должен быть полный код файла `app/Services/ApiAIE5.php` - см. раздел 3, шаг 2 для детального описания всех методов]

### Приложение Б: Пример конфигурации

**Файл:** `config/services.php` (если требуется)

```php
'ai' => [
    'debug' => env('AI_DEBUG', false),
    'logging' => env('AI_LOGGING', false),
],
```

---

## 9. Чек-лист внедрения

- [ ] Добавлен новый case `E5Local` в `ApiAiSourceEnum`
- [ ] Создан сервис `ApiAIE5` со всеми необходимыми методами
- [ ] Обновлена команда `AiSpecialistPosts`
- [ ] Обновлена команда `AiCompanyPosts`
- [ ] Обновлена команда `AiBuilderPosts`
- [ ] Обновлен ресурс `ApiAiResource` в MoonShine
- [ ] Развернут локальный API сервер с моделью e5-base-multilingual
- [ ] Настроен сервис ИИ в админ-панели
- [ ] Проведено тестирование всех команд анализа
- [ ] Проверена работа логирования
- [ ] Настроен порог similarity (при необходимости)

---

## 10. Дополнительные ресурсы

- Документация модели: https://huggingface.co/intfloat/e5-base-multilingual-v2
- Документация transformers: https://huggingface.co/docs/transformers
- Ollama для локальных LLM: https://ollama.ai/

---

**Дата создания документа:** 2025-01-28  
**Версия:** 1.0  
**Статус:** Готово к реализации

