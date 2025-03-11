<?php

namespace App\Models;

use App\Enum\ReviewStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuilderReviewCustomField extends Model
{
    use HasFactory;
    use ModelTableName;

    /**
     * Следует ли обрабатывать временные метки модели.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'title',
        'value',
    ];

    public function review(): belongsTo
    {
        return $this->belongsTo(BuilderReview::class);
    }
}
