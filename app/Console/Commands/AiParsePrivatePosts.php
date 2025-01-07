<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\IsCompanyEnum;
use App\Enum\SpecialistStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\Specialist;
use App\Services\ApiAIYandex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AiParsePrivatePosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ai_parse:private';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Анализ постов в ИИ для резюме';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $posts = ApiChannelPost::select('api_channel_posts.*')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->leftJoin(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', IsCompanyEnum::Private)
            ->orderBy('api_channel_posts.created_at', 'asc')
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
                $options = $post->channel->apiAi->options;

                if ($post->channel->apiAi->api_source === ApiAiSourceEnum::YandexGTP4) {
                    $ApiAIYandex = new ApiAIYandex;
                    $ApiAIYandex->setConfig($options);
                    $ApiAIYandex->setPromt($promt);
                    $ApiAIYandex->setText($post->post);
                    $result = $ApiAIYandex->getResult(IsCompanyEnum::Private);

                    if (count($result['json'])) {
                        // Удаляем старое резюме, на случай повторного прогона поста
                        Specialist::where('api_channel_post_id', $post->id)->delete();

                        $result['json']['api_post_user_id'] = $post->apiPostUser->id;
                        $result['json']['api_channel_post_id'] = $post->id;

                        if ($result['json']['ai_type'] != 'резюме') {
                            $result['json']['status'] = SpecialistStatusEnum::Error;
                        } else {
                            $result['json']['status'] = SpecialistStatusEnum::InModeration;
                        }

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
                    $this->warn('Неизвестный источник');
                }
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                Log::channel('post_ai')->error($e->getMessage());

                $post->ai_date = now();
                $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                $post->save();

                continue;
            }
        }

        $this->info('Завершено');
    }
}
