<?php

namespace App\Repositories;

use App\Enum\SpecialistStatusEnum;
use App\Models\Configuration;
use App\Infrastructures\Repository\Repository;
use App\Models\Specialist;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;

class SpecialistsRepository extends Repository
{
    /**
     * @throws BindingResolutionException
     */
    protected function setModel(): Model
    {
        return app()->make(Specialist::class);
    }

    /**
     * Список уникальных данных по специальности
     *
     * @param array $list
     *
     * @return array
     */
    public function getExperienceList(bool $onlyActive = true): array
    {
        $list = [];

        if ($onlyActive) {
            $configCollection = $this->getQuery()
                ->select('experience')
                ->whereNotNull('experience')
                ->where('status', SpecialistStatusEnum::Active)
                ->groupBy('experience')
                ->get();
        } else {
            $configCollection = $this->getQuery()
                ->select('experience')
                ->whereNotNull('experience')
                ->groupBy('experience')
                ->get();
        }

        foreach ($configCollection as $row) {
            $list[md5($row->experience)] = [
                'value' => $row->experience,
                'id'    => md5($row->experience),
            ];
        }

        return $list;
    }
}
