<?php

namespace App\Models;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DictionarySpeciality extends Model
{
    use ModelTableName;

    protected $fillable = [
        'title',
        'short_name',
        'okso_code',
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

    public function specialists(): HasMany
    {
        return $this->hasMany(SpecialistSpeciality::class);
    }

    public function companyJobs(): HasMany
    {
        return $this->hasMany(CompanyJobSpeciality::class);
    }
}
