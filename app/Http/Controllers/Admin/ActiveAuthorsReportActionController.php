<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enum\ActiveAuthorsReportTypeEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\ApiChannelPost;
use App\Models\Builder;
use App\Models\CompanyJob;
use App\Models\Specialist;
use App\MoonShine\Pages\ActiveAuthorsReportPage;
use App\Services\Admin\ActiveAuthorsReportService;
use App\Support\Admin\ActiveAuthorsReportRow;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use MoonShine\Models\MoonshineUser;
use MoonShine\MoonShineAuth;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ActiveAuthorsReportActionController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::enum(ActiveAuthorsReportTypeEnum::class)],
            'domain_id' => ['required', 'integer', 'min:1'],
        ]);

        $type = ActiveAuthorsReportTypeEnum::from($data['type']);
        $domain = $this->resolveDomain($type, (int) $data['domain_id']);

        if ($domain === null) {
            abort(404);
        }

        $user = MoonShineAuth::guard()->user();
        if (! $user instanceof MoonshineUser) {
            abort(403);
        }
        Gate::forUser($user)->authorize('update', $domain);

        $post = $domain->post;
        if ($post) {
            Gate::forUser($user)->authorize('update', $post);
        }

        return view('admin.active-authors-report-edit', [
            'type' => $type,
            'domain' => $domain,
            'post' => $post,
            'reportUrl' => moonshineRouter()->to_page(ActiveAuthorsReportPage::make()),
            'domainStatusList' => array_filter(
                $type === ActiveAuthorsReportTypeEnum::CompanyJob
                    ? CompanyJobStatusEnum::getList()
                    : ApiPostAiStatusEnum::getList(),
                static fn ($label, $key): bool => $key !== '' && $key !== null,
                ARRAY_FILTER_USE_BOTH
            ),
            'postAiStatusList' => array_filter(
                ApiChannelPostStatusEnum::getList(),
                static fn ($label, $key): bool => $key !== '' && $key !== null,
                ARRAY_FILTER_USE_BOTH
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $pre = $request->validate([
            'type' => ['required', 'string', Rule::enum(ActiveAuthorsReportTypeEnum::class)],
            'domain_id' => ['required', 'integer', 'min:1'],
        ]);

        $type = ActiveAuthorsReportTypeEnum::from($pre['type']);
        $domainEnum = $type === ActiveAuthorsReportTypeEnum::CompanyJob
            ? CompanyJobStatusEnum::class
            : ApiPostAiStatusEnum::class;

        $rules = [
            'type' => ['required', 'string', Rule::enum(ActiveAuthorsReportTypeEnum::class)],
            'domain_id' => ['required', 'integer', 'min:1'],
            'domain_status' => ['required', 'integer', Rule::enum($domainEnum)],
        ];

        $domain = $this->resolveDomain($type, (int) $pre['domain_id']);
        if ($domain === null) {
            abort(404);
        }

        if ($domain->post instanceof ApiChannelPost) {
            $rules['post_ai_status'] = ['required', 'integer', Rule::enum(ApiChannelPostStatusEnum::class)];
        }

        $data = $request->validate($rules);

        $user = MoonShineAuth::guard()->user();
        if (! $user instanceof MoonshineUser) {
            abort(403);
        }
        Gate::forUser($user)->authorize('update', $domain);

        if ($type === ActiveAuthorsReportTypeEnum::CompanyJob) {
            $domain->status = CompanyJobStatusEnum::from((int) $data['domain_status']);
        } else {
            $domain->status = ApiPostAiStatusEnum::from((int) $data['domain_status']);
        }
        $domain->save();

        if (isset($data['post_ai_status']) && $domain->post instanceof ApiChannelPost) {
            $post = $domain->post;
            Gate::forUser($user)->authorize('update', $post);
            $post->ai_parse_status = ApiChannelPostStatusEnum::from((int) $data['post_ai_status']);
            $post->save();
        }

        return redirect()
            ->route('admin.active-authors-report.edit', [
                'type' => $type->value,
                'domain_id' => $domain->id,
            ])
            ->with('success', 'Сохранено.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = MoonShineAuth::guard()->user();
        if (! $user instanceof MoonshineUser) {
            abort(403);
        }

        $type = ActiveAuthorsReportTypeEnum::tryFromRequest($request->query('type'));
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse((string) $request->query('date_from'))->startOfDay()
            : null;
        $dateTo = $request->filled('date_to')
            ? Carbon::parse((string) $request->query('date_to'))->startOfDay()
            : null;
        $multiOnly = $request->boolean('multi_only');

        Gate::forUser($user)->authorize('viewAny', Builder::class);

        $service = app(ActiveAuthorsReportService::class);
        $rows = $service->lazyRowsForExport($type, $dateFrom, $dateTo, $multiOnly);

        $filename = sprintf(
            'active-authors-%s-%s.csv',
            $type->value,
            now()->format('Y-m-d-His')
        );

        return response()->streamDownload(function () use ($rows, $type): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'api_post_user_id',
                'username',
                'telegram_user_id',
                'active_cards_in_scope',
                'report_type',
                'representative_domain_id',
                'post_id',
                'last_message_preview',
                'last_post_at',
                'domain_created_at',
                'domain_status',
                'post_ai_status',
                'post_not_complete',
            ], ';');
            /** @var ActiveAuthorsReportRow $row */
            foreach ($rows as $row) {
                fputcsv($out, [
                    (string) $row->user->id,
                    (string) ($row->user->username ?? ''),
                    (string) ($row->user->user_id ?? ''),
                    (string) $row->activeCardsCount,
                    $type->label(),
                    (string) $row->domainId,
                    (string) ($row->postId ?? ''),
                    $row->lastMessage,
                    $row->lastMessagePostDate?->format('Y-m-d H:i:s') ?? '',
                    $row->domainCreatedAt?->format('Y-m-d H:i:s') ?? '',
                    $row->domainStatusLabel,
                    $row->postAiStatusLabel,
                    $row->postAiNotComplete ? '1' : '0',
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::enum(ActiveAuthorsReportTypeEnum::class)],
            'api_post_user_id' => ['required', 'integer', 'min:1'],
            'delete_scope' => ['required', 'string', Rule::in(['active_only', 'all'])],
        ]);

        $type = ActiveAuthorsReportTypeEnum::from($data['type']);
        $apiPostUserId = (int) $data['api_post_user_id'];
        $activeOnly = $data['delete_scope'] === 'active_only';

        $user = MoonShineAuth::guard()->user();
        if (! $user instanceof MoonshineUser) {
            abort(403);
        }

        match ($type) {
            ActiveAuthorsReportTypeEnum::Builder => $this->softDeleteBuildersForUser($user, $apiPostUserId, $activeOnly),
            ActiveAuthorsReportTypeEnum::Specialist => $this->softDeleteSpecialistsForUser($user, $apiPostUserId, $activeOnly),
            ActiveAuthorsReportTypeEnum::CompanyJob => $this->softDeleteCompanyJobsForUser($user, $apiPostUserId, $activeOnly),
        };

        parse_str((string) $request->input('return_query', ''), $queryParams);

        return redirect()
            ->to(moonshineRouter()->to_page(ActiveAuthorsReportPage::make(), null, $queryParams))
            ->with('success', 'Записи удалены (soft delete).');
    }

    private function softDeleteBuildersForUser(MoonshineUser $moonshineUser, int $apiPostUserId, bool $activeOnly): void
    {
        $query = Builder::query()->where('api_post_user_id', $apiPostUserId);
        if ($activeOnly) {
            $query->where('status', ApiPostAiStatusEnum::Active);
        }
        /** @var \Illuminate\Database\Eloquent\Collection<int, Builder> $models */
        $models = $query->get();
        foreach ($models as $model) {
            Gate::forUser($moonshineUser)->authorize('delete', $model);
            $model->delete();
        }
    }

    private function softDeleteSpecialistsForUser(MoonshineUser $moonshineUser, int $apiPostUserId, bool $activeOnly): void
    {
        $query = Specialist::query()->where('api_post_user_id', $apiPostUserId);
        if ($activeOnly) {
            $query->where('status', ApiPostAiStatusEnum::Active);
        }
        /** @var \Illuminate\Database\Eloquent\Collection<int, Specialist> $models */
        $models = $query->get();
        foreach ($models as $model) {
            Gate::forUser($moonshineUser)->authorize('delete', $model);
            $model->delete();
        }
    }

    private function softDeleteCompanyJobsForUser(MoonshineUser $moonshineUser, int $apiPostUserId, bool $activeOnly): void
    {
        $query = CompanyJob::query()->where('api_post_user_id', $apiPostUserId);
        if ($activeOnly) {
            $query->where('status', CompanyJobStatusEnum::Active);
        }
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyJob> $models */
        $models = $query->get();
        foreach ($models as $job) {
            Gate::forUser($moonshineUser)->authorize('delete', $job);
            $job->delete();
        }
    }

    private function resolveDomain(ActiveAuthorsReportTypeEnum $type, int $id): Builder|Specialist|CompanyJob|null
    {
        return match ($type) {
            ActiveAuthorsReportTypeEnum::Builder => Builder::query()->find($id),
            ActiveAuthorsReportTypeEnum::Specialist => Specialist::query()->find($id),
            ActiveAuthorsReportTypeEnum::CompanyJob => CompanyJob::query()->find($id),
        };
    }
}
