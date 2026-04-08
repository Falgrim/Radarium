<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiAi;
use App\Models\ApiChannel;
use Illuminate\Database\Seeder;

/**
 * 13 публичных VK-сообществ (строительные биржи). Тип выборки: строители (Builder).
 * Запуск: php artisan db:seed --class=VkConstructionGroupsChannelSeeder
 *
 * Токен VK задаётся в .env (VK_SERVICE_TOKEN); в options секреты не хранятся.
 */
class VkConstructionGroupsChannelSeeder extends Seeder
{
    public function run(): void
    {
        $apiAi = ApiAi::query()->first();
        if ($apiAi === null) {
            $apiAi = ApiAi::query()->create([
                'title' => 'Сервис ИИ (заглушка для VK-источников)',
                'description' => 'Создано VkConstructionGroupsChannelSeeder; замените на реальный сервис в админке.',
                'api_source' => ApiAiSourceEnum::OllamaQwen->value,
                'options' => [],
                'status' => 1,
                'balance_sum' => 0,
                'date_balance' => null,
            ]);
        }

        $groups = [
            ['title' => 'VK: Строители МСК', 'link' => 'https://vk.com/stroiteli_msk', 'region' => 'Москва'],
            ['title' => 'VK: Бригады строителей', 'link' => 'https://vk.com/brigadastroitelei', 'region' => 'Москва'],
            ['title' => 'VK: Стройбиржа', 'link' => 'https://vk.com/stroybirzharu', 'region' => 'Москва'],
            ['title' => 'VK: Сообщество club164811551', 'link' => 'https://vk.com/club164811551', 'region' => 'Москва'],
            ['title' => 'VK: Стройка City', 'link' => 'https://vk.com/stroykacity', 'region' => 'Москва'],
            ['title' => 'VK: Строим Москва', 'link' => 'https://vk.com/stroim.moscow', 'region' => 'Москва'],
            ['title' => 'VK: Строители', 'link' => 'https://vk.com/stroitelli', 'region' => 'Москва'],
            ['title' => 'VK: trip_road', 'link' => 'https://vk.com/trip_road', 'region' => 'Москва'],
            ['title' => 'VK: Сообщество club153705757', 'link' => 'https://vk.com/club153705757', 'region' => 'Москва'],
            ['title' => 'VK: Сообщество club191907267', 'link' => 'https://vk.com/club191907267', 'region' => 'Москва'],
            ['title' => 'VK: Сообщество club172567924', 'link' => 'https://vk.com/club172567924', 'region' => 'Москва'],
            ['title' => 'VK: Сообщество club153705922', 'link' => 'https://vk.com/club153705922', 'region' => 'Москва'],
            ['title' => 'VK: Строй Питер', 'link' => 'https://vk.com/stroypiter', 'region' => 'Санкт-Петербург'],
        ];

        foreach ($groups as $row) {
            ApiChannel::query()->updateOrCreate(
                ['link' => $row['link']],
                [
                    'title' => $row['title'],
                    'description' => 'Источник сообщений VK (сидер VkConstructionGroupsChannelSeeder).',
                    'ai_promt' => null,
                    'api_ai_id' => $apiAi->id,
                    'channel_source' => ApiChannelSourceEnum::VK,
                    'options' => [],
                    'status' => ApiChannelStatusEnum::Active,
                    'is_company' => ApiDataTypeEnum::Builder,
                    'region' => $row['region'],
                    'post_from_date' => now()->subMonths(3)->toDateString(),
                ]
            );
        }

        $this->command?->info('VK: создано/обновлено '.count($groups).' источников ApiChannel.');
    }
}
