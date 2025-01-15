<?php

namespace App\Services;

use Faker\Core\DateTime;
use Illuminate\Support\Facades\Http;

class ApiTelegram
{
    protected $config;
    protected $bot;

    public function __construct() {}

    public function connect(array $config): void
    {
        $settings = (new \danog\MadelineProto\Settings\AppInfo)
            ->setApiId($config['app_id'])
            ->setApiHash($config['app_hash']);

        $logSettings = (new \danog\MadelineProto\Settings\Logger)
            ->setLevel($config['log_level'] ?? 0);

        $this->bot = new \danog\MadelineProto\API('session.madeline', $settings);
        $this->bot->updateSettings($logSettings);
        $this->bot->start();
    }

    public function getPosts(string $source, \DateTime $dateStart, int $lastPostId, int $limit): array
    {
        $messages = $this->bot->messages->getHistory([
            'peer'          => $source,
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
        $result = [];
        foreach ($messages as $message) {
            $result[] = $this->getInfoFromPost($message);
        }

        return $result;
    }

    protected function getInfoFromPost($data)
    {
        return [
            'post'      => $data['message'],
            'id'        => $data['id'],
            'user_id'   => $data['from_id'],
            'date'      => (new \DateTime($data['date']))->format("Y-m-d H:i:s"),
        ];
    }
}
