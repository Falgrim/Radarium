<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AuthTelegram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tg_auth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Скрипт регистрации приложения TG';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $MadelineProto = new \danog\MadelineProto\API('session.madeline');
        $MadelineProto->start();

        $me = $MadelineProto->getSelf();

        $MadelineProto->logger($me);

        if (!$me['bot']) {
            $MadelineProto->messages->sendMessage(peer: '@stickeroptimizerbot', message: "/start");

            $MadelineProto->channels->joinChannel(channel: '@MadelineProto');

            try {
                $MadelineProto->messages->importChatInvite(hash: 'https://t.me/+Por5orOjwgccnt2w');
            } catch (\danog\MadelineProto\RPCErrorException $e) {
                $MadelineProto->logger($e);
            }
        }
        $MadelineProto->echo('OK, done!');
    }
}
