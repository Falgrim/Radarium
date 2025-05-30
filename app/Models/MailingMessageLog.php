<?php

namespace App\Models;

use App\Enum\ApiDataTypeEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Enum\DictionaryEnum;
use App\Enum\MailingMessageLogStatusEnum;
use App\Services\Dictionary;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class MailingMessageLog extends Model
{
    use ModelTableName;

    protected $fillable = [
        'mailing_message_id',
        'api_post_user_id',
        'msg_id',
        'status',
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
            'status' => MailingMessageLogStatusEnum::class,
        ];
    }

    public function mailingMessage(): BelongsTo
    {
        return $this->belongsTo(MailingMessage::class, 'mailing_message_id', 'id');
    }

    public function apiPostUser(): HasOne
    {
        return $this->HasOne(ApiPostUser::class, 'id', 'api_post_user_id');
    }
}
