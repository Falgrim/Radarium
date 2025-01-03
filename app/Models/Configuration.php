<?php

namespace App\Models;

use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    use HasFactory;
    use ModelTableName;

    protected static $orderByColumn = 'order';
    protected static $orderByColumnDirection = 'asc';
}
