<?php

namespace App\Repositories;

use App\Enum\SpecialistStatusEnum;
use App\Models\Configuration;
use App\Infrastructures\Repository\Repository;
use App\Models\DictionarySpeciality;
use App\Models\Specialist;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;

class DictionarySpecialityRepository extends Repository
{
    /**
     * @throws BindingResolutionException
     */
    protected function setModel(): Model
    {
        return app()->make(DictionarySpeciality::class);
    }

    /**
     * Список уникальных данных по специальности
     *
     * @param array $list
     *
     * @return array
     */
    public function getList(): array
    {
        $list = [];

        $collection = $this->getQuery()
            ->select('title', 'id', 'short_name')
            ->orderBy('title')
            ->get();

        foreach ($collection as $row) {
            $list[$row->id] = [
                'value' => $row->title,
                'short_name' => $row->short_name,
                'id'    => $row->id,
            ];
        }

        return $list;
    }
}
