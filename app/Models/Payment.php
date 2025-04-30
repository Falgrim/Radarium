<?php

namespace App\Models;

use App\Enum\PaymentServicePayEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\PaymentTariffStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;
    use ModelTableName;

    protected $fillable = [
        'user_id',
        'payment_tariff_id',
        'payment_service',
        'payment_hash',
        'sum',
        'status',
        'service_pay_id',
        'description',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    public const PAYMENT_TTL = 60*60; // Время, через которое платеж будет отменен

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
            'status' => PaymentStatusEnum::class,
            'service_pay_id' => PaymentServicePayEnum::class,
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
}
