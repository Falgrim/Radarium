<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorSpecialityScore extends Model
{
    use ModelTableName;

    protected $table = 'author_speciality_scores';

    protected $fillable = [
        'api_post_user_id',
        'dictionary_speciality_id',
        'score',
        'share',
        'support_posts',
        'rank',
        'is_selected',
        'last_evidence_at',
        'computed_at',
        'matcher_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'share' => 'float',
            'support_posts' => 'integer',
            'rank' => 'integer',
            'is_selected' => 'boolean',
            'last_evidence_at' => 'datetime',
            'computed_at' => 'datetime',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(ApiPostUser::class, 'api_post_user_id');
    }

    public function dictionarySpeciality(): BelongsTo
    {
        return $this->belongsTo(DictionarySpeciality::class, 'dictionary_speciality_id');
    }
}
