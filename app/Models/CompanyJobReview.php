<?php

namespace App\Models;

use App\Enum\ReviewStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyJobReview extends Model
{
    use SoftDeletes;
    use ModelTableName;

    protected $fillable = [
        'api_post_user_id',
        'company_job_id',
        'user_id',
        'text',
        'can_edit',
        'status',
        'rating',
        'created_at',
        'updated_at',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
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
            'status' => ReviewStatusEnum::class,
        ];
    }

    public function user(): belongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function companyJob(): BelongsTo
    {
        return $this->belongsTo(CompanyJob::class);
    }

    public function reviewCustomFields(): HasMany
    {
        return $this->hasMany(CompanyJobReviewCustomField::class)->orderBy('title');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(ApiPostUser::class, 'api_post_user_id','id');
    }
}
