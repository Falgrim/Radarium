<?php

namespace App\Infrastructures\Facades;

use App\Repositories\ConfigurationsRepository;
use Illuminate\Contracts\Container\BindingResolutionException;

class Repositories
{
    private static array $_instance = [];

    /**
     * @throws BindingResolutionException
     */
    private static function getInstance(string $className): object
    {
        if (isset(self::$_instance[$className])) {
            return self::$_instance[$className];
        }

        self::$_instance[$className] = app()->make($className);
        return self::$_instance[$className];
    }

    /**
     * @throws BindingResolutionException
     */
    public static function setting(): ConfigurationsRepository
    {
        return self::getInstance(ConfigurationsRepository::class);
    }
}
