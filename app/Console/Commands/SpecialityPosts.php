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
use App\Services\AuthorCatalogSpecialitiesSync;
use App\Services\BuilderSpecialityMatcher;
use App\Services\Dictionary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpecialityPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:posts:speciality';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Назначение специализации сообщениям без специализации';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Специалисты');
        $this->specialists();

        $this->info('Строители');
        $this->builders();
    }

    protected function specialists(): void
    {
        $specialists = Specialist::whereNotIn('id', SpecialistSpeciality::select('specialist_id')->groupBy('specialist_id'))
            ->has('post')
            ->orderBy('id', 'asc')
            ->take(500)
            ->get();

        if (!count($specialists)) {
            $this->warn('Нет списка постов без специализации');
            return;
        }

        $specialistsAll = Specialist::whereNotIn('id', SpecialistSpeciality::select('specialist_id')->groupBy('specialist_id'))
            ->has('post')
            ->orderBy('id', 'asc')
            ->count();

        $this->info('В обработку постов: '.count($specialists).' из '.$specialistsAll);

        $dictionary = new Dictionary;
        $specialityList = $dictionary->getAll(
            DictionaryEnum::Speciality,
            ApiDataTypeEnum::Specialist
        );

        foreach ($specialists as $specialist) {
            try {
                $specialistSpecialties = $dictionary->checkMatchByList($specialist->post->post, $specialityList);
                if (count($specialistSpecialties)) {
                    $dictionary->updateRelations(
                        DictionaryEnum::Speciality,
                        'specialist',
                        $specialist->id,
                        $specialistSpecialties
                    );

                    $this->info('ID '.$specialist->id.': '.count($specialistSpecialties));
                }
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }

        $this->info('Завершено');
    }

    protected function builders(): void
    {
        $specialists = Builder::whereNotIn('id', BuilderSpeciality::select('builder_id')->groupBy('builder_id'))
            ->has('post')
            ->orderBy('id', 'asc')
            ->take(500)
            ->get();

        if (!count($specialists)) {
            $this->warn('Нет списка постов без специализации');
            return;
        }

        $specialistsAll = Builder::whereNotIn('id', BuilderSpeciality::select('builder_id')->groupBy('builder_id'))
            ->has('post')
            ->orderBy('id', 'asc')
            ->count();

        $this->info('В обработку постов: '.count($specialists).' из '.$specialistsAll);

        $dictionary = new Dictionary;
        $matcher = app(BuilderSpecialityMatcher::class);
        $authorSync = app(AuthorCatalogSpecialitiesSync::class);
        $maxSpec = AuthorCatalogSpecialitiesSync::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR;
        $touchedUserIds = [];

        foreach ($specialists as $specialist) {
            try {
                $resolved = $matcher->resolve(
                    (string) ($specialist->post->post ?? ''),
                    null,
                    $maxSpec
                );
                $specialistSpecialties = $resolved['ids'];
                if (count($specialistSpecialties)) {
                    $dictionary->updateRelations(
                        DictionaryEnum::Speciality,
                        'builder',
                        $specialist->id,
                        $specialistSpecialties
                    );

                    $userId = (int) $specialist->api_post_user_id;
                    if ($userId > 0) {
                        $touchedUserIds[$userId] = true;
                    }

                    $this->info('ID '.$specialist->id.': '.count($specialistSpecialties));
                }
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }

        foreach (array_keys($touchedUserIds) as $userId) {
            $authorSync->syncAfterBuilderImport($userId);
        }

        $this->info('Завершено');
    }
}
