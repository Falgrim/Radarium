<?php

namespace App\Models;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Services\ReadTelegramChats;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApiPostUser extends Model
{
    use HasFactory;
    use ModelTableName;

    protected $fillable = [
        'user_id',
        'username',
        'photo',
        'first_name',
        'last_name',
        'channel_source',
        'user_type',
        'phone',
        'last_online_date',
        'external_info',
        'is_company',
        'send_welcome_msg',
        'send_new_msg',
        'last_post_date',
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
            'last_post_date' => 'datetime:Y-m-d H:i:s',
            'channel_source' => ApiChannelSourceEnum::class,
            'is_company' => ApiDataTypeEnum::class,
            'external_info' => 'array',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ApiChannelPost::class, 'api_post_user_id', 'id')->orderByDesc('post_date');
    }

    public function postsComplete(): HasMany
    {
        return $this->hasMany(ApiChannelPost::class, 'api_post_user_id', 'id')
            ->where('ai_parse_status', ApiChannelPostStatusEnum::Complete)
            ->orderByDesc('post_date');
    }

    public function specialists(): HasMany
    {
        return $this->hasMany(Specialist::class, 'api_post_user_id', 'id');
    }

    public function builders(): HasMany
    {
        return $this->hasMany(Builder::class, 'api_post_user_id', 'id');
    }

    public function specialtiesWithShortName(int $substr = 0): array
    {
        $data = $this->through('specialists')
            ->has('specialities')
            ->with('dictionarySpeciality')
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

    public function builderReviews(): HasMany
    {
        return $this->hasMany(BuilderReview::class);
    }

    public function getAvrSpecialistRating()
    {
        $avg = ApiPostUser::withAvg(['specialistReviews' => function ($query) {
            $query->where('rating', '>', 0);
        }], 'rating')
            ->where('id', $this->id)
            ->first();

        return is_null($avg->specialist_reviews_avg_rating) ? 0 : number_format($avg->specialist_reviews_avg_rating, 1, '.', ' ');
    }

    public function getAvrBuilderRating()
    {
        $avg = ApiPostUser::withAvg(['builderReviews' => function ($query) {
            $query->where('rating', '>', 0);
        }], 'rating')
            ->where('id', $this->id)
            ->first();

        return is_null($avg->builder_reviews_avg_rating) ? 0 : number_format($avg->builder_reviews_avg_rating, 1, '.', ' ');
    }

    public function specialistData(): array
    {
        $data = Specialist::where('api_post_user_id', $this->id)
            ->where('status', ApiPostAiStatusEnum::Active)
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
                    $result[$key][md5($row->{$key})] = $row->{$key};
                }
            }
        }

        return $result;
    }

    public function builderData(): array
    {
        $data = BuilderReview::where('api_post_user_id', $this->id)
            ->where('status', ApiPostAiStatusEnum::Active)
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
                    $result[$key][md5($row->{$key})] = $row->{$key};
                }
            }
        }

        return $result;
    }

    public function lastPost(): ?ApiChannelPost
    {
        $data = ApiChannelPost::where('api_post_user_id', $this->id)
            //->where('ai_parse_status', ApiChannelPostStatusEnum::Complete)
            ->orderByDesc('post_date')
            ->first();

        return $data;
    }

    public function lastPostAnyStatus(): ?ApiChannelPost
    {
        $data = ApiChannelPost::where('api_post_user_id', $this->id)
            ->orderByDesc('post_date')
            ->first();

        return $data;
    }

    public function getPhoto()
    {
        if (empty($this->photo)) {
            return asset('images/avatar.jpg');
        }

        return asset('storage/'.ReadTelegramChats::PHOTO_PATH.'/'.$this->photo);
    }

    public static function prepareLastPostText(string $post, bool $checkOpenContact)
    {
        $post = Str::limit($post, 250);
        if (!$checkOpenContact) {
            $post = preg_replace(
                '/(?:\+7|8|7)[\s\-()]*\d{3}[\s\-()]*\d{3}[\s\-()]*\d{2}[\s\-()]*\d{2}/',
                '*********',
                $post
            );
        }

        return $post;
    }

    public static function profileSkillsFront(array $softExperience, array $specialties) {
        $params = [];

        if (count($softExperience)) {
            foreach ($softExperience as $item) {
                $tmp = explode(';', $item);
                $params = $params+self::softExperienceUniq($tmp);
            }
        }

        return $params;
    }

    public static function softExperienceUniq(array $skills)
    {
        $result = [];
        foreach ($skills as $skill) {
            $result[Str::lower($skill)] = $skill;
        }

        return $result;
    }
}
