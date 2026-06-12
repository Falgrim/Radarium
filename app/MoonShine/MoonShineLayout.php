<?php

declare(strict_types=1);

namespace App\MoonShine;

use App\Services\AiProviderHealthAlertService;
use MoonShine\Components\FlexibleRender;
use MoonShine\Components\Layout\{Content,
    Flash,
    Footer,
    Header,
    LayoutBlock,
    LayoutBuilder,
    Menu,
    Profile,
    Search,
    Sidebar};
use MoonShine\Components\When;
use MoonShine\Contracts\MoonShineLayoutContract;

final class MoonShineLayout implements MoonShineLayoutContract
{
    public static function build(): LayoutBuilder
    {
        return LayoutBuilder::make([
            Sidebar::make([
                Menu::make(),
                When::make(
                    static fn() => config('moonshine.auth.enable', true),
                    static fn() => [Profile::make(withBorder: true)]
                ),
            ]),
            LayoutBlock::make([
                Flash::make(),
                FlexibleRender::make(
                    view('moonshine.components.ai-provider-health-banner', [
                        'banners' => app(AiProviderHealthAlertService::class)->getActiveBanners(),
                        'apiAiUrl' => app(AiProviderHealthAlertService::class)->adminApiAiUrl(),
                    ])
                ),
                Header::make([
                    Search::make(),
                ]),
                Content::make(),
                Footer::make()
            ])->customAttributes(['class' => 'layout-page']),
        ]);
    }
}
