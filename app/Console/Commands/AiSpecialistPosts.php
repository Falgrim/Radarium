<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiChannel;
use App\Services\AiParsePostExceptionHandler;
use App\Services\ApiAIOllama;
use App\Services\ApiAIYandex;
use App\Services\SpecialistPostImporter;
use Illuminate\Console\Command;

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
    public function handle(SpecialistPostImporter $importer)
    {
        $posts = ApiChannelPost::select('api_channel_posts.*')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->leftJoin(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', ApiDataTypeEnum::Specialist)
            ->orderBy('api_channel_posts.post_date', 'asc')
            ->take(100)
            ->get();

        if (! count($posts)) {
            $this->info('Нет списка постов для парсинга');

            return 0;
        }

        $postsAll = ApiChannelPost::select('api_channel_posts.*')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->leftJoin(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', ApiDataTypeEnum::Specialist)
            ->count();

        $this->info('В обработку постов: '.count($posts).' из '.$postsAll);

        foreach ($posts as $post) {
            try {
                $this->info('Анализ поста ID: '.$post->id);
                if ($post->channel->apiAi->status !== ApiAiStatusEnum::Active) {
                    $this->error('ИИ "'.$post->channel->apiAi->title.'" (ID '.$post->channel->apiAi->id.') отключен: '.$post->id);

                    continue;
                }

                $apiSource = $post->channel->apiAi->api_source;

                if ($apiSource === ApiAiSourceEnum::YandexGTP4) {
                    $aiService = new ApiAIYandex;
                } elseif ($apiSource === ApiAiSourceEnum::OllamaQwen) {
                    $aiService = new ApiAIOllama;
                } else {
                    $this->warn('Неизвестный источник');

                    continue;
                }

                $aiService->logging('Анализ поста ID: '.$post->id);
                $aiService->setConfig($post->channel->apiAi->options);
                $post->ai_provider_used = $apiSource->value;

                $import = $importer->import($post, $aiService, [
                    'trigger' => 'specialist_channel',
                ]);

                match ($import['status']) {
                    SpecialistPostImporter::STATUS_CREATED => $this->info(
                        'Создан специалист ID: '.(int) $import['specialist_id']
                    ),
                    SpecialistPostImporter::STATUS_DONT_MATCH => $this->warn(
                        'Тип сообщения не подходит: '.($import['message'] ?? '')
                    ),
                    SpecialistPostImporter::STATUS_NO_PROMPT => $this->error(
                        $import['message'] ?? 'Нет промпта для проектировщиков'
                    ),
                    default => $this->warn($import['message'] ?? 'По специалисту не найдены данные'),
                };
            } catch (\Throwable $e) {
                app(AiParsePostExceptionHandler::class)->handle(
                    $post,
                    $e,
                    $aiService ?? null,
                    $this
                );
            }
        }

        $this->info('Завершено');
    }
}
