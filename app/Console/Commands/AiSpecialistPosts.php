<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\DictionaryEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\ModerationAlertStatusEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\ModerationAlert;
use App\Models\Specialist;
use App\Models\SpecialistSpeciality;
use App\Services\ApiAIYandex;
use App\Services\Dictionary;
use App\Services\ModerationAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiSpecialistPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ai_parse:specialist';

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
            ->where('api_channels.is_company', ApiDataTypeEnum::Specialist)
            ->orderBy('api_channel_posts.post_date', 'asc')
            ->take(100)
            ->get();

        if (!count($posts)) {
            $this->info('Нет списка постов для парсинга');
            return 0;
        }

        $postsAll = ApiChannelPost::select('api_channel_posts.*')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->leftJoin(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', ApiDataTypeEnum::Specialist)
            ->count();

        $this->info('В обработку постов: '.count($posts).' из '.$postsAll);

        $dictionary = new Dictionary;
        $specialityList = $dictionary->getAll(
            DictionaryEnum::Speciality,
            ApiDataTypeEnum::Specialist
        );

        $moderationAlertService = app()->make(ModerationAlertService::class);

        foreach ($posts as $post) {
            try {
                $promt = $post->channel->ai_promt;
                $options = $post->channel->apiAi->options;

                $this->info('Анализ поста: '.$post->id);
                if ($post->channel->apiAi->status !== ApiAiStatusEnum::Active) {
                    $this->error('ИИ "'.$post->channel->apiAi->title.'" (ID '.$post->channel->apiAi->id.') отключен: '.$post->id);
                    continue;
                }

                if ($post->channel->apiAi->api_source === ApiAiSourceEnum::YandexGTP4) {
                    $ApiAIYandex = new ApiAIYandex;
                    $ApiAIYandex->logging('Анализ поста: '.$post->id);
                    $ApiAIYandex->setConfig($options);
                    $ApiAIYandex->setPromt($promt);
                    $ApiAIYandex->setText($post->post);
                    $result = $ApiAIYandex->getResult(ApiDataTypeEnum::Specialist);

                    if (count($result['json'])) {
                        // Удаляем старое резюме, на случай повторного прогона поста
                        Specialist::where('api_channel_post_id', $post->id)->delete();

                        $post->ai_result = $result['origin'];
                        $post->ai_date = now();

                        $result['json']['ai_type'] = Str::lower($result['json']['ai_type']);

                        if (
                            $result['json']['ai_type'] != 'резюме' AND
                            $result['json']['ai_type'] != 'предоставление услуги' AND
                            $result['json']['ai_type'] != 'предложение услуг'
                        ) {
                            $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
                            $post->save();

                            //$this->info($post->post);
                            $this->warn('Тип сообщения: '.$result['json']['ai_type']);
                            continue;
                        }

                        $result['json']['post_date'] = $post->post_date;
                        $result['json']['api_post_user_id'] = $post->apiPostUser->id;
                        $result['json']['api_channel_post_id'] = $post->id;

                        if (!$result['json']['contact_info']) {
                            $result['json']['contact_info'] = '';
                        }

                        $result['json']['status'] = ApiPostAiStatusEnum::Active;

                        $specialist = Specialist::create($result['json']);

                        $specialistSpecialties = $dictionary->checkMatchByList($post->post, $specialityList);
                        if (count($specialistSpecialties)) {
                            $dictionary->updateRelations(
                                DictionaryEnum::Speciality,
                                'specialist',
                                $specialist->id,
                                $specialistSpecialties
                            );
                        } else {

                        }

                        $post->ai_parse_status = ApiChannelPostStatusEnum::Complete;
                        $post->save();

                        if ($specialist->status === ApiPostAiStatusEnum::InModeration OR $specialist->status === ApiPostAiStatusEnum::Active) {
                            $this->info('Создан специалист');

                            $moderationAlertService->createAlert(
                                0,
                                ModerationAlertSystemEnum::System,
                                ModerationAlertTableNameEnum::Specialist,
                                $specialist->id,
                                'Нет специализаций'
                            );
                        } else {
                            $this->info('Данный пост не является типом специалиста');
                        }
                    } else {
                        $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                        $post->save();
                        $this->warn('По специалисту не найдены данные');
                    }
                } else {
                    $this->warn('Неизвестный источник');
                }
            } catch (\TypeError $e) {
                $this->error($e->getMessage());
                $ApiAIYandex->logging($e->getMessage(), true);

                $post->ai_result = $e->getMessage();
                $post->ai_date = now();
                $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                $post->save();
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $ApiAIYandex->logging($e->getMessage(), true);

                $post->ai_result = $e->getMessage();
                $post->ai_date = now();
                $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                $post->save();
            }
        }

        $this->info('Завершено');
    }
}
