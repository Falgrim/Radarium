<?php

namespace App\Models;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiAi extends Model
{
    use HasFactory;
    use ModelTableName;

    protected $fillable = [
        'title',
        'description',
        'api_source',
        'options',
        'status',
        'balance_sum',
        'date_balance',
        'created_at',
        'updated_at',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'date_balance',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
            'date_balance' => 'datetime:Y-m-d H:i:s',
            'options' => 'array',
            'status' => ApiAiStatusEnum::class,
            'api_source' => ApiAiSourceEnum::class,
        ];
    }

    public function channels(): HasMany
    {
        return $this->hasMany(ApiChannel::class);
    }
}
