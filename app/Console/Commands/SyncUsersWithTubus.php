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
use App\Models\User;
use App\Services\ApiAIYandex;
use App\Services\ApiTubus;
use App\Services\Dictionary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncUsersWithTubus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tubus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Синхронизация пользователй с Тубус';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::whereNull('tubus_id')
            ->whereNotNull('phone')
            ->orderBy('created_at')
            ->take(30)
            ->get();

        if (!count($users)) {
            $this->warn('Нет пользователей для синхронизации');
            return 1;
        }

        $usersAll = User::whereNull('tubus_id')
            ->whereNotNull('phone')
            ->orderBy('created_at')
            ->count();

        $this->info('В обработку постов: '.count($users).' из '.$usersAll);

        $apiTubus = new ApiTubus;

        foreach ($users as $user) {
            try {
                $this->info('Проверка номера: '.$user->phone);
                $result = $apiTubus->checkUser($user->phone);

                if (!isset($result['user'])) {
                    throw new \Exception('Ошибка ответа, нет массива user');
                }

                if (isset($result['user']['performer_user_id']) AND $result['user']['performer_user_id'] !== $user->id) {
                    $resultLink = $apiTubus->linkUser($user);

                    if ($resultLink !== true) {
                        throw new \Exception('Ошибка связывания пользователя в Tubus');
                    }
                }

                $user->tubus_id = $result['user']['id'] ?? 0;
                $user->save();

                $this->info('Получен Tubus ID: '.$user->tubus_id);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $user->tubus_id = 0;
                $user->save();
            }
        }

        $this->info('Завершено');
    }
}
