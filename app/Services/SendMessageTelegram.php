<?php

namespace App\Services;

use Amp\Ipc\Sync\ChannelException;
use App\Enum\ApiChannelPostStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use danog\MadelineProto\PeerNotInDbException;
use danog\MadelineProto\RPCErrorException;
use danog\MadelineProto\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SendMessageTelegram
{
    private ApiChannel $apiChannel;

    protected $cronCountPosts;
    protected $minLengthPost;

    protected array $messages = [
        'info' => [],
        'warn' => [],
        'error' => [],
    ];

    public function __construct() {
    }

    private function unsetMessages()
    {
        $this->messages = [
            'info' => [],
            'warn' => [],
            'error' => [],
        ];
    }

    public function send(): bool
    {
        $this->unsetMessages();

        $tgParams = [
            'api_id' => '25969424',
            'api_hash' => '4a6a2be56a49a7439059a74aab4d3e33',
        ];

        if (!isset($tgParams['api_id']) OR !isset($tgParams['api_hash'])) {
            $this->setWarnMsg('Нет конфигурации бота для чата: нет api_id и/или api_hash');
            return false;
        }

        $settings = new Settings;
        $settings->setAppInfo(
            (new \danog\MadelineProto\Settings\AppInfo)
                ->setApiId($tgParams['api_id'])
                ->setApiHash($tgParams['api_hash'])
                ->setLangCode('RU')
        );

        $settings->getLogger()->setLevel(\danog\MadelineProto\Logger::LEVEL_ERROR);

        if (config('database.redis.default.password')) {
            $settings->setDb(
                (new \danog\MadelineProto\Settings\Database\Redis)
                    ->setUri('redis://' . config('database.redis.default.host'))
                    ->setPassword(config('database.redis.default.password'))
            );
        } else {
            $settings->setDb(
                (new \danog\MadelineProto\Settings\Database\Redis)
                    ->setUri('redis://' . config('database.redis.default.host'))
            );
        }

        $settings->setConnection((new Settings\Connection())->setTimeout(10));
        $settings->setSerialization((new Settings\Serialization())->setInterval(30));

        $MadelineProto = new \danog\MadelineProto\API('session.madeline', $settings);

        if (!$MadelineProto->getSelf()) {
            $MadelineProto->start();
        }

        $message = "🎯 Новый тир для IPSC открыт!   Безопасные тренировки на электронных мишенях!


Уважаемые спортсмены и любители IPSC!

Мы рады сообщить о запуске нового тира с упором на IPSC — дисциплину, где важны не только меткость, но и скорость, тактика и адаптация к меняющимся условиям!


••• Что вас ждёт:
1. Современные электронные мишени — безопасно, реалистично, с мгновенной обратной связью.
2. Холостые тренировки IPSC — идеально для новичков и тех, кто хочет отточить навыки без боевых патронов.


••• Что такое «холостые тренировки IPSC»?
Это практика без живого огня: вместо патронов — лазерные модули и учебные макеты оружия. Электронные мишени фиксируют ваши действия, позволяя:

- 🎯 Тренировать скорость, точность и перемещение между укрытиями;

- 🔄 Отрабатывать сложные сценарии (движение под углом, стрельба в ограниченном пространстве);

- ✅ Безопасно начинать — никакого риска случайного выстрела;

- 💰 Экономить — платите только за время тренировки.


Записывайтесь уже сегодня и погрузитесь в мир динамичной стрельбы!

Ваша безопасность — наш приоритет.

Ждем вас в Смольном Тире!

📍 Адрес: [указать]
📞 Запись: [телефон/ссылка]
⏰ Часы работы: [расписание]

 ✨ IPSC — это не просто стрельба. Это стиль жизни!";

        try {
            // https://docs.madelineproto.xyz/PHP/danog/MadelineProto/API.html#sendMessage
            $sentMessage = $MadelineProto->messages->sendMessage([
                'peer' => '@shapeshifter08',
                'message' => $message,
                'no_webpage' => true,
            ]);

            /*
             * Array
(
    [_] => updateShortSentMessage
    [out] => 1
    [id] => 70
    [pts] => 127
    [pts_count] => 1
    [date] => 1748546555
    [request] => Array
        (
            [_] => messages.sendMessage
            [body] => Array
                (
                    [peer] => @shapeshifter08
                    [message] => 🎯 Новый тир для IPSC открыт!   Безопасные тренировки на электронных мишенях!


Уважаемые спортсмены и любители IPSC!

Мы рады сообщить о запуске нового тира с упором на IPSC — дисциплину, где важны не только меткость, но и скорость, тактика и адаптация к меняющимся условиям!


••• Что вас ждёт:
1. Современные электронные мишени — безопасно, реалистично, с мгновенной обратной связью.
2. Холостые тренировки IPSC — идеально для новичков и тех, кто хочет отточить навыки без боевых патронов.


••• Что такое «холостые тренировки IPSC»?
Это практика без живого огня: вместо патронов — лазерные модули и учебные макеты оружия. Электронные мишени фиксируют ваши действия, позволяя:

- 🎯 Тренировать скорость, точность и перемещение между укрытиями;

- 🔄 Отрабатывать сложные сценарии (движение под углом, стрельба в ограниченном пространстве);

- ✅ Безопасно начинать — никакого риска случайного выстрела;

- 💰 Экономить — платите только за время тренировки.


Записывайтесь уже сегодня и погрузитесь в мир динамичной стрельбы!

Ваша безопасность — наш приоритет.

Ждем вас в Смольном Тире!

📍 Адрес: [указать]
📞 Запись: [телефон/ссылка]
⏰ Часы работы: [расписание]

 ✨ IPSC — это не просто стрельба. Это стиль жизни!
                    [no_webpage] => 1
                )

        )

)

             */

        } catch (RPCErrorException $e) {
            $this->setErrorMsg('RPCErrorException: '.$e->getMessage());
        } catch (PeerNotInDbException $e) {
            $this->setErrorMsg('PeerNotInDbException: '.$e->getMessage());
        } catch (\Exception $e) {
            $this->setErrorMsg('Exception: '.$e->getMessage());
        }

        if ($MadelineProto) {
            try {
                unset($MadelineProto);
            } catch (\Throwable $e) {
                $this->setErrorMsg('Shutdown error: ' . $e->getMessage());
            }
        }

        gc_collect_cycles();

        return isset($sentMessage['id']);
    }

    private function setInfoMsg(string $text)
    {
        $this->messages['info'][] = $text;
    }

    public function getInfoMsg(): array
    {
        return $this->messages['info'];
    }

    private function setWarnMsg(string $text)
    {
        $this->messages['warn'][] = $text;
    }

    public function getWarnMsg(): array
    {
        return $this->messages['warn'];
    }

    private function setErrorMsg(string $text)
    {
        $this->messages['error'][] = $text;
    }

    public function getErrorMsg(): array
    {
        return $this->messages['error'];
    }
}
