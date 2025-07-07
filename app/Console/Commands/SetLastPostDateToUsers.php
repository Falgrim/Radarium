<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\DictionaryEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\Builder;
use App\Models\BuilderSpeciality;
use App\Models\Specialist;
use App\Models\SpecialistSpeciality;
use App\Services\ApiAIYandex;
use App\Services\Dictionary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SetLastPostDateToUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:api_post_users:last_post_date';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Назначение последней даты поста для пользователя';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = ApiPostUser::get();
        foreach ($users as $user) {
            $lastPost = Specialist::where('api_post_user_id', $user->id)
                ->where('status', ApiPostAiStatusEnum::Active)
                ->orderBy('post_date', 'DESC')->first();

            if ($lastPost AND (!$user->last_post_date OR $user->last_post_date < $lastPost->post_date)) {
                $user->last_post_date = $lastPost->post_date;
                $user->save();

                $this->info('Обновлен профиль ID: '.$user->id);
            }
        }

        $this->info('Данные обновлены');
    }
}
