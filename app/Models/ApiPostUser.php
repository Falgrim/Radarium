<?php

namespace App\Models;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiPostUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'username',
        'first_name',
        'last_name',
        'channel_source',
        'user_type',
        'phone',
        'last_online_date',
        'external_info',
        'created_at',
        'updated_at',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'last_online_date',
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
            'last_online_date' => 'datetime:Y-m-d H:i:s',
            'channel_source' => ApiChannelSourceEnum::class,
            'external_info' => 'array',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ApiChannelPost::class, 'api_post_user_id', 'id');
    }
}
