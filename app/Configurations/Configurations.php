<?php

namespace App\Configurations;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $string)
 *
 * @see App\Configurations\ConfigurationsManager
 */
class Configurations extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'configurations.manager';
    }
}
