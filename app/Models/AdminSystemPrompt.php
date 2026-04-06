<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Model;

class AdminSystemPrompt extends Model
{
    use ModelTableName;

    protected $fillable = [
        'scope',
        'body',
    ];
}
