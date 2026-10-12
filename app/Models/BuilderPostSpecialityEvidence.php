<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuilderPostSpecialityEvidence extends Model
{
    use ModelTableName;

    protected $table = 'builder_post_speciality_evidence';

    protected $fillable = [
        'api_channel_post_id',
        'dictionary_speciality_id',
        'text_score',
        'ai_score',
        'combined',
        'matcher_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'text_score' => 'float',
            'ai_score' => 'float',
            'combined' => 'float',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ApiChannelPost::class, 'api_channel_post_id');
    }

    public function dictionarySpeciality(): BelongsTo
    {
        return $this->belongsTo(DictionarySpeciality::class, 'dictionary_speciality_id');
    }
}
