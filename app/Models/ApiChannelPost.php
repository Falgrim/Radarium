<?php

namespace App\Models;

use App\Enum\ApiChannelPostStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiChannelPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_channel_id',
        'user_login',
        'user_login_id',
        'post_id',
        'post_date',
        'post',
        'ai_parse_status',
        'ai_result',
        'ai_date',
        'api_post_user_id',
        'created_at',
        'updated_at',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'post_date',
        'ai_date',
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
            'post_date' => 'datetime:Y-m-d H:i:s',
            'ai_date' => 'datetime:Y-m-d H:i:s',
            'ai_parse_status' => ApiChannelPostStatusEnum::class,
        ];
    }

    public function apiUser(): BelongsTo
    {
        return $this->belongsTo(ApiPostUser::class, 'api_post_user_id', 'id');
    }
}
