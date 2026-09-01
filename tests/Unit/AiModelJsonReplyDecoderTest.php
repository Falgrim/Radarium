<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\AiContentRefusalException;
use App\Services\AiModelJsonReplyDecoder;
use App\Services\BuilderNormalizer;
use Tests\TestCase;

class AiModelJsonReplyDecoderTest extends TestCase
{
    public function test_decodes_plain_json_object(): void
    {
        $out = AiModelJsonReplyDecoder::decode('{"type":"x","reason":"y"}', '[t]');
        $this->assertSame('x', $out['type']);
        $this->assertSame('y', $out['reason']);
    }

    public function test_decodes_json_after_preamble(): void
    {
        $raw = "Вот результат:\n{\"type\":\"резюме\",\"reason\":\"ok\"}";
        $out = AiModelJsonReplyDecoder::decode($raw, '[t]');
        $this->assertSame('резюме', $out['type']);
    }

    public function test_decodes_json_embedded_in_text(): void
    {
        $raw = 'Пояснение. {"type":"a","reason":"b"} Конец.';
        $out = AiModelJsonReplyDecoder::decode($raw, '[t]');
        $this->assertSame('a', $out['type']);
    }

    public function test_invalid_json_throws_with_message(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('не является валидным JSON');
        AiModelJsonReplyDecoder::decode('not json { broken', '[t]');
    }

    public function test_safety_refusal_throws_ai_content_refusal(): void
    {
        $this->expectException(AiContentRefusalException::class);
        $this->expectExceptionMessage('Отказ модели (safety)');
        AiModelJsonReplyDecoder::decode(
            'Я не могу обсуждать эту тему. Давайте поговорим о чём-нибудь ещё.',
            '[YandexGPT]'
        );
    }

    public function test_clean_price_nulls_out_of_mysql_int_range(): void
    {
        $this->assertSame(5000, BuilderNormalizer::cleanPrice('5000 руб'));
        $this->assertNull(BuilderNormalizer::cleanPrice('800210210000'));
        $this->assertNull(BuilderNormalizer::cleanPrice('8506051000'));
        $this->assertSame(BuilderNormalizer::MYSQL_INT_MAX, BuilderNormalizer::cleanPrice((string) BuilderNormalizer::MYSQL_INT_MAX));
    }

    public function test_sanitize_price_fields_nulls_overflow(): void
    {
        $out = BuilderNormalizer::sanitizePriceFields([
            'price_by_project' => 8506051000,
            'price_by_hour' => '1500',
            'price_by_month' => '?',
        ]);
        $this->assertNull($out['price_by_project']);
        $this->assertSame(1500, $out['price_by_hour']);
        $this->assertNull($out['price_by_month']);
    }
}
