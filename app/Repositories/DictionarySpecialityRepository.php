<?php

namespace App\Repositories;

use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
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
     * @param bool $withEmpty
     *
     * @return array
     */
    public function getList(ApiDataTypeEnum $type, bool $withEmpty = true): array
    {
        $list = [];

        if ($type === ApiDataTypeEnum::Specialist OR $type === ApiDataTypeEnum::Company) {
            $whereHas = 'specialists';
        } elseif ($type === ApiDataTypeEnum::Builder) {
            $whereHas = 'builders';
        } else {
            return $list;
        }

        $collection = $this->getQuery()
            ->select('title', 'id', 'short_name', 'group_title')
            ->where('api_data_type_id', $type);

        if (!$withEmpty) {
            $collection = $collection->whereHas($whereHas);
        }

        $collection = $collection->orderBy('title')->get();

        foreach ($collection as $row) {
            if ($row->group_title AND $row->group_title != $row->title) {
                $title = $row->group_title.' - '.$row->title;
            } else {
                $title = $row->title;
            }

            $list[$row->id] = [
                'value' => $title,
                'short_name' => $row->short_name,
                'id'    => $row->id,
            ];
        }

        return $list;
    }
}
