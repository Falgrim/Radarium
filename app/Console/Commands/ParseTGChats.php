<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ParseTGChats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tg_parse';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Скрипт парсинга чатов ТГ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $settings = (new \danog\MadelineProto\Settings\AppInfo)
            ->setApiId(25969424)
            ->setApiHash('4a6a2be56a49a7439059a74aab4d3e33');

        $logSettings = (new \danog\MadelineProto\Settings\Logger)
            ->setLevel(0);

        $MadelineProto = new \danog\MadelineProto\API('session.madeline', $settings);
        $MadelineProto->updateSettings($logSettings);
        $MadelineProto->start();

        $messages = $MadelineProto->messages->getHistory([
            'peer'          => 'https://t.me/PetrashevBIM_HR/53129',
            //'peer'  => '251968498',
            'offset_id'     => 0,
            'offset_date'   => 0,
            'add_offset'    => 0,
            'limit'         => 20,
            'max_id'        => 0,
            'min_id'        => 0,
            'hash'          => 0,
        ]);

        /* Сообщения, сортировка по дате (новые сверху) */
        $messages = array_reverse($messages['messages']);
        //print_r($messages);
    }
}
