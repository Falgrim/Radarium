<?php

namespace App\Services;

use App\Enum\DictionaryEnum;
use App\Models\DictionarySpeciality;
use App\Models\Specialist;
use App\Models\SpecialistSpeciality;
use Faker\Core\DateTime;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Dictionary
{
    public function __construct() {

    }

    public function parseOkcoString(string $name): array
    {
        preg_match('#^([0-9\.]{1,})+\s?\-?\s?(.*)$#', $name, $mathes);
        return [
            'code' => $mathes[1] ?? null,
            'name' => $mathes[2] ?? null,
            'short_name' => self::acronym($mathes[2]),
        ];
    }

    public static function acronym (?string $name)
    {
        if (!$name) {
            return null;
        }
        return Str::upper(Str::acronym($name));
    }

    public function getOrCreate(DictionaryEnum $dictionary, string $title, ?string $shortName, ?string $OksoCode): int
    {
        if (DictionaryEnum::Speciality === $dictionary) {
            $rowData = DictionarySpeciality::updateOrCreate([
                'title' => $title,
                'okso_code' => $OksoCode,
            ], [
                'title' => $title,
                'short_name' => $shortName,
                'okso_code' => $OksoCode,
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
