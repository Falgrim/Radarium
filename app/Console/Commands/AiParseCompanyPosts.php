<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Enum\DictionaryEnum;
use App\Enum\IsCompanyEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\CompanyJob;
use App\Services\ApiAIYandex;
use App\Services\Dictionary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiParseCompanyPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ai_parse:company';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Анализ постов в ИИ для вакансий';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $posts = ApiChannelPost::select('api_channel_posts.*')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->leftJoin(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', IsCompanyEnum::Company)
            ->orderBy('api_channel_posts.post_date', 'asc')
            ->take(30)
            ->get();

        if (!count($posts)) {
            $this->warn('Нет списка постов для парсинга');
            return 1;
        }

        $postsAll = ApiChannelPost::select('api_channel_posts.*')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->leftJoin(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', IsCompanyEnum::Company)
            ->count();

        $this->info('В обработку постов: '.count($posts).' из '.$postsAll);

        $dictionary = new Dictionary;

        foreach ($posts as $post) {
            try {
                $promt = $post->channel->ai_promt;
                $options = $post->channel->apiAi->options;

                if ($post->channel->apiAi->api_source === ApiAiSourceEnum::YandexGTP4) {
                    $this->info('Анализ поста: '.$post->id);

                    $ApiAIYandex = new ApiAIYandex;
                    $ApiAIYandex->logging('Анализ поста: '.$post->id);
                    $ApiAIYandex->setConfig($options);
                    $ApiAIYandex->setPromt($promt);
                    $ApiAIYandex->setText($post->post);
                    $result = $ApiAIYandex->getResult(IsCompanyEnum::Company);

                    if (count($result['json'])) {
                        // Удаляем старое резюме, на случай повторного прогона поста
                        CompanyJob::where('api_channel_post_id', $post->id)->delete();

                        $post->ai_result = $result['origin'];
                        $post->ai_date = now();

                        $result['json']['ai_type'] = Str::lower($result['json']['ai_type']);

                        if (
                            $result['json']['ai_type'] != 'вакансия' AND
                            $result['json']['ai_type'] != 'поиск того кто окажет услугу' AND
                            $result['json']['ai_type'] != 'поиск подрядчика'
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

                        $result['json']['status'] = CompanyJobStatusEnum::InModeration;

                        $companyJobSpecialties = [];
                        if (isset($result['json']['specialities'])) {
                            $companyJobSpecialties = $result['json']['specialities'];
                            unset($result['json']['specialities']);
                        }

                        $companyJob = CompanyJob::create($result['json']);

                        if (count($companyJobSpecialties)) {
                            $dictionaryArr = [];
                            foreach ($companyJobSpecialties as $companyJobSpecialty) {
                                $specInfo = $dictionary->parseOkcoString($companyJobSpecialty);

                                if (!$specInfo['name'] OR !$specInfo['code']) {
                                    throw new \Exception('Ошибка определения специализации: '.json_encode($specInfo));
                                }

                                $dictionaryArr[] = $dictionary->getOrCreate(
                                    DictionaryEnum::Speciality,
                                    $specInfo['name'],
                                    $specInfo['short_name'],
                                    $specInfo['code'],
                                );
                            }

                            $dictionary->updateRelations(DictionaryEnum::Speciality, 'companyJob', $companyJob->id, $dictionaryArr);
                        }

                        $post->ai_parse_status = ApiChannelPostStatusEnum::Complete;
                        $post->save();

                        if ($companyJob->status === CompanyJobStatusEnum::InModeration) {
                            $this->info('Создана вакансия');
                        } else {
                            $this->info('Данный пост не является типом вакансии');
                        }
                    } else {
                        $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                        $post->save();
                        $this->warn('По вакансии не найдены данные');
                    }
                } else {
                    $this->warn('Неизвестный источник');
                }
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $ApiAIYandex->logging($e->getMessage(), true);

                $post->ai_result = $e->getMessage();
                $post->ai_date = now();
                $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                $post->save();

                continue;
            }
        }

        $this->info('Завершено');
    }
}
