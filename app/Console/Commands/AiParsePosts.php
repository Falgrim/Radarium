<?php

namespace App\Console\Commands;

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
            ->take(50)
            ->get();

        if (!count($posts)) {
            $this->warn('Нет списка постов для парсинга');
            return 1;
        }

        foreach ($posts as $post) {


        }

        $this->info('Завершено');
    }
}
