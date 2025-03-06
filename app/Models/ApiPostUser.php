<?php

namespace App\Models;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\IsCompanyEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\SpecialistStatusEnum;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiPostUser extends Model
{
    use HasFactory;
    use ModelTableName;

    protected $fillable = [
        'user_id',
        'username',
        'first_name',
        'last_name',
        'channel_source',
        'user_type',
        'phone',
        'last_online_date',
        'external_info',
        'is_company',
        'created_at',
        'updated_at',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'last_online_date',
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
            'last_online_date' => 'datetime:Y-m-d H:i:s',
            'channel_source' => ApiChannelSourceEnum::class,
            'is_company' => IsCompanyEnum::class,
            'external_info' => 'array',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ApiChannelPost::class, 'api_post_user_id', 'id');
    }

    public function specialists(): HasMany
    {
        return $this->hasMany(Specialist::class, 'api_post_user_id', 'id');
    }

    public function specialtiesWithShortName(): array
    {
        $data = $this->through('specialists')
            ->has('specialities')
            ->get();

        $result = [];
        foreach ($data as $row) {
            $result[$row->id] = $row->dictionarySpeciality->short_name ? $row->dictionarySpeciality->short_name : $row->dictionarySpeciality->title;
        }
        return $result;
    }

    public function companies(): HasMany
    {
        return $this->hasMany(CompanyJob::class, 'api_post_user_id', 'id');
    }

    public function lastSpecialistReview(): string
    {
        $data = Review::where('api_post_user_id', $this->id)
            ->where('status', ReviewStatusEnum::Active)
            ->orderByDesc('created_at')
            ->first();

        return $data ? $data['text'] : '';
    }

    public function specialistReviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function getAvrSpecialistRating()
    {
        $avg = ApiPostUser::withAvg('specialistReviews', 'rating')
            ->where('id', $this->id)
            //->where('rating', '>', 0)
            ->first();
        return is_null($avg->reviews_avg_rating) ? 0 : number_format($avg->reviews_avg_rating, 1, '.', ' ');
    }

    public function specialistData(): array
    {
        $data = Specialist::where('api_post_user_id', $this->id)
            ->where('status', SpecialistStatusEnum::Active)
            ->get();

        $result = [
            'experience' => [],
            'soft_experience' => [],
            'education' => [],
            'work_schedule' => [],
            'total_work_project' => [],
            'type_of_work' => [],
            'about' => [],
            'spec_requirements' => [],
            'link_resume' => [],
        ];
        foreach ($data as $row) {
            foreach ($result as $key => $item) {
                if ($row->{$key}) {
                    $result[$key][$row->id] = $row->{$key};
                }
            }
        }

        return $result;
    }

    public function lastPost(): ?ApiChannelPost
    {
        $data = ApiChannelPost::where('api_post_user_id', $this->id)
            ->where('ai_parse_status', ApiChannelPostStatusEnum::Complete)
            ->orderByDesc('created_at')
            ->first();

        return $data;
    }
}
