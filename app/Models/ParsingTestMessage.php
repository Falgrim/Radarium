<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParsingTestMessage extends Model
{
    protected $fillable = [
        'parsing_test_run_id',
        'api_channel_post_id',
        'api_post_user_id',
        'source',
        'author_name',
        'author_login',
        'channel_title',
        'post_date',
        'post_text',
        'ai_type',
        'ai_reason',
        'specialities',
        'model_reply',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'post_date' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ParsingTestRun::class, 'parsing_test_run_id');
    }
}
