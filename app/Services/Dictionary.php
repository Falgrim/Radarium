<?php

namespace App\Services;

use App\Enum\ApiDataTypeEnum;
use App\Enum\DictionaryEnum;
use App\Models\DictionarySpeciality;
use App\Models\Specialist;
use App\Models\SpecialistSpeciality;
use Faker\Core\DateTime;
use Illuminate\Support\Collection;
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

    public function getAll(DictionaryEnum $dictionary, ApiDataTypeEnum $apiDataType): Collection
    {
        if (DictionaryEnum::Speciality === $dictionary) {
            return DictionarySpeciality::where('api_data_type_id', $apiDataType)->get();
        } else {
            throw new \Exception('Выбранный словарь не найден');
        }
    }

    public function checkMatchByList($text, $list): array
    {
        $matchIds = [];
        foreach ($list as $item) {
            $pattern = '/\b(?:' . implode('|', array_map('preg_quote', $item['key_words'])) . ')\b(?:-[а-яa-z]*)?/iu';
            preg_match_all($pattern, $text, $matches);
            if (isset($matches[0]) AND count($matches[0])) {
                $matchIds[] = $item->id;
            }
        }

        return $matchIds;
    }

    public function getOrCreate(DictionaryEnum $dictionary, string $title, ?string $shortName, ?string $OksoCode): int
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
        } elseif ($model === 'builder') {
            $rowIdName = 'builder_id';
            $modelSpeciality = 'App\Models\BuilderSpeciality';
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
