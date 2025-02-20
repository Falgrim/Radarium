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
        $specialists = Specialist::whereNotIn('id', SpecialistSpeciality::select('specialist_id')->groupBy('specialist_id'))
            ->orderBy('id', 'asc')
            ->take(100)
            ->get();

        if (!count($specialists)) {
            $this->warn('Нет списка постов без специализации');
            return 1;
        }

        $specialistsAll = Specialist::whereNotIn('id', SpecialistSpeciality::select('specialist_id')->groupBy('specialist_id'))
            ->orderBy('id', 'asc')
            ->count();

        $this->info('В обработку постов: '.count($specialists).' из '.$specialistsAll);

        $dictionary = new Dictionary;
        $specialityList = $dictionary->getAll(DictionaryEnum::Speciality);

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
}
