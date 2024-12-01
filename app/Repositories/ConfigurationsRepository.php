<?php

namespace App\Repositories;

use App\Models\Configuration;
use App\Infrastructures\Repository\Repository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;

class ConfigurationsRepository extends Repository
{
    /**
     * @throws BindingResolutionException
     */
    protected function setModel(): Model
    {
        return app()->make(Configuration::class);
    }

    public function change(Configuration $setting, $value, array $options = [])
    {
        $dataToUpdate = ['value' => $value];

        if ($options) {
            $dataToUpdate['options'] = $options;
        }

        $setting->update($dataToUpdate);

        return $setting->value;
    }

    /**
     * Получение настроек по идентификатору альясу
     *
     * @param array $names
     *
     * @return array
     */
    public function getByNames(array $names): array
    {
        $configs = [];
        $configCollection = $this->getQuery()->whereIn('name', $names)->get();
        foreach ($configCollection as $config) {
            $configs[$config->name] = [
                'value' => $config->value,
                'options' => $config->options
            ];
        }
        return $configs;
    }

    public function findByName(string $name): ?Configuration
    {
        return $this->getQuery()->where('name', $name)->first();
    }
}
