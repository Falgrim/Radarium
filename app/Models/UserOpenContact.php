<?php

namespace App\Models;

use App\Enum\PaymentServicePayEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\PaymentTariffStatusEnum;
use App\Observers\UserOpenContactObserver;
use App\Observers\UserTariffObserver;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([UserOpenContactObserver::class])]
class UserOpenContact extends Model
{
    use ModelTableName;

    protected $fillable = [
        'user_tariff_id',
        'user_id',
        'api_post_user_id',
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
        ];
    }

    public function user(): belongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiPostUser(): BelongsTo
    {
        return $this->belongsTo(ApiPostUser::class);
    }
}
