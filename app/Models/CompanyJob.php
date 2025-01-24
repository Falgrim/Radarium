<?php

namespace App\Models;

use App\Enum\CompanyJobStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyJob extends Model
{
    use SoftDeletes;
    use ModelTableName;

    protected $fillable = [
        'api_post_user_id',
        'api_channel_post_id',
        'position',
        'company_name',
        'min_price',
        'max_price',
        'duty',
        'requirement',
        'work_schedule',
        'type_of_work',
        'description',
        'period',
        'extra_conditions',
        'status',
        'ai_type',
        'ai_reason',
        'contact_info',
        'post_date',
        'created_at',
        'updated_at',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'post_date',
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
            'status' => CompanyJobStatusEnum::class,
        ];
    }

    public function apiPostUser(): HasOne
    {
        return $this->HasOne(ApiPostUser::class, 'id', 'api_post_user_id');
    }

    public function apiChannelPost(): BelongsTo
    {
        return $this->belongsTo(ApiChannelPost::class, 'api_channel_post_id', 'id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CompanyJobReview::class);
    }

    public function specialties(): HasMany
    {
        return $this->hasMany(SpecialistSpeciality::class);
    }
}
