<?php

namespace App\Models;

use App\Enum\PaymentServicePayEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\PaymentTariffStatusEnum;
use App\Observers\UserTariffObserver;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([UserTariffObserver::class])]
class UserTariff extends Model
{
    use SoftDeletes;
    use ModelTableName;

    protected $fillable = [
        'user_id',
        'payment_tariff_id',
        'api_data_type',
        'count_month',
        'count_contacts',
        'count_contacts_left',
        'payment_id',
        'status',
        'date_start',
        'date_end',
        'comment',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'date_start',
        'date_end',
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
            'date_start' => 'datetime:Y-m-d H:i:s',
            'date_end' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function user(): belongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentTariff(): BelongsTo
    {
        return $this->belongsTo(PaymentTariff::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
