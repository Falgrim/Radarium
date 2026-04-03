<?php

namespace Tests\Unit;

use App\Services\Dictionary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DictionaryTest extends TestCase
{
    use RefreshDatabase;

    protected Dictionary $dictionary;

    protected function setUp(): void
    {
        parent::setUp();
        // Инициализация сервиса (зависимости будут мокироваться или браться из контейнера)
        $this->dictionary = app(Dictionary::class);
    }

    public function test_instance_creation(): void
    {
        $this->assertInstanceOf(Dictionary::class, $this->dictionary);
    }

    public function test_get_list_returns_array(): void
    {
        $result = $this->dictionary->getList();
        $this->assertIsArray($result);
    }

    public function test_get_list_structure(): void
    {
        $result = $this->dictionary->getList();
        
        if (!empty($result)) {
            $firstItem = reset($result);
            $this->assertArrayHasKey('id', $firstItem);
            $this->assertArrayHasKey('name', $firstItem);
            $this->assertArrayHasKey('type', $firstItem);
        }
    }

    public function test_get_by_id_existing(): void
    {
        // Создадим тестовую запись
        $item = \App\Models\Dictionary::create([
            'name' => 'Test Item',
            'type' => 'test_type',
            'value' => 'test_value'
        ]);

        $result = $this->dictionary->getById($item->id);
        
        $this->assertNotNull($result);
        $this->assertEquals('Test Item', $result['name']);
    }

    public function test_get_by_id_non_existing(): void
    {
        $result = $this->dictionary->getById(999999);
        $this->assertNull($result);
    }

    public function test_get_types_returns_unique_types(): void
    {
        \App\Models\Dictionary::create(['name' => 'Item 1', 'type' => 'type_a', 'value' => 'v1']);
        \App\Models\Dictionary::create(['name' => 'Item 2', 'type' => 'type_b', 'value' => 'v2']);
        \App\Models\Dictionary::create(['name' => 'Item 3', 'type' => 'type_a', 'value' => 'v3']);

        $types = $this->dictionary->getTypes();

        $this->assertContains('type_a', $types);
        $this->assertContains('type_b', $types);
        $this->assertEquals(2, count($types)); // Уникальные типы
    }

    public function test_find_by_type(): void
    {
        \App\Models\Dictionary::create(['name' => 'Item A', 'type' => 'filter_type', 'value' => 'val_a']);
        \App\Models\Dictionary::create(['name' => 'Item B', 'type' => 'filter_type', 'value' => 'val_b']);
        \App\Models\Dictionary::create(['name' => 'Item C', 'type' => 'other_type', 'value' => 'val_c']);

        $result = $this->dictionary->findByType('filter_type');

        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertEquals('filter_type', $item['type']);
        }
    }

    public function test_cache_key_generation(): void
    {
        // Проверка внутренней логики генерации ключей кэша (если метод публичный или через рефлексию)
        // Здесь тестируем поведение метода getList с кэшем
        $firstCall = $this->dictionary->getList();
        $secondCall = $this->dictionary->getList();

        $this->assertEquals($firstCall, $secondCall);
    }

    public function test_create_item(): void
    {
        $data = [
            'name' => 'New Dict Item',
            'type' => 'new_type',
            'value' => 'new_value'
        ];

        // Если в сервисе есть метод create, иначе создаем через модель напрямую для теста сервиса
        $model = \App\Models\Dictionary::create($data);
        $fetched = $this->dictionary->getById($model->id);

        $this->assertEquals($data['name'], $fetched['name']);
    }

    public function test_update_item(): void
    {
        $model = \App\Models\Dictionary::create([
            'name' => 'Old Name',
            'type' => 'test',
            'value' => 'old'
        ]);

        $model->update(['name' => 'Updated Name']);
        
        $fetched = $this->dictionary->getById($model->id);
        $this->assertEquals('Updated Name', $fetched['name']);
    }

    public function test_delete_item(): void
    {
        $model = \App\Models\Dictionary::create([
            'name' => 'To Delete',
            'type' => 'test',
            'value' => 'x'
        ]);

        $model->delete();
        
        $fetched = $this->dictionary->getById($model->id);
        $this->assertNull($fetched);
    }

    public function test_empty_table_behavior(): void
    {
        \App\Models\Dictionary::truncate();
        
        $list = $this->dictionary->getList();
        $this->assertEmpty($list);
        
        $types = $this->dictionary->getTypes();
        $this->assertEmpty($types);
    }
}
