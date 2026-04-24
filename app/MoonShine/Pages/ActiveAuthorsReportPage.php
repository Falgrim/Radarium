<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enum\ActiveAuthorsReportTypeEnum;
use App\Models\ApiPostUser;
use App\MoonShine\Resources\ApiChannelPostResource;
use App\MoonShine\Resources\ApiPostUserResource;
use App\MoonShine\Resources\BuilderResource;
use App\MoonShine\Resources\CompanyJobResource;
use App\MoonShine\Resources\SpecialistResource;
use App\Services\Admin\ActiveAuthorsReportService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use MoonShine\Components\FlexibleRender;
use MoonShine\Pages\Page;
use Throwable;

final class ActiveAuthorsReportPage extends Page
{
    public function __construct()
    {
        parent::__construct('Отчёт: активные авторы', 'active-authors-report');

        $this->customView('moonshine.active-authors-report-page');
    }

    /**
     * @return array<int, string>
     */
    public function breadcrumbs(): array
    {
        return [
            '#' => $this->title(),
        ];
    }

    /**
     * @return list<\MoonShine\Components\MoonShineComponent>
     *
     * @throws Throwable
     */
    public function components(): array
    {
        $request = request();
        $type = ActiveAuthorsReportTypeEnum::tryFromRequest($request->query('type'));
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse((string) $request->query('date_from'))->startOfDay()
            : null;
        $dateTo = $request->filled('date_to')
            ? Carbon::parse((string) $request->query('date_to'))->startOfDay()
            : null;
        $multiOnly = $request->boolean('multi_only');

        $service = app(ActiveAuthorsReportService::class);
        /** @var LengthAwarePaginator<int, ApiPostUser> $paginator */
        $paginator = $service->paginateUsers($type, $dateFrom, $dateTo, 50, $multiOnly);
        $items = collect($paginator->items())->values();
        $rows = $service->buildRowsForUsers($items, $type, $dateFrom, $dateTo);
        $entries = $items->map(fn (ApiPostUser $user, int $i) => [
            'user' => $user,
            'row' => $rows->get($i),
        ]);

        $apiPostUserResource = app(ApiPostUserResource::class);
        $apiPostUserResource->boot();

        $builderResource = app(BuilderResource::class);
        $builderResource->boot();
        $specialistResource = app(SpecialistResource::class);
        $specialistResource->boot();
        $companyJobResource = app(CompanyJobResource::class);
        $companyJobResource->boot();

        $postResource = app(ApiChannelPostResource::class);
        $postResource->boot();

        /** @var View $view */
        $view = view('moonshine.active-authors-report', [
            'type' => $type,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'multiOnly' => $multiOnly,
            'paginator' => $paginator,
            'entries' => $entries,
            'reportFormAction' => moonshineRouter()->to_page(self::make()),
            'exportUrl' => route('admin.active-authors-report.export', $request->query()),
            'authorDetailUrl' => static fn (int $id): string => $apiPostUserResource->detailPageUrl($id),
            'deleteUrl' => route('admin.active-authors-report.destroy'),
            'domainFormUrl' => static function (ActiveAuthorsReportTypeEnum $t, int $domainId) use ($builderResource, $specialistResource, $companyJobResource): string {
                return match ($t) {
                    ActiveAuthorsReportTypeEnum::Builder => $builderResource->formPageUrl($domainId),
                    ActiveAuthorsReportTypeEnum::Specialist => $specialistResource->formPageUrl($domainId),
                    ActiveAuthorsReportTypeEnum::CompanyJob => $companyJobResource->formPageUrl($domainId),
                };
            },
            'postFormUrl' => static fn (int $postId): string => $postResource->formPageUrl($postId),
        ]);

        return [
            FlexibleRender::make($view),
        ];
    }
}
