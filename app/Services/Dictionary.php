<?php

namespace App\Services;

use App\Enum\DictionaryEnum;
use App\Models\DictionarySpeciality;
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

    public function updateRelations(DictionaryEnum $dictionary, int $rowId, array $dictionaryIds): void
    {
        if (DictionaryEnum::Speciality === $dictionary) {
            if (!count($dictionaryIds)) {
                SpecialistSpeciality::where('specialist_id', $rowId)->delete();
                return;
            }

            SpecialistSpeciality::where('specialist_id', $rowId)
                ->whereNotIn('dictionary_speciality_id', array_values($dictionaryIds))
                ->delete();

            foreach ($dictionaryIds as $dictionaryId) {
                SpecialistSpeciality::firstOrCreate([
                    'specialist_id' => $rowId,
                    'dictionary_speciality_id' => $dictionaryId,
                ]);
            }
        } else {
            throw new \Exception('Выбранный словарь не найден');
        }
    }
}
