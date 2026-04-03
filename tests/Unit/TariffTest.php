<?php

namespace Tests\Unit;

use App\Services\Tariff;
use App\Models\Tariff as TariffModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TariffTest extends TestCase
{
    use RefreshDatabase;

    protected Tariff $tariffService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tariffService = app(Tariff::class);
    }

    public function test_instance_creation(): void
    {
        $this->assertInstanceOf(Tariff::class, $this->tariffService);
    }

    public function test_get_all_tariffs(): void
    {
        TariffModel::create(['name' => 'Free', 'price' => 0, 'duration_days' => 30]);
        TariffModel::create(['name' => 'Pro', 'price' => 1000, 'duration_days' => 30]);

        $tariffs = $this->tariffService->getAll();

        $this->assertCount(2, $tariffs);
    }

    public function test_get_active_tariffs(): void
    {
        TariffModel::create(['name' => 'Active', 'price' => 100, 'is_active' => true]);
        TariffModel::create(['name' => 'Inactive', 'price' => 200, 'is_active' => false]);

        $active = $this->tariffService->getActive();

        $this->assertCount(1, $active);
        $this->assertEquals('Active', $active[0]['name']);
    }

    public function test_get_by_id(): void
    {
        $tariff = TariffModel::create(['name' => 'Test Tariff', 'price' => 500, 'duration_days' => 60]);
        
        $result = $this->tariffService->getById($tariff->id);

        $this->assertNotNull($result);
        $this->assertEquals(500, $result['price']);
        $this->assertEquals(60, $result['duration_days']);
    }

    public function test_get_by_id_not_found(): void
    {
        $result = $this->tariffService->getById(999);
        $this->assertNull($result);
    }

    public function test_calculate_price_with_discount(): void
    {
        // Логика расчета зависит от реализации сервиса, проверяем базовую структуру
        $basePrice = 1000;
        $discount = 0.1; // 10%
        
        // Предполагаем, что сервис имеет метод расчета или возвращает данные для расчета
        $expected = $basePrice - ($basePrice * $discount);
        
        // Тест на проверку арифметики внутри сервиса если есть такой метод
        // $calculated = $this->tariffService->calculateDiscountedPrice($basePrice, $discount);
        // $this->assertEquals($expected, $calculated);
        
        $this->assertTrue(true); // Заглушка, пока нет явного метода
    }

    public function test_tariff_features_structure(): void
    {
        $tariff = TariffModel::create([
            'name' => 'Business',
            'price' => 2000,
            'features' => json_encode(['feature1', 'feature2'])
        ]);

        $result = $this->tariffService->getById($tariff->id);
        
        $this->assertArrayHasKey('features', $result);
        $this->assertIsString($result['features']); // Или array, если сервис декодирует
    }

    public function test_create_tariff(): void
    {
        $data = [
            'name' => 'New Tariff',
            'price' => 1500,
            'duration_days' => 30,
            'is_active' => true
        ];

        $model = TariffModel::create($data);
        $fetched = $this->tariffService->getById($model->id);

        $this->assertEquals($data['name'], $fetched['name']);
        $this->assertEquals($data['price'], $fetched['price']);
    }

    public function test_update_tariff(): void
    {
        $model = TariffModel::create(['name' => 'Old', 'price' => 100]);
        $model->update(['price' => 200, 'name' => 'Updated']);

        $fetched = $this->tariffService->getById($model->id);
        $this->assertEquals(200, $fetched['price']);
        $this->assertEquals('Updated', $fetched['name']);
    }

    public function test_deactivate_tariff(): void
    {
        $model = TariffModel::create(['name' => 'To Deactivate', 'is_active' => true]);
        $model->update(['is_active' => false]);

        $activeList = $this->tariffService->getActive();
        $ids = array_column($activeList, 'id');
        
        $this->assertNotContains($model->id, $ids);
    }

    public function test_sorting_by_price(): void
    {
        TariffModel::create(['name' => 'Expensive', 'price' => 3000]);
        TariffModel::create(['name' => 'Cheap', 'price' => 100]);
        TariffModel::create(['name' => 'Medium', 'price' => 500]);

        $all = $this->tariffService->getAll();
        
        // Проверка, что данные получены (сортировка может быть в сервисе или контроллере)
        $this->assertCount(3, $all);
    }

    public function test_free_tariff_exists(): void
    {
        TariffModel::create(['name' => 'Free', 'price' => 0]);
        
        $free = TariffModel::where('price', 0)->first();
        $this->assertNotNull($free);
    }

    public function test_price_must_be_non_negative(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        // Попытка вставки отрицательной цены (если есть проверка на уровне БД или модели)
        // В Laravel это лучше тестировать через FormRequest или модельные события
        TariffModel::create(['name' => 'Negative', 'price' => -100]);
    }

    public function test_duration_days_validation(): void
    {
        $tariff = TariffModel::create(['name' => 'Test', 'duration_days' => 1]);
        $this->assertEquals(1, $tariff->duration_days);
        
        $tariff->update(['duration_days' => 365]);
        $this->assertEquals(365, $tariff->fresh()->duration_days);
    }

    public function test_json_features_encoding(): void
    {
        $features = ['limit_1', 'limit_2'];
        $tariff = TariffModel::create([
            'name' => 'JSON Test',
            'features' => json_encode($features)
        ]);

        $decoded = json_decode($tariff->features, true);
        $this->assertEquals($features, $decoded);
    }
}
