<?php

namespace App\Models;

use App\Enum\ApiDataTypeEnum;
use App\Enum\DictionaryEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Observers\BuilderObserver;
use App\Services\Dictionary;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[ObservedBy([BuilderObserver::class])]
class Builder extends Model
{
    use HasFactory;
    use SoftDeletes;
    use ModelTableName;

    protected $fillable = [
        'api_post_user_id',
        'api_channel_post_id',
        'experience',
        'soft_experience',
        'education',
        'work_schedule',
        'total_work_project',
        'type_of_work',
        'price_by_hour',
        'price_by_project',
        'price_by_month',
        'about',
        'spec_requirements',
        'link_resume',
        'status',
        'ai_type',
        'ai_reason',
        'contact_info',
        'post_date',
        'region',
        'service_type_raw',
        'service_types',
        'object_types',
        'performer_type_raw',
        'performer_type',
        'equipment_skills_json',
        'legal_form',
        'location_city',
        'location_region',
        'price_comment',
        'created_at',
        'updated_at',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'post_date',
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
            'post_date' =>'datetime:Y-m-d H:i:s',
            'status' => ApiPostAiStatusEnum::class,
            'service_types' => 'array',
            'object_types' => 'array',
            'equipment_skills_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(ApiPostUser::class, 'api_post_user_id','id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ApiChannelPost::class, 'api_channel_post_id', 'id');
    }

    public function lastReview(): string
    {
        $data = BuilderReview::where('specialist_id', $this->id)
            ->where('status', ReviewStatusEnum::Active)
            ->orderByDesc('created_at')
            ->first();

        return $data ? $data['text'] : '';
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(BuilderReview::class);
    }

    public function getAvrRating()
    {
        $avg = Builder::withAvg(BuilderReview::table(), 'rating')
            ->where('id', $this->id)
            //->where('rating', '>', 0)
            ->first();
        return is_null($avg->reviews_avg_rating) ? 0 : number_format($avg->reviews_avg_rating, 1, '.', ' ');
    }

    // Костыль, чтобы адинка увидела корректно связи при редактировании
    public function specialitiesForMoonshine(): HasMany
    {
        return $this->hasMany(BuilderSpeciality::class, 'builder_id', 'id')->select('dictionary_speciality_id as id', 'builder_id');
    }

    public function specialities(): HasMany
    {
        return $this->hasMany(BuilderSpeciality::class, 'builder_id', 'id');
    }

    public function specialtiesWithTitle(): array
    {
        $data = $this->through('specialities')
            ->has('dictionarySpeciality')
            ->where('api_data_type_id', ApiDataTypeEnum::Builder)
            ->get();
        $result = [];
        foreach ($data as $row) {
            $result[$row->id] = $row->title;
        }
        return $result;
    }

    public function specialtiesWithShortName(int $substr = 0): array
    {
        $data = $this->through('specialities')
            ->has('dictionarySpeciality')
            ->where('api_data_type_id', ApiDataTypeEnum::Builder)
            ->get();
        $result = [];
        foreach ($data as $row) {
            if ($row->dictionarySpeciality->short_name AND $row->dictionarySpeciality->short_name != $row->dictionarySpeciality->title) {
                $name = $row->dictionarySpeciality->short_name.' - '.$row->dictionarySpeciality->title;
            } elseif ($row->dictionarySpeciality->short_name) {
                $name = $row->dictionarySpeciality->short_name;
            } else {
                $name = $row->dictionarySpeciality->title;
            }

            if ($substr) {
                $name = Str::limit($name, $substr);
            }

            $result[$name] = [
                'name' => $name,
                'key_words' => $row->dictionarySpeciality->key_words,
            ];
        }
        return $result;
    }

    /**
     * Метод «booted» модели.
     */
    protected static function booted(): void
    {
        parent::boot();

        static::creating(function (Builder $builder) {
            if (isset($builder->specialitiesForMoonshine)) {
                $specialitiesCount = is_array($builder->specialitiesForMoonshine) ? count($builder->specialitiesForMoonshine) : $builder->specialitiesForMoonshine->count();
                if ($specialitiesCount) {
                    $specialities = is_array($builder->specialitiesForMoonshine) ? $builder->specialitiesForMoonshine : $builder->specialitiesForMoonshine->toArray();
                    $dictionary = new Dictionary;
                    $dictionary->updateRelations(DictionaryEnum::Speciality, 'builder', $builder->id, $specialities);
                }
                unset($builder->specialitiesForMoonshine);
            }
        });

        static::updating(function (Builder $builder) {
            if (isset($builder->specialitiesForMoonshine)) {
                if (is_array($builder->specialitiesForMoonshine)) {
                    if (count($builder->specialitiesForMoonshine)) {
                        $dictionary = new Dictionary;
                        $dictionary->updateRelations(DictionaryEnum::Speciality, 'builder', $builder->id, $builder->specialitiesForMoonshine);
                    }
                } else {
                    if ($builder->specialitiesForMoonshine->count()) {
                        $specialities = new Collection($builder->specialitiesForMoonshine->toArray());
                        $dictionary = new Dictionary;
                        $dictionary->updateRelations(DictionaryEnum::Speciality, 'builder', $builder->id, $specialities->pluck('id')->toArray());
                    }
                }

                unset($builder->specialitiesForMoonshine);
            }
        });
    }
}
