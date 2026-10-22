<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\ParsingTestRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParsingTestRun extends Model
{
    protected $fillable = [
        'status',
        'industry',
        'source',
        'sample_size',
        'sampled_count',
        'processed_count',
        'target_count',
        'heuristic_reject_count',
        'error_count',
        'extraction_error_count',
        'model',
        'prompt_label',
        'prompt_body',
        'provider_options',
        'note',
        'error_message',
        'last_error',
        'started_at',
        'finished_at',
        'cancel_requested_at',
        'pid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ParsingTestRunStatus::class,
            'provider_options' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ParsingTestMessage::class);
    }
}
