<?php

namespace App\Models;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ModerationAlertStatusEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ApiPostUser;
use App\Models\Specialist;
use App\Models\CompanyJob;
use App\Models\Builder;

class ModerationAlert extends Model
{
    use ModelTableName;

    protected $fillable = [
        'user_id',
        'is_system',
        'table_name',
        'table_row_id',
        'description',
        'comment',
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
            'status' => ModerationAlertStatusEnum::class,
            'is_system' => ModerationAlertSystemEnum::class,
            'table_name' => ModerationAlertTableNameEnum::class,
        ];
    }

    public function getObject(): ?array
    {
        if (!$this->table_row_id) {
            return null;
        }

        $object = '\\App\\Models\\'.$this->table_name->value;
        $data = $object::where('id', $this->table_row_id)->first();

        return $data ? $data->toArray() : null;
    }

    public function user(): belongsTo
    {
        return $this->belongsTo(User::class);
    }
}
