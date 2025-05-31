<?php

namespace App\Models;

use App\Enum\MailingMessageStatusEnum;
use App\Observers\MailingMessageObserver;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[ObservedBy([MailingMessageObserver::class])]
class MailingMessage extends Model
{
    use SoftDeletes;
    use ModelTableName;

    protected $fillable = [
        'text',
        'is_main',
        'date_send',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'date_send',
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
            'date_send' =>'datetime:Y-m-d H:i:s',
            'status' => MailingMessageStatusEnum::class,
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(MailingMessageLog::class, 'mailing_message_id', 'id')->orderByDesc('created_at');
    }
}
