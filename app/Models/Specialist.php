<?php

namespace App\Models;

use App\Enum\DictionaryEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\SpecialistStatusEnum;
use App\Services\Dictionary;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Specialist extends Model
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
            'status' => SpecialistStatusEnum::class,
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
        $data = Review::where('specialist_id', $this->id)
            ->where('status', ReviewStatusEnum::Active)
            ->orderByDesc('created_at')
            ->first();

        return $data ? $data['text'] : '';
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function getAvrRating()
    {
        $avg = Specialist::withAvg(Review::table(), 'rating')
            ->where('id', $this->id)
            //->where('rating', '>', 0)
            ->first();
        return is_null($avg->reviews_avg_rating) ? 0 : number_format($avg->reviews_avg_rating, 1, '.', ' ');
    }

    // Костыль, чтобы адинка увидела корректно связи при редактировании
    public function specialitiesForMoonshine(): HasMany
    {
        return $this->hasMany(SpecialistSpeciality::class, 'specialist_id', 'id')->select('dictionary_speciality_id as id', 'specialist_id');
    }

    public function specialities(): HasMany
    {
        return $this->hasMany(SpecialistSpeciality::class, 'specialist_id', 'id');
    }

    public function specialtiesWithTitle(): array
    {
        $data = $this->through('specialities')->has('dictionarySpeciality')->get();
        $result = [];
        foreach ($data as $row) {
            $result[$row->id] = trim($row->okso_code.' '.$row->title);
        }
        return $result;
    }

    /**
     * Метод «booted» модели.
     */
    protected static function booted(): void
    {
        parent::boot();

        static::creating(function (Specialist $specialist) {
            if (isset($specialist->specialitiesForMoonshine)) {
                $specialitiesCount = is_array($specialist->specialitiesForMoonshine) ? count($specialist->specialitiesForMoonshine) : $specialist->specialitiesForMoonshine->count();
                if ($specialitiesCount) {
                    $specialities = is_array($specialist->specialitiesForMoonshine) ? $specialist->specialitiesForMoonshine : $specialist->specialitiesForMoonshine->toArray();
                    $dictionary = new Dictionary;
                    $dictionary->updateRelations(DictionaryEnum::Speciality, 'specialist', $specialist->id, $specialities);
                }
                unset($specialist->specialitiesForMoonshine);
            }
        });

        static::updating(function (Specialist $specialist) {
            if (isset($specialist->specialitiesForMoonshine)) {
                if (is_array($specialist->specialitiesForMoonshine)) {
                    if (count($specialist->specialitiesForMoonshine)) {
                        $dictionary = new Dictionary;
                        $dictionary->updateRelations(DictionaryEnum::Speciality, 'specialist', $specialist->id, $specialist->specialitiesForMoonshine);
                    }
                } else {
                    if ($specialist->specialitiesForMoonshine->count()) {
                        $specialities = new Collection($specialist->specialitiesForMoonshine->toArray());
                        $dictionary = new Dictionary;
                        $dictionary->updateRelations(DictionaryEnum::Speciality, 'specialist', $specialist->id, $specialities->pluck('id')->toArray());
                    }
                }

                unset($specialist->specialitiesForMoonshine);
            }
        });
    }
}
