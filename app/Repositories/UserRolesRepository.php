<?php

namespace App\Repositories;

use App\Enum\ApiPostAiStatusEnum;
use App\Models\Configuration;
use App\Infrastructures\Repository\Repository;
use App\Models\Specialist;
use App\Models\UserRole;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;

class UserRolesRepository extends Repository
{
    /**
     * @throws BindingResolutionException
     */
    protected function setModel(): Model
    {
        return app()->make(UserRole::class);
    }

    /**
     * Список ролей
     *
     * @param array $list
     *
     * @return array
     */
    public function getList(): array
    {
        $list = [];

        $roleCollection = $this->getQuery()->get();

        foreach ($roleCollection as $row) {
            $list[$row->id] = [
                'value' => $row->title,
                'id'    => $row->id,
            ];
        }

        return $list;
    }
}
