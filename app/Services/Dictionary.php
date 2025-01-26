<?php

namespace App\Services;

use App\Enum\DictionaryEnum;
use App\Models\DictionarySpeciality;
use App\Models\Specialist;
use App\Models\SpecialistSpeciality;
use Faker\Core\DateTime;
use Illuminate\Support\Facades\Http;

class Dictionary
{
    public function __construct() {

    }

    public function getOrCreate(DictionaryEnum $dictionary, string $title, ?string $shortName): int
    {
        if (DictionaryEnum::Speciality === $dictionary) {
            $rowData = DictionarySpeciality::updateOrCreate([
                'title' => $title,
            ], [
                'title' => $title,
                'short_name' => $shortName,
            ]);
        } else {
            throw new \Exception('Выбранный словарь не найден');
        }

        return $rowData->id;
    }

    public function updateRelations(DictionaryEnum $dictionary, string $model, int $rowId, array $dictionaryIds): void
    {
        $rowIdName = '';
        if ($model === 'specialist') {
            $rowIdName = 'specialist_id';
            $modelSpeciality = 'App\Models\SpecialistSpeciality';
        } elseif ($model === 'companyJob') {
            $rowIdName = 'company_job_id';
            $modelSpeciality = 'App\Models\CompanyJobSpeciality';
        }

        if (!$rowIdName) {
            throw new \Exception('Модель не найдена');
        }

        if (DictionaryEnum::Speciality === $dictionary) {
            if (!count($dictionaryIds)) {
                $modelSpeciality::where($rowIdName, $rowId)->delete();
                return;
            }

            $modelSpeciality::where($rowIdName, $rowId)
                ->whereNotIn('dictionary_speciality_id', array_values($dictionaryIds))
                ->delete();

            foreach ($dictionaryIds as $dictionaryId) {
                $modelSpeciality::firstOrCreate([
                    $rowIdName => $rowId,
                    'dictionary_speciality_id' => $dictionaryId,
                ]);
            }
        } else {
            throw new \Exception('Выбранный словарь не найден');
        }
    }
}
