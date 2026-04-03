<?php

namespace App\Models;

use App\Enum\ApiDataTypeEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Enum\DictionaryEnum;
use App\Observers\CompanyJobObserver;
use App\Services\Dictionary;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[ObservedBy([CompanyJobObserver::class])]
class CompanyJob extends Model
{
    use SoftDeletes;
    use ModelTableName;

    protected $fillable = [
        'api_post_user_id',
        'api_channel_post_id',
        'position',
        'company_name',
        'min_price',
        'max_price',
        'duty',
        'requirement',
        'work_schedule',
        'type_of_work',
        'description',
        'period',
        'extra_conditions',
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
            'status' => CompanyJobStatusEnum::class,
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ApiChannelPost::class, 'api_channel_post_id', 'id');
    }

    public function apiPostUser(): HasOne
    {
        return $this->HasOne(ApiPostUser::class, 'id', 'api_post_user_id');
    }

    public function apiChannelPost(): BelongsTo
    {
        return $this->belongsTo(ApiChannelPost::class, 'api_channel_post_id', 'id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CompanyJobReview::class);
    }

    // Костыль, чтобы адинка увидела корректно связи при редактировании
    public function specialitiesForMoonshine(): HasMany
    {
        return $this->hasMany(CompanyJobSpeciality::class, 'company_job_id', 'id')->select('dictionary_speciality_id as id', 'company_job_id');
    }

    public function specialities(): HasMany
    {
        return $this->hasMany(CompanyJobSpeciality::class, 'company_job_id', 'id');
    }

    public function specialtiesWithTitle(): array
    {
        $data = $this->through('specialities')
            ->has('dictionarySpeciality')
            ->where('api_data_type_id', ApiDataTypeEnum::Specialist)
            ->get();
        $result = [];
        foreach ($data as $row) {
            $result[$row->id] = $row->title;
        }
        return $result;
    }

    /**
     * Метод «booted» модели.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (CompanyJob $companyJob) {
            if (isset($companyJob->specialitiesForMoonshine)) {
                $specialitiesCount = is_array($companyJob->specialitiesForMoonshine) ? count($companyJob->specialitiesForMoonshine) : $companyJob->specialitiesForMoonshine->count();
                if ($specialitiesCount) {
                    $specialities = is_array($companyJob->specialitiesForMoonshine) ? $companyJob->specialitiesForMoonshine : $companyJob->specialitiesForMoonshine->toArray();
                    $dictionary = new Dictionary;
                    $dictionary->updateRelations(DictionaryEnum::Speciality, 'companyJob', $companyJob->id, $specialities);
                }
                unset($companyJob->specialitiesForMoonshine);
            }
        });

        static::updating(function (CompanyJob $companyJob) {
            if (isset($companyJob->specialitiesForMoonshine)) {
                if (is_array($companyJob->specialitiesForMoonshine)) {
                    if (count($companyJob->specialitiesForMoonshine)) {
                        $dictionary = new Dictionary;
                        $dictionary->updateRelations(DictionaryEnum::Speciality, 'companyJob', $companyJob->id, $companyJob->specialitiesForMoonshine);
                    }
                } else {
                    if ($companyJob->specialitiesForMoonshine->count()) {
                        $specialities = new Collection($companyJob->specialitiesForMoonshine->toArray());
                        $dictionary = new Dictionary;
                        $dictionary->updateRelations(DictionaryEnum::Speciality, 'companyJob', $companyJob->id, $specialities->pluck('id')->toArray());
                    }
                }

                unset($companyJob->specialitiesForMoonshine);
            }
        });
    }
}
