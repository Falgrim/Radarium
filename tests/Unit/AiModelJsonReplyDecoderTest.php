<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AiModelJsonReplyDecoder;
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
        $this->expectExceptionMessage('Невалидный JSON');
        AiModelJsonReplyDecoder::decode('not json { broken', '[t]');
    }
}
