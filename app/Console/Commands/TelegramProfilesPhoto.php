<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
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
use App\Services\MadelineConnectionConfigurator;
use App\Services\ReadTelegramChats;
use danog\MadelineProto\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramProfilesPhoto extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tg_profile:photo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Загрузка фотографий профилей ТГ';

    protected ReadTelegramChats $readTelegramChats;


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $profiles = ApiPostUser::whereNull('photo')->get();
        $this->readTelegramChats = new ReadTelegramChats();

        if (!count($profiles)) {
            $this->info('Профили не найдены.');
            return;
        }

        $apiChannel = ApiChannel::where('status', ApiChannelStatusEnum::Active)->first();

        if (is_null($apiChannel)) {
            $this->error('Нет активных каналов для подключения');
            return;
        }

        if (!count($apiChannel->options) OR !isset($apiChannel->options['api_id']) OR !isset($apiChannel->options['api_hash'])) {
            $this->error('Канал ID '.$apiChannel->id.': нет api_id и/или api_hash');
            return;
        }

        $settings = new Settings;
        $settings->setAppInfo(
            (new \danog\MadelineProto\Settings\AppInfo)
                ->setApiId($apiChannel->options['api_id'])
                ->setApiHash($apiChannel->options['api_hash'])
        );
        MadelineConnectionConfigurator::apply($settings);
        MadelineConnectionConfigurator::applyFileLogger($settings);

        $MadelineProto = new \danog\MadelineProto\API('session.madeline', $settings);

        $MadelineProto->start();

        foreach ($profiles as $profile) {
            $this->info('Профиль ID ' . $profile->user_id);
            $this->searchPhoto($MadelineProto, $profile);
        }
    }

    protected function searchPhoto($MadelineProto, ApiPostUser $profile): void
    {
        try {
            $userInfo = $MadelineProto->getInfo($profile->user_id);
            if (!isset($userInfo['User'])) {
                $this->warn('Нет данных по профилю ID ' . $profile->user_id);
                return;
            }

            if (isset($userInfo['User']['photo'])) {
                $photos = $MadelineProto->photos->getUserPhotos([
                    'user_id' => $profile->user_id,
                    'offset' => 0,
                    'max_id' => 0,
                    'limit' => 1,
                ]);

                try {
                    $photoPath = $this->readTelegramChats->downloadUserPhoto($MadelineProto, $profile, $photos['photos'] ?? []);
                    if ($photoPath !== false) {
                        ApiPostUser::where('id', $profile->id)->update(['photo' => $photoPath]);
                        $this->info('Добавлено фото профиля ID ' . $profile->user_id . ': ' . $photoPath);
                    }
                } catch (\Exception $e) {
                    $this->warn('Не удалось скачать фото профиля ID ' . $profile->user_id . ': ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $this->warn('Ошибка данных профиля ID ' . $profile->user_id . ': '.$e->getMessage());
        }
    }
}
