<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\DictionaryEnum;
use App\Enum\IsCompanyEnum;
use App\Enum\SpecialistStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\Specialist;
use App\Models\SpecialistSpeciality;
use App\Services\ApiAIYandex;
use App\Services\Dictionary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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
            ->take(30)
            ->get();

        if (!count($posts)) {
            $this->warn('Нет списка постов для парсинга');
            return 1;
        }

        $postsAll = ApiChannelPost::select('api_channel_posts.*')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->leftJoin(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', IsCompanyEnum::Private)
            ->orderBy('api_channel_posts.created_at', 'asc')
            ->count();

        $this->info('В обработку постов: '.count($posts).' из '.$postsAll);

        $dictionary = new Dictionary;

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

                        if (isset($result['json']['empty']) AND $result['json']['empty'] === true) {
                            $post->ai_result = $result['origin'];
                            $post->ai_date = now();
                            $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                            $post->save();
                            continue;
                        }

                        $result['json']['post_date'] = $post->post_date;
                        $result['json']['api_post_user_id'] = $post->apiPostUser->id;
                        $result['json']['api_channel_post_id'] = $post->id;

                        if (!$result['json']['contact_info']) {
                            $result['json']['contact_info'] = '';
                        }

                        if ($result['json']['ai_type'] != 'резюме') {
                            $result['json']['status'] = SpecialistStatusEnum::Error;
                        } else {
                            $result['json']['status'] = SpecialistStatusEnum::InModeration;
                        }

                        $specialistSpecialties = [];
                        if (isset($result['json']['specialist_specialties'])) {
                            $specialistSpecialties = $result['json']['specialist_specialties'];
                            unset($result['json']['specialist_specialties']);
                        }

                        $specialist = Specialist::create($result['json']);

                        if (count($specialistSpecialties)) {
                            $dictionaryArr = [];
                            if (is_array($specialistSpecialties[0]['name'])) {
                                foreach ($specialistSpecialties[0]['name'] as $key => $val) {
                                    $dictionaryArr[] = $dictionary->getOrCreate(
                                        DictionaryEnum::Speciality,
                                        $specialistSpecialties[0]['name'][$key],
                                        $specialistSpecialties[0]['short_name'][$key]
                                    );
                                }
                            } else {
                                foreach ($specialistSpecialties as $specialistSpecialty) {
                                    $dictionaryArr[] = $dictionary->getOrCreate(
                                        DictionaryEnum::Speciality,
                                        $specialistSpecialty['name'],
                                        $specialistSpecialty['short_name']
                                    );
                                }
                            }

                            $dictionary->updateRelations(DictionaryEnum::Speciality, $specialist->id, $dictionaryArr);
                        }

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
