<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Services\ApiAIYandex;
use Illuminate\Console\Command;

class AiParsePosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ai_parse';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Анализ постов в ИИ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $posts = ApiChannelPost::where('ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->orderBy('created_at', 'asc')
            ->take(10)
            ->get();

        if (!count($posts)) {
            $this->warn('Нет списка постов для парсинга');
            return 1;
        }

        foreach ($posts as $post) {
            try {
                $promt = $post->channel->ai_promt;
                $options = $post->channel->api_ai->options;

                if ($post->channel->api_ai->api_source === ApiAiSourceEnum::YandexGTP4) {
                    $ApiAIYandex = new ApiAIYandex;
                    $ApiAIYandex->setConfig($options);
                    $ApiAIYandex->setPromt($promt);
                    $ApiAIYandex->setText($post->post);
                    $result = $ApiAIYandex->getResult();

                    print_r($result);
                } else {
                    $this->warn('Неизвестный источник для');
                }
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $post->ai_date = now();
                $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                $post->save();
            }
        }

        $this->info('Завершено');
    }

    public function addSpecialistData(array $aiResult)
    {

    }
}
