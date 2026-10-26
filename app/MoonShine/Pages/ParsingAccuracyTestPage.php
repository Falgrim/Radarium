<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\ParsingTestMessage;
use App\Models\ParsingTestRun;
use App\MoonShine\Resources\ApiChannelPostResource;
use App\MoonShine\Resources\ApiPostUserResource;
use App\Services\ParsingAccuracyTestService;
use MoonShine\Components\FlexibleRender;
use MoonShine\Pages\Page;
use Throwable;

final class ParsingAccuracyTestPage extends Page
{
    public function __construct()
    {
        parent::__construct('Тесты парсинга', 'parsing-accuracy-tests');

        $this->customView('moonshine.parsing-accuracy-test-page');
    }

    /**
     * @return array<int|string, string>
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
        $service = app(ParsingAccuracyTestService::class);
        $service->releaseStaleRuns();

        $run = ParsingTestRun::query()->latest('id')->first();
        $isActive = $run?->status->isActive() ?? false;
        $isStopping = $isActive && $run?->cancel_requested_at !== null;
        $messages = $run === null
            ? null
            : ParsingTestMessage::query()
                ->where('parsing_test_run_id', $run->id)
                ->orderByDesc('post_date')
                ->orderByDesc('id')
                ->paginate(30, ['*'], 'p');

        $postResource = app(ApiChannelPostResource::class);
        $postResource->boot();
        $authorResource = app(ApiPostUserResource::class);
        $authorResource->boot();

        return [
            FlexibleRender::make(view('moonshine.parsing-accuracy-test', [
                'run' => $run,
                'messages' => $messages,
                'isActive' => $isActive,
                'isStopping' => $isStopping,
                'error' => session('parsing_test_error'),
                'startUrl' => route('admin.parsing-accuracy-test.start'),
                'cancelUrl' => route('admin.parsing-accuracy-test.cancel'),
                'postFormUrl' => static fn (int $postId): string => $postResource->formPageUrl($postId),
                'authorDetailUrl' => static fn (int $authorId): string => $authorResource->detailPageUrl($authorId),
            ])),
        ];
    }
}
