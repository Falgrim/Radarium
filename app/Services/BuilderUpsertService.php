<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Builder;

/**
 * Upsert Builder по api_channel_post_id без delete+create.
 *
 * При повторной обработке одного и того же поста перезаписываются только «авто»-поля
 * (те, что корректно заполняются ИИ). Модераторские поля и метаданные (status, about,
 * contact_info, spec_requirements, post_date, api_post_user_id, api_channel_post_id)
 * не перезаписываются, если у найденного билдера они уже задействованы.
 */
final class BuilderUpsertService
{
    /**
     * Поля, которые всегда перезаписываются ответом ИИ при повторной обработке.
     * Это «технические» данные (распознанная категория, тарификация, типы объектов и т.п.).
     */
    private const AUTO_FIELDS = [
        'experience',
        'soft_experience',
        'education',
        'work_schedule',
        'total_work_project',
        'type_of_work',
        'price_by_hour',
        'price_by_project',
        'price_by_month',
        'link_resume',
        'ai_type',
        'ai_reason',
        'region',
        'service_type_raw',
        'service_types',
        'object_types',
        'performer_type_raw',
        'performer_type',
        'equipment_skills_json',
        'legal_form',
        'location_city',
        'location_region',
        'price_comment',
    ];

    /**
     * Поля, которые заполняются только один раз (на создании).
     * При повторной обработке не перезаписываются, чтобы не терять ручные правки модератора.
     */
    private const FIRST_CREATE_ONLY_FIELDS = [
        'status',
        'about',
        'spec_requirements',
        'contact_info',
    ];

    /**
     * Поля-идентификаторы поста/автора. При создании всегда задаются из входа,
     * при обновлении — не меняются (post_date, api_post_user_id, api_channel_post_id
     * логически неизменяемы для уже созданного билдера).
     */
    private const POST_IDENTITY_FIELDS = [
        'api_post_user_id',
        'api_channel_post_id',
        'post_date',
    ];

    /**
     * @param  array<string, mixed>  $payload  Нормализованные поля для Builder (как перед Builder::create()).
     * @return array{builder: Builder, created: bool, changed: list<string>}
     */
    public function upsert(array $payload): array
    {
        $payload = BuilderNormalizer::sanitizePriceFields($payload);

        $postId = $payload['api_channel_post_id'] ?? null;
        if ($postId === null || $postId === '') {
            throw new \InvalidArgumentException('BuilderUpsertService: отсутствует api_channel_post_id в payload');
        }

        $existing = Builder::query()
            ->where('api_channel_post_id', (int) $postId)
            ->first();

        if ($existing === null) {
            $createPayload = $this->filterCreatePayload($payload);
            $builder = Builder::create($createPayload);

            return [
                'builder' => $builder,
                'created' => true,
                'changed' => array_keys($createPayload),
            ];
        }

        $changed = [];
        foreach (self::AUTO_FIELDS as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }
            $newValue = $payload[$field];
            $currentValue = $existing->getAttribute($field);

            if ($this->valuesEqual($currentValue, $newValue)) {
                continue;
            }

            $existing->setAttribute($field, $newValue);
            $changed[] = $field;
        }

        if ($changed !== []) {
            $existing->save();
        }

        return [
            'builder' => $existing->refresh(),
            'created' => false,
            'changed' => $changed,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterCreatePayload(array $payload): array
    {
        $allowed = array_merge(self::AUTO_FIELDS, self::FIRST_CREATE_ONLY_FIELDS, self::POST_IDENTITY_FIELDS);
        $allowed = array_flip($allowed);

        return array_intersect_key($payload, $allowed);
    }

    private function valuesEqual(mixed $a, mixed $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return json_encode($a) === json_encode($b);
        }

        return (string) $a === (string) $b;
    }
}
