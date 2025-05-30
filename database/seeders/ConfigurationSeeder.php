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

        $configs[] = [
            'title' => 'Минимальная длина сообщения для парсинга',
            'name' => 'min_length_post',
            'type' => 'string',
            'value' => 20,
            'order' => ++$maxOrder,
            'options' => '',
            'title_hint' => 'Если сообщение будет меньше установленного лимита, то оно будет пропущено',
        ];

        $configs[] = [
            'title' => 'Частота чтения каналов',
            'name' => 'read_source_cron',
            'type' => 'select',
            'value' => 30,
            'order' => ++$maxOrder,
            'options' => serialize([
                10 => 'Каждые 10 минут',
                30 => 'Каждые 30 минут',
                60 => 'Каждый час',
                120 => 'Каждые 2 часа',
                180 => 'Каждые 3 часа',
                360 => 'Каждые 6 часов',
                720 => 'Каждые 12 часов',
                1440 => 'Каждый день',
            ]),
            'title_hint' => 'Выберите как часто вы хотите, чтобы система обновляла информацию о новых сообщениях из источников',
        ];

        $configs[] = [
            'title' => 'Отправка приветствия для компаний',
            'name' => 'mailing_tg_new_company',
            'type' => 'checkbox',
            'value' => 1,
            'order' => ++$maxOrder,
            'options' => '',
            'title_hint' => 'Отправлять новым контактам "Компания" текст приветствия. Текст настраивается в разделе "Продвижение -> Рассылка"',
        ];

        $configs[] = [
            'title' => 'ТГ Бот для отправки сообщений. App ID',
            'name' => 'mailing_tg_api_id',
            'type' => 'string',
            'value' => '',
            'order' => ++$maxOrder,
            'options' => '',
            'title_hint' => '',
        ];

        $configs[] = [
            'title' => 'ТГ Бот для отправки сообщений. App Hash',
            'name' => 'mailing_tg_api_hash',
            'type' => 'string',
            'value' => '',
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
