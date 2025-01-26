<?php

namespace App\Models;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CompanyJobSpeciality extends Model
{
    use ModelTableName;

    protected $fillable = [
        'company_job_id',
        'dictionary_speciality_id',
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
        ];
    }

    public function companyJob(): HasMany
    {
        return $this->hasMany(CompanyJob::class);
    }

    public function dictionarySpeciality(): HasOne
    {
        return $this->hasOne(DictionarySpeciality::class, 'id', 'dictionary_speciality_id');
    }
}
