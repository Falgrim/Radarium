<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\DictionaryEnum;
use App\Models\Builder;
use App\Models\Specialist;
use Illuminate\Support\Facades\Log;

/**
 * Ограничивает число специализаций у автора в публичном каталоге: по совокупности
 * текстов сообщений с завершённой ИИ-обработкой оставляет не более N наиболее релевантных
 * и записывает один и тот же набор во все активные карточки этого пользователя.
 * Фактически может быть 1–N записей: меньше, если по тексту уверенно определено меньше совпадений.
 */
final class AuthorCatalogSpecialitiesSync
{
    /** Верхняя граница числа специализаций на автора в каталоге (1…N по данным текста). Справочники не меняются. */
    public const DEFAULT_MAX_SPECIALITIES_PER_AUTHOR = 3;

    public function __construct(
        private readonly Dictionary $dictionary,
        private readonly BuilderSpecialityMatcher $builderMatcher,
    ) {
    }

    /**
     * @return array{changed: bool, specialist_ids: list<int>, builder_ids: list<int>, top_speciality_ids: list<int>}
     */
    public function syncSpecialistsForUser(int $apiPostUserId, int $maxSpecialities = self::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR, bool $dryRun = false): array
    {
        $specialists = Specialist::query()
            ->where('api_post_user_id', $apiPostUserId)
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->with(['post' => static function ($q): void {
                $q->select('id', 'post', 'api_post_user_id', 'post_date', 'ai_parse_status');
            }])
            ->orderByDesc('id')
            ->get();

        if ($specialists->isEmpty()) {
            return ['changed' => false, 'specialist_ids' => [], 'builder_ids' => [], 'top_speciality_ids' => []];
        }

        $chunks = [];
        foreach ($specialists as $specialist) {
            $p = $specialist->post;
            if ($p && $p->post !== '') {
                $chunks[] = (string) $p->post;
            }
        }
        $combined = implode("\n\n", $chunks);
        $dict = $this->dictionary->getAll(DictionaryEnum::Speciality, ApiDataTypeEnum::Specialist);
        $scores = $this->dictionary->scoreSpecialistMatchesByList($combined, $dict);
        $top = $this->dictionary->pickTopSpecialityIdsFromScores($scores, $maxSpecialities);

        $specialistIds = [];
        $changed = false;
        foreach ($specialists as $specialist) {
            $specialistIds[] = (int) $specialist->id;
            $old = $specialist->specialities()->pluck('dictionary_speciality_id')->map('intval')->sort()->values()->all();
            $new = $top;
            sort($new);
            if ($old !== $new) {
                $changed = true;
            }
            if (! $dryRun) {
                $this->dictionary->updateRelations(
                    DictionaryEnum::Speciality,
                    'specialist',
                    (int) $specialist->id,
                    $new
                );
            }
        }

        return [
            'changed' => $changed,
            'specialist_ids' => $specialistIds,
            'builder_ids' => [],
            'top_speciality_ids' => $top,
        ];
    }

    /**
     * @return array{changed: bool, specialist_ids: list<int>, builder_ids: list<int>, top_speciality_ids: list<int>}
     */
    public function syncBuildersForUser(int $apiPostUserId, int $maxSpecialities = self::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR, bool $dryRun = false): array
    {
        $builders = Builder::query()
            ->where('api_post_user_id', $apiPostUserId)
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->with(['post' => static function ($q): void {
                $q->select('id', 'post', 'ai_result', 'api_post_user_id', 'post_date', 'ai_parse_status');
            }])
            ->orderByDesc('id')
            ->get();

        if ($builders->isEmpty()) {
            return ['changed' => false, 'specialist_ids' => [], 'builder_ids' => [], 'top_speciality_ids' => []];
        }

        $textParts = [];
        $aiMerged = [];
        foreach ($builders as $builder) {
            $post = $builder->post;
            if (! $post) {
                continue;
            }
            if ($post->post !== '') {
                $textParts[] = (string) $post->post;
            }
            $fromJson = $this->extractAiSpecialitiesFromPostJson($post->ai_result);
            foreach ($fromJson as $s) {
                if ($s !== '' && ! in_array($s, $aiMerged, true)) {
                    $aiMerged[] = $s;
                }
            }
        }

        $combined = implode("\n\n", $textParts);
        $resolved = $this->builderMatcher->resolve(
            $combined,
            $aiMerged === [] ? null : $aiMerged,
            $maxSpecialities
        );
        $top = array_values(array_map('intval', $resolved['ids']));

        $builderIds = [];
        $changed = false;
        foreach ($builders as $builder) {
            $builderIds[] = (int) $builder->id;
            $old = $builder->specialities()->pluck('dictionary_speciality_id')->map('intval')->sort()->values()->all();
            $new = $top;
            sort($new);
            if ($old !== $new) {
                $changed = true;
            }
            if (! $dryRun) {
                $this->dictionary->updateRelations(
                    DictionaryEnum::Speciality,
                    'builder',
                    (int) $builder->id,
                    $new
                );
            }
        }

        return [
            'changed' => $changed,
            'specialist_ids' => [],
            'builder_ids' => $builderIds,
            'top_speciality_ids' => $top,
        ];
    }

    public function syncAfterSpecialistImport(int $apiPostUserId): void
    {
        try {
            $this->syncSpecialistsForUser($apiPostUserId, self::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR, false);
        } catch (\Throwable $e) {
            Log::channel('ai_debug')->warning('[AuthorCatalogSpecialitiesSync] specialist sync failed', [
                'api_post_user_id' => $apiPostUserId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function syncAfterBuilderImport(int $apiPostUserId): void
    {
        try {
            $this->syncBuildersForUser($apiPostUserId, self::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR, false);
        } catch (\Throwable $e) {
            Log::channel('ai_debug')->warning('[AuthorCatalogSpecialitiesSync] builder sync failed', [
                'api_post_user_id' => $apiPostUserId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function extractAiSpecialitiesFromPostJson(mixed $aiResult): array
    {
        if (! is_string($aiResult) || $aiResult === '') {
            return [];
        }

        $decoded = json_decode($aiResult, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach (['specialities', 'service_types'] as $key) {
            if (! isset($decoded[$key]) || ! is_array($decoded[$key])) {
                continue;
            }
            foreach ($decoded[$key] as $v) {
                if (is_string($v) && $v !== '') {
                    $out[] = $v;
                }
            }
        }

        return array_values(array_unique($out));
    }
}
