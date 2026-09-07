<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AiModelValueNormalizer;
use Tests\TestCase;

class AiModelValueNormalizerTest extends TestCase
{
    public function test_text_keeps_plain_string(): void
    {
        $this->assertSame('5 лет на объектах', AiModelValueNormalizer::toText('  5 лет на объектах  '));
    }

    public function test_text_joins_list_of_strings(): void
    {
        $this->assertSame(
            'работа с заказчиками; проекты раздела АР',
            AiModelValueNormalizer::toText(['работа с заказчиками', 'проекты раздела АР'])
        );
    }

    /**
     * Ответ модели по скалярному полю иногда приходит вложенной структурой — раньше implode() падал
     * с «Array to string conversion» и пост уходил в Error.
     */
    public function test_text_flattens_nested_structure(): void
    {
        $value = [
            ['role' => 'прораб', 'years' => 5],
            ['role' => 'мастер СМР'],
        ];

        $this->assertSame('прораб; 5; мастер СМР', AiModelValueNormalizer::toText($value));
    }

    public function test_text_drops_empty_leaves(): void
    {
        $this->assertSame('ArchiCAD', AiModelValueNormalizer::toText(['', null, 'ArchiCAD', []]));
    }

    public function test_text_of_null_is_empty_string(): void
    {
        $this->assertSame('', AiModelValueNormalizer::toText(null));
    }

    public function test_contact_text_reads_object_of_pairs(): void
    {
        $this->assertSame(
            'telegram: @ivan; phone: +79990000000',
            AiModelValueNormalizer::toContactText(['telegram' => '@ivan', 'phone' => '+79990000000'])
        );
    }

    public function test_contact_text_reads_list_of_objects(): void
    {
        $value = [
            ['telegram' => '@ivan'],
            ['phone' => '+79990000000'],
        ];

        $this->assertSame('telegram: @ivan; phone: +79990000000', AiModelValueNormalizer::toContactText($value));
    }

    /**
     * Главная причина падений: по полю типа array_string модель присылала готовую строку,
     * прежний код брал её первый символ и отдавал в foreach.
     */
    public function test_contact_text_accepts_plain_string(): void
    {
        $this->assertSame('@ivan', AiModelValueNormalizer::toContactText('@ivan'));
    }

    public function test_contact_text_of_list_of_strings_has_no_numeric_keys(): void
    {
        $this->assertSame('@ivan; +79990000000', AiModelValueNormalizer::toContactText(['@ivan', '+79990000000']));
    }

    public function test_contact_text_of_empty_array_is_empty_string(): void
    {
        $this->assertSame('', AiModelValueNormalizer::toContactText([]));
    }
}
