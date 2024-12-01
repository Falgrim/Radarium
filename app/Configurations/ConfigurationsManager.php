<?php

namespace App\Configurations;

use App\Infrastructures\Facades\Repositories;
use Illuminate\Contracts\Container\BindingResolutionException;
use App\Models\Configuration as ConfigurationModel;

class ConfigurationsManager
{
    /**
     * Get a setting instance
     *
     * @param string $title
     * @return ConfigurationModel|null
     * @throws BindingResolutionException
     */
    public static function instance(string $title): ?ConfigurationModel
    {
        return Repositories::setting()->getQuery()->where('name', $title)->first();
    }

    /**
     * Get a setting value
     *
     * @param string $title
     * @return mixed
     * @throws BindingResolutionException
     * @deprecated It's better use getByIdent() and /App/Enums/SettingLabel
     */
    public static function get(string $title): mixed
    {
        return static::instance($title)?->value;
    }
}
