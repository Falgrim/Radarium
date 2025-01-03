<?php

namespace App\Traits;

trait ModelTableName
{
    public static function table()
    {
        return with(new static)->getTable();
    }
}
