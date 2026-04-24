<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enum\ActiveAuthorsReportTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\Builder;
use App\Models\CompanyJob;
use App\Models\Specialist;
use App\Support\Admin\ActiveAuthorsReportRow;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final class ActiveAuthorsReportService
{
    public function paginateUsers(
        ActiveAuthorsReportTypeEnum $type,
        ?Carbon $dateFrom,
        ?Carbon $dateTo,
        int $perPage = 50,
        bool $onlyMultipleActiveCards = false,
    ): LengthAwarePaginator {
        $base = $this->aggregationSubquery($type, $dateFrom, $dateTo);
        $userTable = (new ApiPostUser)->getTable();

        return ApiPostUser::query()
            ->joinSub($base, 'agg', 'agg.uid', '=', "{$userTable}.id")
            ->when($onlyMultipleActiveCards, static fn (EloquentBuilder $q) => $q->where('agg.active_cards', '>=', 2))
            ->orderByDesc('agg.last_post_date')
            ->select("{$userTable}.*")
            ->addSelect(['report_active_cards' => DB::raw('agg.active_cards')])
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Все строки отчёта по тем же критериям, что и таблица (для CSV).
     *
     * @return LazyCollection<int, ActiveAuthorsReportRow>
     */
    public function lazyRowsForExport(
        ActiveAuthorsReportTypeEnum $type,
        ?Carbon $dateFrom,
        ?Carbon $dateTo,
        bool $onlyMultipleActiveCards,
    ): LazyCollection {
        $base = $this->aggregationSubquery($type, $dateFrom, $dateTo);
        $userTable = (new ApiPostUser)->getTable();

        return ApiPostUser::query()
            ->joinSub($base, 'agg', 'agg.uid', '=', "{$userTable}.id")
            ->when($onlyMultipleActiveCards, static fn (EloquentBuilder $q) => $q->where('agg.active_cards', '>=', 2))
            ->orderByDesc('agg.last_post_date')
            ->select("{$userTable}.*")
            ->addSelect(['report_active_cards' => DB::raw('agg.active_cards')])
            ->cursor()
            ->map(function (ApiPostUser $user) use ($type, $dateFrom, $dateTo): ?ActiveAuthorsReportRow {
                $count = (int) ($user->getAttribute('report_active_cards') ?? 0);

                return $this->resolveRowForUser($user, $type, $dateFrom, $dateTo, $count);
            })
            ->filter();
    }

    /**
     * По одному элементу на каждого пользователя (в том же порядке, что и коллекция после values()).
     *
     * @return Collection<int, ActiveAuthorsReportRow|null>
     */
    public function buildRowsForUsers(Collection $users, ActiveAuthorsReportTypeEnum $type, ?Carbon $dateFrom, ?Carbon $dateTo): Collection
    {
        return $users->values()->map(function (ApiPostUser $user) use ($type, $dateFrom, $dateTo): ?ActiveAuthorsReportRow {
            $count = (int) ($user->getAttribute('report_active_cards') ?? 0);

            return $this->resolveRowForUser($user, $type, $dateFrom, $dateTo, $count);
        });
    }

    private function resolveRowForUser(
        ApiPostUser $user,
        ActiveAuthorsReportTypeEnum $type,
        ?Carbon $dateFrom,
        ?Carbon $dateTo,
        int $activeCardsCount,
    ): ?ActiveAuthorsReportRow {
        return match ($type) {
            ActiveAuthorsReportTypeEnum::Builder => $this->pickBuilderRow($user, $dateFrom, $dateTo, $activeCardsCount),
            ActiveAuthorsReportTypeEnum::Specialist => $this->pickSpecialistRow($user, $dateFrom, $dateTo, $activeCardsCount),
            ActiveAuthorsReportTypeEnum::CompanyJob => $this->pickCompanyJobRow($user, $dateFrom, $dateTo, $activeCardsCount),
        };
    }

    private function pickBuilderRow(ApiPostUser $user, ?Carbon $dateFrom, ?Carbon $dateTo, int $activeCardsCount): ?ActiveAuthorsReportRow
    {
        $builder = $this->domainQuery(
            Builder::query()->with(['post', 'post.channel']),
            $dateFrom,
            $dateTo,
            function (EloquentBuilder $q) use ($user): void {
                $t = $q->getModel()->getTable();
                $q->where("{$t}.api_post_user_id", $user->id)
                    ->where("{$t}.status", ApiPostAiStatusEnum::Active);
            },
        )->first();

        if (! $builder instanceof Builder) {
            return null;
        }

        return ActiveAuthorsReportRow::fromBuilder($builder, $user, $builder->post, $activeCardsCount);
    }

    private function pickSpecialistRow(ApiPostUser $user, ?Carbon $dateFrom, ?Carbon $dateTo, int $activeCardsCount): ?ActiveAuthorsReportRow
    {
        $specialist = $this->domainQuery(
            Specialist::query()->with(['post', 'post.channel']),
            $dateFrom,
            $dateTo,
            function (EloquentBuilder $q) use ($user): void {
                $t = $q->getModel()->getTable();
                $q->where("{$t}.api_post_user_id", $user->id)
                    ->where("{$t}.status", ApiPostAiStatusEnum::Active);
            },
        )->first();

        if (! $specialist instanceof Specialist) {
            return null;
        }

        return ActiveAuthorsReportRow::fromSpecialist($specialist, $user, $specialist->post, $activeCardsCount);
    }

    private function pickCompanyJobRow(ApiPostUser $user, ?Carbon $dateFrom, ?Carbon $dateTo, int $activeCardsCount): ?ActiveAuthorsReportRow
    {
        $job = $this->domainQuery(
            CompanyJob::query()->with(['post', 'post.channel']),
            $dateFrom,
            $dateTo,
            function (EloquentBuilder $q) use ($user): void {
                $t = $q->getModel()->getTable();
                $q->where("{$t}.api_post_user_id", $user->id)
                    ->where("{$t}.status", CompanyJobStatusEnum::Active);
            },
        )->first();

        if (! $job instanceof CompanyJob) {
            return null;
        }

        return ActiveAuthorsReportRow::fromCompanyJob($job, $user, $job->post, $activeCardsCount);
    }

    /**
     * @param  EloquentBuilder<Builder|Specialist|CompanyJob>  $query
     */
    private function domainQuery(
        EloquentBuilder $query,
        ?Carbon $dateFrom,
        ?Carbon $dateTo,
        callable $userConstraints
    ): EloquentBuilder {
        $t = $query->getModel()->getTable();
        $postTable = (new ApiChannelPost)->getTable();
        $query->join($postTable, "{$postTable}.id", '=', "{$t}.api_channel_post_id");
        $userConstraints($query);
        $query->when($dateFrom, static fn (EloquentBuilder $q) => $q->where("{$postTable}.post_date", '>=', $dateFrom));
        $query->when($dateTo, static fn (EloquentBuilder $q) => $q->where("{$postTable}.post_date", '<=', $dateTo->copy()->endOfDay()));
        $query->orderByDesc("{$postTable}.post_date");
        $query->select("{$t}.*");

        return $query;
    }

    private function aggregationSubquery(ActiveAuthorsReportTypeEnum $type, ?Carbon $dateFrom, ?Carbon $dateTo): EloquentBuilder
    {
        return match ($type) {
            ActiveAuthorsReportTypeEnum::Builder => $this->aggregatedUserIdsFromBuilders($dateFrom, $dateTo),
            ActiveAuthorsReportTypeEnum::Specialist => $this->aggregatedUserIdsFromSpecialists($dateFrom, $dateTo),
            ActiveAuthorsReportTypeEnum::CompanyJob => $this->aggregatedUserIdsFromCompanyJobs($dateFrom, $dateTo),
        };
    }

    private function aggregatedUserIdsFromBuilders(?Carbon $dateFrom, ?Carbon $dateTo): EloquentBuilder
    {
        $postTable = (new ApiChannelPost)->getTable();
        $domainTable = (new Builder)->getTable();

        return Builder::query()
            ->join($postTable, "{$postTable}.id", '=', "{$domainTable}.api_channel_post_id")
            ->where("{$domainTable}.status", ApiPostAiStatusEnum::Active)
            ->whereNotNull("{$domainTable}.api_channel_post_id")
            ->where("{$domainTable}.api_channel_post_id", '>', 0)
            ->when($dateFrom, static fn (EloquentBuilder $q) => $q->where("{$postTable}.post_date", '>=', $dateFrom))
            ->when($dateTo, static fn (EloquentBuilder $q) => $q->where("{$postTable}.post_date", '<=', $dateTo->copy()->endOfDay()))
            ->groupBy("{$domainTable}.api_post_user_id")
            ->selectRaw("{$domainTable}.api_post_user_id as uid, MAX({$postTable}.post_date) as last_post_date, COUNT(*) as active_cards");
    }

    private function aggregatedUserIdsFromSpecialists(?Carbon $dateFrom, ?Carbon $dateTo): EloquentBuilder
    {
        $postTable = (new ApiChannelPost)->getTable();
        $domainTable = (new Specialist)->getTable();

        return Specialist::query()
            ->join($postTable, "{$postTable}.id", '=', "{$domainTable}.api_channel_post_id")
            ->where("{$domainTable}.status", ApiPostAiStatusEnum::Active)
            ->whereNotNull("{$domainTable}.api_channel_post_id")
            ->where("{$domainTable}.api_channel_post_id", '>', 0)
            ->when($dateFrom, static fn (EloquentBuilder $q) => $q->where("{$postTable}.post_date", '>=', $dateFrom))
            ->when($dateTo, static fn (EloquentBuilder $q) => $q->where("{$postTable}.post_date", '<=', $dateTo->copy()->endOfDay()))
            ->groupBy("{$domainTable}.api_post_user_id")
            ->selectRaw("{$domainTable}.api_post_user_id as uid, MAX({$postTable}.post_date) as last_post_date, COUNT(*) as active_cards");
    }

    private function aggregatedUserIdsFromCompanyJobs(?Carbon $dateFrom, ?Carbon $dateTo): EloquentBuilder
    {
        $postTable = (new ApiChannelPost)->getTable();
        $domainTable = (new CompanyJob)->getTable();

        return CompanyJob::query()
            ->join($postTable, "{$postTable}.id", '=', "{$domainTable}.api_channel_post_id")
            ->where("{$domainTable}.status", CompanyJobStatusEnum::Active)
            ->whereNotNull("{$domainTable}.api_channel_post_id")
            ->where("{$domainTable}.api_channel_post_id", '>', 0)
            ->when($dateFrom, static fn (EloquentBuilder $q) => $q->where("{$postTable}.post_date", '>=', $dateFrom))
            ->when($dateTo, static fn (EloquentBuilder $q) => $q->where("{$postTable}.post_date", '<=', $dateTo->copy()->endOfDay()))
            ->groupBy("{$domainTable}.api_post_user_id")
            ->selectRaw("{$domainTable}.api_post_user_id as uid, MAX({$postTable}.post_date) as last_post_date, COUNT(*) as active_cards");
    }
}
