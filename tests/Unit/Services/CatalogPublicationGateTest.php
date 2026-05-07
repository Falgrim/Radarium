<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Services\CatalogPublicationGate;
use Tests\TestCase;

final class CatalogPublicationGateTest extends TestCase
{
    public function test_when_disabled_always_active(): void
    {
        config(['catalog_publication_gate.enabled' => false]);

        $gate = new CatalogPublicationGate;
        $out = $gate->decide(ApiDataTypeEnum::Builder, 'текст', []);

        $this->assertSame(ApiPostAiStatusEnum::Active, $out['status']);
        $this->assertSame([], $out['reasons']);
    }

    public function test_when_enabled_and_no_speciality_is_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $out = $gate->decide(ApiDataTypeEnum::Builder, 'текст', []);

        $this->assertSame(ApiPostAiStatusEnum::InModeration, $out['status']);
        $this->assertContains('no_dictionary_speciality_matched', $out['reasons']);
    }

    public function test_when_enabled_and_has_speciality_is_active(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $out = $gate->decide(ApiDataTypeEnum::Specialist, 'текст', [101]);

        $this->assertSame(ApiPostAiStatusEnum::Active, $out['status']);
        $this->assertSame([], $out['reasons']);
    }

    public function test_require_speciality_off_allows_empty_matches(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => false,
        ]);

        $gate = new CatalogPublicationGate;
        $out = $gate->decide(ApiDataTypeEnum::Builder, 'текст', []);

        $this->assertSame(ApiPostAiStatusEnum::Active, $out['status']);
    }

    public function test_logs_to_catalog_publication_gate_file_when_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
        ]);

        $token = 'gate_test_token_'.uniqid('', true);

        $gate = new CatalogPublicationGate;
        $gate->decide(
            ApiDataTypeEnum::Builder,
            'текст поста '.$token,
            [],
            ['test_token' => $token],
        );

        $path = storage_path('logs/catalog_publication_gate-'.date('Y-m-d').'.log');
        $this->assertFileExists($path);
        $this->assertStringContainsString($token, (string) file_get_contents($path));
    }

    public function test_builder_hiring_text_goes_to_moderation_even_with_speciality(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $post = 'Требуется помощник - строитель. Оплата 500 руб./час.';

        $out = $gate->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::InModeration, $out['status']);
        $this->assertContains('post_text_hiring_or_staffing_signal', $out['reasons']);
    }

    public function test_builder_short_customer_order_goes_to_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $post = 'Добрый день, нужно смонтировать водосток -40м/п в лс';

        $out = $gate->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::InModeration, $out['status']);
        $this->assertContains('post_text_customer_request_without_offer', $out['reasons']);
    }

    public function test_builder_mass_hire_goes_to_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $post = 'Требуются: Моляры. Бригада 10-15 человек. Аванс на 3й день.';

        $out = $gate->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::InModeration, $out['status']);
        $this->assertContains('post_text_hiring_or_staffing_signal', $out['reasons']);
    }

    public function test_builder_heuristic_disabled_allows_active_with_speciality(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => false,
        ]);

        $gate = new CatalogPublicationGate;
        $post = 'Требуются: Моляры. Бригада 10-15 человек.';

        $out = $gate->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::Active, $out['status']);
        $this->assertSame([], $out['reasons']);
    }

    public function test_builder_service_offer_stays_active(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $post = 'Мы бригада электриков, ищем заказ в Мурино, выполняем монтаж по проекту, звоните.';

        $out = $gate->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::Active, $out['status']);
        $this->assertSame([], $out['reasons']);
    }

    public function test_builder_need_to_produce_customer_order_goes_to_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $post = 'Добрый день! Нужно произвести укладку тротуарной плитки, около 10м2, на пескоцемент, всё на месте, оплата 10000р';

        $out = $gate->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::InModeration, $out['status']);
        $this->assertContains('post_text_customer_request_without_offer', $out['reasons']);
    }

    public function test_builder_facade_package_hiring_goes_to_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $post = 'Есть проживание, инструмент выдается, выплаты два раза в месяц, аванс на 3 день. Объем фасада 1000м2.';

        $out = $gate->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::InModeration, $out['status']);
        $this->assertContains('post_text_hiring_or_staffing_signal', $out['reasons']);
    }

    public function test_builder_gig_spec_bundle_payment_volume_place_goes_to_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $post = <<<'TXT'
1200р/м²
шпаклевка, покраска
Объем 800м²
Сестрорецк
ОПЛАТА ПО ФАКТУ СДЕЛАННОГО
TXT;

        $out = $gate->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::InModeration, $out['status']);
        $this->assertContains('post_text_gig_payment_volume_place_or_date', $out['reasons']);
    }

    public function test_builder_price_and_volume_without_place_or_date_stays_active(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $post = 'Делаем отделку, цена 900 руб/м², типовые объекты от 50 м², качество, звоните.';

        $out = $gate->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::Active, $out['status']);
        $this->assertSame([], $out['reasons']);
    }

    public function test_builder_user_sample_facade_hiring_goes_to_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => true,
        ]);

        $post = <<<'TXT'
м.Долгопрудная
Максимальная высота 4 этаж.
Кронштейны 400р
Утеплитель 550 2 слоя
Направляющие 400
Композитные кассеты 1500 ( небольшие)
Керамогранитная плитка 1100
Откосы оцинковка 400 р/м.п.
Отливы 250р/м.п.
Начальный обьем 1000м².
Основная работа с кассетой и керамогранитом,большая часть подсистемы сделана
Есть проживание, инструмент выдается, аванс на 3 день работы, выплаты два раза в месяц
TXT;

        $out = (new CatalogPublicationGate)->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::InModeration, $out['status']);
        $this->assertNotEmpty($out['reasons']);
    }

    public function test_builder_user_sample_helper_murino_goes_to_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => true,
        ]);

        $post = <<<'TXT'
Всем привет.
Мурино - Девяткино.
Возможно предложить будет интересно соседям по району.
Требуется помощник - строитель (самостоятельный и без выхлопа)
Оплата из расчета 500 руб./час.
Работа по 4 часа 3 раза в неделю.
TXT;

        $out = (new CatalogPublicationGate)->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::InModeration, $out['status']);
        $this->assertNotEmpty($out['reasons']);
    }

    public function test_builder_user_sample_demolition_telegram_goes_to_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
            'catalog_publication_gate.builder_non_service_heuristics' => true,
        ]);

        $post = <<<'TXT'
Еще одного
С завтрашнего дня
К 9:00
2 человека (трезвые , РФ)
Каменный остров
Сбивать штукатурку перфоратором до кирпича(только сбивать, собирать не нужно)
Инструмент предоставляется
Оплата 100р с квадрата
Выплаты каждый этаж (300кв примерно на этаже )
Работы на неделю-две
Писать в лс

https://t.me/mobilepersonnel
TXT;

        $out = (new CatalogPublicationGate)->decide(ApiDataTypeEnum::Builder, $post, [101]);

        $this->assertSame(ApiPostAiStatusEnum::InModeration, $out['status']);
        $this->assertNotEmpty($out['reasons']);
    }
}
