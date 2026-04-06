<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\AdminSystemPromptScope;
use App\Enum\ApiAiSourceEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Model;

class AdminSystemPromptPreset extends Model
{
    use ModelTableName;

    /** Значение поля api_source: промпт для всех обработчиков ИИ в области (тип выборки). */
    public const API_SOURCE_ALL_HANDLERS = 'all';

    protected $fillable = [
        'scope',
        'name',
        'api_source',
        'body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => AdminSystemPromptScope::class,
        ];
    }

    public function targetsAllAiHandlers(): bool
    {
        return $this->api_source === self::API_SOURCE_ALL_HANDLERS;
    }

    public function apiSourceAsEnum(): ?ApiAiSourceEnum
    {
        if ($this->targetsAllAiHandlers()) {
            return null;
        }

        return ApiAiSourceEnum::tryFrom((string) $this->api_source);
    }

    public function apiSourceAdminLabel(): string
    {
        if ($this->targetsAllAiHandlers()) {
            return 'Все ИИ обработчики';
        }

        $enum = $this->apiSourceAsEnum();

        return (string) ($enum?->toString() ?? $this->api_source);
    }
}
