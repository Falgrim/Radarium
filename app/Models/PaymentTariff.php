<?php

namespace App\Models;

use App\Enum\PaymentTariffStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTariff extends Model
{
    use SoftDeletes;
    use ModelTableName;

    protected $fillable = [
        'api_data_type',
        'title',
        'description',
        'img_banner',
        'price',
        'count_contacts',
        'status',
        'period', // Срок в днях
        'is_hot',
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
            'status' => PaymentTariffStatusEnum::class,
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('updated_at');
    }

    /*public function user(): belongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function reviewCustomFields(): HasManyoO
    {
        return $this->hasMany(ReviewCustomField::class)->orderBy('title');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(ApiPostUser::class, 'api_post_user_id','id');
    }*/
}
