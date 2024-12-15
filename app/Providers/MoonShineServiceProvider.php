<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Resources\ApiAiResource;
use App\MoonShine\Resources\ApiChannelPostResource;
use App\MoonShine\Resources\ApiChannelResource;
use App\MoonShine\Resources\ConfigurationResource;
use MoonShine\Providers\MoonShineApplicationServiceProvider;
use MoonShine\MoonShine;
use MoonShine\Menu\MenuGroup;
use MoonShine\Menu\MenuItem;
use MoonShine\Resources\MoonShineUserResource;
use MoonShine\Resources\MoonShineUserRoleResource;
use MoonShine\Contracts\Resources\ResourceContract;
use MoonShine\Menu\MenuElement;
use MoonShine\Pages\Page;
use Closure;

class MoonShineServiceProvider extends MoonShineApplicationServiceProvider
{
    /**
     * @return list<ResourceContract>
     */
    protected function resources(): array
    {
        return [];
    }

    /**
     * @return list<Page>
     */
    protected function pages(): array
    {
        return [];
    }

    /**
     * @return Closure|list<MenuElement>
     */
    protected function menu(): array
    {
        return [
            MenuGroup::make(static fn() => __('moonshine::ui.resource.system'), [
                MenuItem::make(
                    static fn() => __('moonshine::ui.resource.admins_title'),
                    new MoonShineUserResource()
                ),
                MenuItem::make(
                    static fn() => __('moonshine::ui.resource.role_title'),
                    new MoonShineUserRoleResource()
                ),
                MenuItem::make('Настройки', new ConfigurationResource(), 'heroicons.cog'),
            ]),

            MenuGroup::make('API', [
                MenuItem::make('Список сервисов ИИ', new ApiAiResource(), 'heroicons.users'),
                MenuItem::make('Список источников', new ApiChannelResource(), 'heroicons.users'),
                MenuItem::make('Лог обработки', new ApiChannelPostResource(), 'heroicons.users'),
            ], 'heroicons.users'),
        ];
    }

    /**
     * @return Closure|array{css: string, colors: array, darkColors: array}
     */
    protected function theme(): array
    {
        return [];
    }
}
