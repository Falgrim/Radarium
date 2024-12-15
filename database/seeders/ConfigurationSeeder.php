<?php

namespace Database\Seeders;

use App\Models\Configuration;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        dump('Configuration seeder is running...');
        $time_start = microtime(true);

        $this->createConfigurations();

        $time_end = microtime(true);
        dump('Configuration created in ' . round($time_end - $time_start, 2) . ' seconds');
    }

    private function createConfigurations(): void
    {
        $maxOrder = 1;

        $configs = [];
        $configs[] = [
            'title' => 'Количество сообщений для парсинга за 1 запуск',
            'name' => 'cron_count_posts',
            'type' => 'string',
            'value' => 50,
            'order' => ++$maxOrder,
            'options' => '',
            'title_hint' => 'Учитывайте, что, если чат находится в отдельной теме группы, то скрипт прочитает указанное кол-во сообщений и средни них будет выбирать нужные из конкретного чата/топика.',
        ];

        $configs[] = [
            'title' => 'Разрешить регистрации на сайте',
            'name' => 'allow_registration',
            'type' => 'checkbox',
            'value' => 1,
            'order' => ++$maxOrder,
            'options' => '',
            'title_hint' => '',
        ];

        foreach ($configs as $config) {
            Configuration::updateOrCreate([
                'name' => $config['name']
            ], [
                ...$config
            ]);
        }
    }
}
