<?php

namespace App\Models;

use App\Enum\SpecialistStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

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
            'status' => SpecialistStatusEnum::class,
        ];
    }

    public function user(): HasOne
    {
        return $this->HasOne(ApiPostUser::class, 'id', 'api_post_user_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ApiChannelPost::class, 'api_channel_post_id', 'id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
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
            $result[$row->id] = $row->title;
        }
        return $result;
    }

    public function specialtiesTemp(): HasManyThrough
    {
        return $this->hasManyThrough(
            DictionarySpeciality::class,
            SpecialistSpeciality::class,
            'dictionary_speciality_id', // Внешний ключ в таблице `SpecialistSpeciality` ...
            'id', // Внешний ключ в таблице `DictionarySpeciality` ...
            'id', // Локальный ключ в таблице `Specialist` ...
            'specialist_id' // Локальный ключ в таблице `SpecialistSpeciality` ...
        );
    }
}
