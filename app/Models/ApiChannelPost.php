<?php

namespace App\Models;

use App\Enum\ApiChannelPostStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApiChannelPost extends Model
{
    use HasFactory;
    use ModelTableName;

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
        'ai_provider_used',
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

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ApiChannel::class, 'api_channel_id', 'id');
    }

    public function specialist(): HasOne
    {
        return $this->HasOne(Specialist::class, 'api_channel_post_id', 'id');
    }

    public function companyJob(): HasOne
    {
        return $this->HasOne(CompanyJob::class, 'api_channel_post_id', 'id');
    }

    public function apiPostUser(): HasOne
    {
        return $this->HasOne(ApiPostUser::class, 'id', 'api_post_user_id');
    }
}
