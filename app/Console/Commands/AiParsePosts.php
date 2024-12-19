<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\SpecialistStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\Specialist;
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

        $this->info('Найдено постов: '.count($posts));

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

                    if (count($result['json'])) {
                        // Удаляем старое резюме, на случай повторного прогона поста
                        Specialist::where('api_channel_post_id', $post->id)->delete();

                        $result['json']['api_post_user_id'] = $post->apiUser->id;
                        $result['json']['api_channel_post_id'] = $post->id;
                        $result['json']['status'] = SpecialistStatusEnum::InModeration;
                        Specialist::create($result['json']);

                        $post->ai_result = $result['origin'];
                        $post->ai_date = now();
                        $post->ai_parse_status = ApiChannelPostStatusEnum::Complete;
                        $post->save();

                        $this->info('Создан специалист (резюме)');
                    } else {
                        $post->ai_date = now();
                        $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                        $post->save();
                        $this->warn('По специалисту не найдены данные');
                    }
                } else {
                    $this->warn('Неизвестный источник для');
                }
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $post->ai_date = now();
                $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                $post->save();
                continue;
            }
        }

        $this->info('Завершено');
    }
}
