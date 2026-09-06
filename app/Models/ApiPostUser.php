<?php

namespace App\Models;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostUserMailingStatusEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Services\ReadTelegramChats;
use App\Support\CatalogLastMessage;
use App\Support\TelegramPostUrl;
use App\Traits\ModelTableName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
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
            'send_welcome_msg' => ApiPostUserMailingStatusEnum::class,
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

    /**
     * Последний полностью разобранный пост (для списков — под eager load вместо N+1 в lastPost()).
     */
    public function latestCompletePost(): HasOne
    {
        return $this->hasOne(ApiChannelPost::class, 'api_post_user_id', 'id')
            ->where('ai_parse_status', ApiChannelPostStatusEnum::Complete)
            ->latestOfMany('post_date');
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
        if ($this->relationLoaded('specialists') && $this->specialists->isNotEmpty()
            && $this->specialists->every(static fn (Specialist $s) => $s->relationLoaded('specialities'))) {
            $rows = [];
            foreach ($this->specialists as $specialist) {
                foreach ($specialist->specialities as $ss) {
                    if ($ss->dictionarySpeciality) {
                        $rows[] = $ss;
                    }
                }
            }
        } else {
            $rows = $this->through('specialists')
                ->has('specialities')
                ->with('dictionarySpeciality')
                ->get()
                ->all();
        }

        $result = [];
        foreach ($rows as $row) {
            $d = $row->dictionarySpeciality;
            if (! $d) {
                continue;
            }
            if ($d->short_name && $d->short_name != $d->title) {
                $name = $d->short_name.' - '.$d->title;
            } elseif ($d->short_name) {
                $name = $d->short_name;
            } else {
                $name = $d->title;
            }

            if ($substr) {
                $name = Str::limit($name, $substr);
            }

            $result[$name] = [
                'name' => $name,
                'key_words' => $d->key_words,
            ];
        }

        return $result;
    }

    /**
     * Специализации строителей для данного пользователя (по активным builders).
     * Формат как у specialtiesWithShortName для совместимости с profileSkillsFront.
     */
    public function builderSpecialtiesWithShortName(int $substr = 0): array
    {
        $builderIds = $this->builders()
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->pluck('id');
        if ($builderIds->isEmpty()) {
            return [];
        }

        $max = \App\Services\AuthorCatalogSpecialitiesSync::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR;
        $bySpecialityId = [];
        $items = BuilderSpeciality::whereIn('builder_id', $builderIds)
            ->with('dictionarySpeciality')
            ->orderBy('dictionary_speciality_id')
            ->get();

        foreach ($items as $bs) {
            $specialityId = (int) $bs->dictionary_speciality_id;
            if (isset($bySpecialityId[$specialityId])) {
                continue;
            }

            $d = $bs->dictionarySpeciality;
            if (! $d || (int) $d->api_data_type_id !== ApiDataTypeEnum::Builder->value) {
                continue;
            }
            $name = $d->short_name && $d->short_name != $d->title
                ? $d->short_name.' - '.$d->title
                : ($d->short_name ?: $d->title);
            if ($substr) {
                $name = Str::limit($name, $substr);
            }
            $bySpecialityId[$specialityId] = [
                'name' => $name,
                'key_words' => $d->key_words,
            ];
        }

        if (count($bySpecialityId) > $max) {
            $bySpecialityId = array_slice($bySpecialityId, 0, $max, true);
        }

        $result = [];
        foreach ($bySpecialityId as $row) {
            $result[$row['name']] = $row;
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
        if ($this->relationLoaded('specialists')) {
            $data = $this->specialists->filter(
                static fn (Specialist $s) => $s->status === ApiPostAiStatusEnum::Active
            )->values();
        } else {
            $data = Specialist::where('api_post_user_id', $this->id)
                ->where('status', ApiPostAiStatusEnum::Active)
                ->get();
        }

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
        $data = Builder::where('api_post_user_id', $this->id)
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
        if ($this->relationLoaded('latestCompletePost')) {
            return $this->latestCompletePost;
        }

        return ApiChannelPost::where('api_post_user_id', $this->id)
            ->where('ai_parse_status', ApiChannelPostStatusEnum::Complete)
            ->orderByDesc('post_date')
            ->first();
    }

    /**
     * Последнее сообщение среди постов, по которым созданы записи проектировщиков (specialists).
     * По этой же дате каталог проектировщиков сортирует авторов.
     */
    public function lastSpecialistPost(): ?ApiChannelPost
    {
        return $this->lastCardPost(CatalogLastMessage::SPECIALIST_RELATION, fn (): HasMany => $this->specialists());
    }

    /**
     * Последнее сообщение среди постов, по которым созданы записи строителей (builders).
     * Используется на странице поиска строителей.
     */
    public function lastBuilderPost(): ?ApiChannelPost
    {
        return $this->lastCardPost(CatalogLastMessage::BUILDER_RELATION, fn (): HasMany => $this->builders());
    }

    /**
     * Запасной путь для одиночной модели: в списках каталога значение уже подложено
     * пачкой в {@see CatalogLastMessage}, чтобы не делать запросы на каждую строку.
     *
     * @param  callable(): HasMany  $cards
     */
    private function lastCardPost(string $preloadedRelation, callable $cards): ?ApiChannelPost
    {
        if ($this->relationLoaded($preloadedRelation)) {
            return $this->getRelation($preloadedRelation);
        }

        $postIds = $cards()
            ->where('status', ApiPostAiStatusEnum::Active)
            ->pluck('api_channel_post_id')
            ->filter(fn ($id) => $id !== null && (int) $id > 0)
            ->values();

        if ($postIds->isEmpty()) {
            return null;
        }

        return ApiChannelPost::whereIn('id', $postIds)
            ->where('ai_parse_status', ApiChannelPostStatusEnum::Complete)
            ->orderByDesc('post_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Телефон или @username — прямой контакт вне исходного канала/группы.
     */
    public function hasDirectTelegramContact(): bool
    {
        return trim((string) ($this->phone ?? '')) !== ''
            || trim((string) ($this->username ?? '')) !== '';
    }

    /**
     * Подсказка для каталога, когда прямого контакта нет (связь через ответ в источнике).
     */
    public function catalogIndirectContactHint(): string
    {
        return 'Прямой контакт (телефон или @username) недоступен. Напишите автору ответом на его сообщение в Telegram-канале или группе, откуда взято объявление.';
    }

    /**
     * Ссылка на исходное сообщение в публичном канале/группе (если известны link канала и post_id).
     */
    public function lastBuilderPostTelegramUrl(): ?string
    {
        $post = $this->lastBuilderPost();
        if (! $post) {
            return null;
        }

        $post->loadMissing('channel');

        return TelegramPostUrl::fromChannelLinkAndPostId(
            $post->channel?->link,
            $post->post_id !== null ? (int) $post->post_id : null
        );
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

    /**
     * Полный текст последнего сообщения в каталоге: при закрытом контакте — размытие найденных контактов.
     */
    public static function prepareLastPostText(string $post, bool $checkOpenContact): HtmlString
    {
        $post = self::sanitizeCatalogPostString($post);

        if ($checkOpenContact) {
            return new HtmlString(nl2br(e($post)));
        }

        return new HtmlString(nl2br(self::wrapContactDataWithBlur($post)));
    }

    /**
     * Без валидного UTF-8 preg с модификатором u даёт false — совпадений нет и blur не применяется.
     */
    private static function sanitizeCatalogPostString(string $post): string
    {
        $post = (string) $post;
        if ($post === '') {
            return $post;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $post);

        return $converted !== false ? $converted : $post;
    }

    /**
     * Оборачивает распознанные контакты в span с blur; остальной текст экранируется.
     */
    private static function wrapContactDataWithBlur(string $post): string
    {
        $post = html_entity_decode($post, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $post = self::sanitizeCatalogPostString($post);
        $post = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}]/u', '', $post) ?? $post;

        // \p{Nd} — любые десятичные цифры (в т.ч. не ASCII); плюс — ASCII и полноширинный (U+FF0B, U+FE62)
        $plus7 = '(?:\+|[\x{FF0B}\x{FE62}])7';
        $prefix78 = '(?:8|7)';

        $patterns = [
            '/'.$plus7.'(?:[\s\p{Zs}()._-]*\p{Nd}){10}(?!\p{Nd})/u',
            '/'.$prefix78.'(?:[\s\p{Zs}()._-]*\p{Nd}){10}(?!\p{Nd})/u',
            '/'.$plus7.'[\s\p{Zs}()._-]*\p{Nd}{3}[\s\p{Zs}()._-]*\p{Nd}{3}[\s\p{Zs}()._-]*\p{Nd}{2}[\s\p{Zs}()._-]*\p{Nd}{2}(?!\p{Nd})/u',
            '/'.$prefix78.'[\s\p{Zs}()._-]*\p{Nd}{3}[\s\p{Zs}()._-]*\p{Nd}{3}[\s\p{Zs}()._-]*\p{Nd}{2}[\s\p{Zs}()._-]*\p{Nd}{2}(?!\p{Nd})/u',
            '/(?:[\p{L}a-zA-Z0-9._%+-]+)@(?:[\p{L}a-zA-Z0-9.-]+)\.(?:[a-zA-Z\p{L}]{2,})/u',
            '/(?i)(?:https?:\/\/)?(?:t\.me|telegram\.me)\/[a-zA-Z][a-zA-Z0-9_]{3,31}(?:\/[a-zA-Z0-9_]+)?/',
            '/(?i)https?:\/\/(?:wa\.me|api\.whatsapp\.com)\/\+?\d[\d]*/',
            // «tg: username» / «telegram: @nick» без ссылки и без @ в тексте
            '/(?i)(?:tg|telegram)\s*:\s*@?[^\s,;.!?]+/u',
            // Telegram @ник: 5–32 символа, первый — буква
            '/(?<![a-zA-Z0-9_])@[a-zA-Z][a-zA-Z0-9_]{4,31}(?![a-zA-Z0-9_])/u',
        ];

        $ranges = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $post, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    if ($match[0] === '') {
                        continue;
                    }
                    $start = $match[1];
                    $ranges[] = [$start, $start + strlen($match[0])];
                }
            }
        }

        if ($ranges === []) {
            return e($post);
        }

        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($ranges as $range) {
            if ($merged === []) {
                $merged[] = $range;
                continue;
            }
            $lastIdx = count($merged) - 1;
            if ($range[0] <= $merged[$lastIdx][1]) {
                $merged[$lastIdx][1] = max($merged[$lastIdx][1], $range[1]);
            } else {
                $merged[] = $range;
            }
        }

        $html = '';
        $cursor = 0;
        foreach ($merged as [$start, $end]) {
            if ($start > $cursor) {
                $html .= e(substr($post, $cursor, $start - $cursor));
            }
            $chunk = substr($post, $start, $end - $start);
            $html .= '<span class="last-post-contact-blur">'.e($chunk).'</span>';
            $cursor = $end;
        }
        if ($cursor < strlen($post)) {
            $html .= e(substr($post, $cursor));
        }

        return $html;
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
            $skill = trim($skill);
            $result[Str::lower($skill)] = $skill;
        }

        return $result;
    }
}
