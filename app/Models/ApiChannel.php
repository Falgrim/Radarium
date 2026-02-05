<?php

namespace App\Models;

use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiChannel extends Model
{
    use HasFactory;
    use ModelTableName;

    protected $fillable = [
        'title',
        /*'region',*/
        'link',
        'description',
        'ai_promt',
        'api_ai_id',
        'channel_source',
        'options',
        'status',
        'last_post_id',
        'is_company',
        'last_date_check',
        'post_from_date',
        'region',
        'created_at',
        'updated_at',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'last_date_check',
        'post_from_date',
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
            'last_date_check' => 'datetime:Y-m-d H:i:s',
            'post_from_date' => 'date:Y-m-d',
            'options' => 'array',
            'status' => ApiChannelStatusEnum::class,
            'channel_source' => ApiChannelSourceEnum::class,
            'is_company' => ApiDataTypeEnum::class,
        ];
    }


    public function apiAi(): BelongsTo
    {
        return $this->belongsTo(ApiAi::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ApiChannelPost::class);
    }
}
