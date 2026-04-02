# План внедрения поддержки E5 Base Multilingual

> **Статус:** Отложено на будущее  
> **Текущий приоритет:** Низкий  
> **Текущая реальность:** Ollama + Qwen2.5

---

## 📋 Обзор задачи

Замена/дополнение текущего провайдера YandexGPT на локальную модель E5 Base Multilingual для:
- Векторизации текста (embeddings)
- Семантического сравнения сообщений
- Проверки соответствия критериям отбора

---

## 🔧 Этап 1: Подготовка инфраструктуры

### Шаг 1.1: Развёртывание Python API сервера
```bash
# Создать директорию для E5 API сервера
mkdir -p e5-api-server
cd e5-api-server

# Инициализировать проект
python -m venv venv
source venv/bin/activate

# Установить зависимости
pip install fastapi uvicorn sentence-transformers pydantic
```

### Шаг 1.2: Создание API сервера
**Файл:** `e5-api-server/main.py`
```python
from fastapi import FastAPI
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
import numpy as np

app = FastAPI()
model = SentenceTransformer('intfloat/e5-base-v2')

class TextRequest(BaseModel):
    text: str

class CompareRequest(BaseModel):
    text1: str
    text2: str

@app.post("/embed")
def get_embedding(request: TextRequest):
    embedding = model.encode(f"passage: {request.text}", normalize_embeddings=True)
    return {"embedding": embedding.tolist()}

@app.post("/similarity")
def get_similarity(request: CompareRequest):
    emb1 = model.encode(f"query: {request.text1}", normalize_embeddings=True)
    emb2 = model.encode(f"passage: {request.text2}", normalize_embeddings=True)
    similarity = np.dot(emb1, emb2)
    return {"similarity": float(similarity)}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
```

### Шаг 1.3: Запуск сервера
```bash
# В production через systemd или supervisor
# В development:
python main.py

# Или через Docker (см. план Docker Compose)
```

---

## 🔧 Этап 2: Реализация PHP класса ApiAIE5

### Шаг 2.1: Заполнить пустой файл
**Файл:** `app/Services/ApiAIE5.php`

```php
<?php

namespace App\Services;

use App\Enum\ApiDataTypeEnum;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiAIE5 extends AbstractAiService
{
    protected string $apiUrl;
    protected float $similarityThreshold;

    public function __construct()
    {
        $this->apiUrl = config('services.e5.api_url', 'http://localhost:8000');
        $this->similarityThreshold = config('services.e5.similarity_threshold', 0.7);
    }

    protected function getKeyRows(ApiDataTypeEnum $type): array
    {
        return match ($type) {
            ApiDataTypeEnum::SPECIALIST => [
                'specialist_name' => 'Имя специалиста',
                'specialization' => 'Специализация',
                'experience' => 'Опыт работы',
                'skills' => 'Навыки',
                'rate' => 'Рейтинг',
            ],
            ApiDataTypeEnum::BUILDER => [
                'builder_name' => 'Название компании',
                'specialization' => 'Профиль работ',
                'experience' => 'Опыт',
                'portfolio' => 'Портфолио',
            ],
            ApiDataTypeEnum::COMPANY => [
                'company_name' => 'Название компании',
                'industry' => 'Отрасль',
                'size' => 'Размер',
                'description' => 'Описание',
            ],
        };
    }

    public function getResult(ApiDataTypeEnum $type): array
    {
        // Получить сырые данные из чатов
        $rawData = $this->fetchRawMessages($type);
        
        // Нормализовать по схеме
        $schema = $this->getKeyRows($type);
        $normalized = $this->normalizeData($rawData, $schema);
        
        // Применить фильтрацию через E5
        $filtered = $this->filterByCriteria($normalized, $type);
        
        return $filtered;
    }

    private function fetchRawMessages(ApiDataTypeEnum $type): array
    {
        // Логика получения сообщений из БД (аналогично другим сервисам)
        // ...
    }

    private function normalizeData(array $raw, array $schema): array
    {
        // Общая логика нормализации
        // ...
    }

    private function filterByCriteria(array $data, ApiDataTypeEnum $type): array
    {
        $criteria = $this->getCriteriaForType($type);
        $filtered = [];

        foreach ($data as $item) {
            if ($this->checkCriteria($item, $criteria)) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    public function getEmbeddings(string $text): array
    {
        try {
            $response = Http::post("{$this->apiUrl}/embed", [
                'text' => $text
            ]);

            if ($response->successful()) {
                return $response->json('embedding');
            }

            Log::error('E5 API error: ' . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error('E5 connection error: ' . $e->getMessage());
            return [];
        }
    }

    public function cosineSimilarity(string $text1, string $text2): float
    {
        try {
            $response = Http::post("{$this->apiUrl}/similarity", [
                'text1' => $text1,
                'text2' => $text2
            ]);

            if ($response->successful()) {
                return $response->json('similarity');
            }

            Log::error('E5 similarity API error: ' . $response->body());
            return 0.0;
        } catch (\Exception $e) {
            Log::error('E5 similarity connection error: ' . $e->getMessage());
            return 0.0;
        }
    }

    public function checkCriteria(array $item, array $criteria): bool
    {
        foreach ($criteria as $field => $criterion) {
            if (!isset($item[$field]) || empty($item[$field])) {
                continue;
            }

            $similarity = $this->cosineSimilarity($item[$field], $criterion);
            
            if ($similarity < $this->similarityThreshold) {
                return false;
            }
        }

        return true;
    }

    private function getCriteriaForType(ApiDataTypeEnum $type): array
    {
        return match ($type) {
            ApiDataTypeEnum::SPECIALIST => [
                'specialization' => 'строительство ремонт отделка',
                'experience' => 'опыт работа лет',
            ],
            ApiDataTypeEnum::BUILDER => [
                'specialization' => 'строительство ремонт дом квартира',
            ],
            ApiDataTypeEnum::COMPANY => [
                'industry' => 'строительство недвижимость производство',
            ],
        };
    }
}
```

