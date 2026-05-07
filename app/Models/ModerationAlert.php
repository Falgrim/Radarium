<?php

namespace App\Models;

use App\Enum\ModerationAlertStatusEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Observers\ModerationAlertObserver;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([ModerationAlertObserver::class])]
class ModerationAlert extends Model
{
    use ModelTableName;

    protected $fillable = [
        'user_id',
        'is_system',
        'table_name',
        'table_row_id',
        'api_channel_post_id',
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

    /**
     * Пост канала, к которому относится обращение: явно сохранённый ID или вывод по карточке каталога.
     */
    public function resolveApiChannelPostId(): ?int
    {
        if ($this->api_channel_post_id !== null) {
            return (int) $this->api_channel_post_id;
        }

        return match ($this->table_name) {
            ModerationAlertTableNameEnum::Builder => Builder::query()
                ->whereKey($this->table_row_id)
                ->value('api_channel_post_id'),
            ModerationAlertTableNameEnum::Specialist => Specialist::query()
                ->whereKey($this->table_row_id)
                ->value('api_channel_post_id'),
            ModerationAlertTableNameEnum::CompanyJob => CompanyJob::query()
                ->whereKey($this->table_row_id)
                ->value('api_channel_post_id'),
            default => null,
        };
    }

    public function allowsApiChannelPost(ApiChannelPost $post): bool
    {
        $resolved = $this->resolveApiChannelPostId();
        if ($resolved !== null) {
            return (int) $post->id === (int) $resolved;
        }

        if ($this->table_name === ModerationAlertTableNameEnum::Author) {
            return (int) $post->api_post_user_id === (int) $this->table_row_id;
        }

        return false;
    }

    public function user(): belongsTo
    {
        return $this->belongsTo(User::class);
    }
}