---

## 🔧 Этап 3: Интеграция в существующую систему

### Шаг 3.1: Обновить enum ApiAiSourceEnum
**Файл:** `app/Enum/ApiAiSourceEnum.php`

Добавить:
```php
case E5Local = 'e5local';

// В методе toString():
self::E5Local => 'E5 Base Multilingual (локально)',

// В методе getColor():
self::E5Local => 'info',
```

### Шаг 3.2: Обновить AI-команды
**Файлы:** 
- `app/Console/Commands/AiSpecialistPosts.php`
- `app/Console/Commands/AiBuilderPosts.php`
- `app/Console/Commands/AiCompanyPosts.php`

Добавить в каждую команду:
```php
elseif ($apiSource === ApiAiSourceEnum::E5Local) {
    $aiService = new ApiAIE5;
}
```

### Шаг 3.3: Обновить MoonShine UI
**Файл:** `app/MoonShine/Resources/ApiAiResource.php`

Добавить в hint для JSON-поля документацию по E5Local.

---

## 🔧 Этап 4: Конфигурация

### Шаг 4.1: Добавить переменные окружения
**Файл:** `.env`
```bash
E5_API_URL=http://localhost:8000
E5_SIMILARITY_THRESHOLD=0.7
```

### Шаг 4.2: Обновить config/services.php
```php
'e5' => [
    'api_url' => env('E5_API_URL', 'http://localhost:8000'),
    'similarity_threshold' => env('E5_SIMILARITY_THRESHOLD', 0.7),
],
```

### Шаг 4.3: Обновить .env.example
Добавить секцию с примерами значений.

---

## 🔧 Этап 5: Тестирование

### Шаг 5.1: Unit тесты
```bash
php artisan make:test ApiAIE5Test
```

Тестировать:
- Подключение к API
- Получение embeddings
- Расчёт similarity
- Фильтрацию по критериям

### Шаг 5.2: Integration тесты
- Запустить полный цикл парсинга с E5Local
- Сравнить результаты с YandexGPT/Ollama
- Проверить производительность

### Шаг 5.3: Load тесты
- Проверить работу при 100+ одновременных запросах
- Измерить latency
- Настроить pooling соединений

---

## 🔧 Этап 6: Мониторинг и отладка

### Шаг 6.1: Логирование
Добавить детальное логирование:
- Время ответа API
- Количество запросов
- Ошибки подключения
- Значения similarity

### Шаг 6.2: Метрики
- Среднее время обработки сообщения
- Процент успешных запросов
- Использование памяти Python процессом

### Шаг 6.3: Alerting
Настроить уведомления при:
- Недоступности API > 5 минут
- Падении similarity ниже порога
- Ошибках сериализации данных

---

## 📊 Оценка ресурсов

| Ресурс | Требование | Примечание |
|--------|------------|------------|
| RAM | 2-4 GB | Для модели E5 |
| CPU | 2+ ядра | Для инференса |
| Disk | 1-2 GB | Модель + кэш |
| Network | localhost | Минимальная задержка |

---

## ⚠️ Риски и митигация

| Риск | Вероятность | Митигация |
|------|-------------|-----------|
| Медленный инференс | Средняя | Кэширование embeddings, batching |
| Недостаточно памяти | Низкая | Использовать quantized версию модели |
| Неточные результаты | Средняя | Настройка threshold, fine-tuning |
| API недоступен | Низкая | Fallback на Ollama/Yandex |

---

## ✅ Чеклист готовности

- [ ] Python API сервер развёрнут и доступен
- [ ] Класс ApiAIE5 реализован полностью
- [ ] Enum обновлён
- [ ] Все AI-команды поддерживают E5Local
- [ ] Конфигурация добавлена
- [ ] Написаны тесты (покрытие > 80%)
- [ ] Документация обновлена
- [ ] Настроен мониторинг
- [ ] Проведено нагрузочное тестирование
- [ ] Есть план отката (rollback)

---

## 📝 Примечания

- Не начинать реализацию до завершения приоритетных задач (пункты 7-11 основного плана)
- Требуется отдельный сервер/контейнер для Python API
- Модель E5 требует больше памяти чем Qwen2.5 через Ollama
- Рекомендуется использовать quantized версию модели для экономии ресурсов

---

**Дата создания:** {{CURRENT_DATE}}  
**Статус:** Ожидает реализации  
**Ответственный:** TBD
