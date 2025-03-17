<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Resources\ApiAiResource;
use App\MoonShine\Resources\ApiChannelPostResource;
use App\MoonShine\Resources\ApiChannelResource;
use App\MoonShine\Resources\ApiPostUserResource;
use App\MoonShine\Resources\BuilderResource;
use App\MoonShine\Resources\BuilderReviewResource;
use App\MoonShine\Resources\CompanyJobResource;
use App\MoonShine\Resources\CompanyJobReviewResource;
use App\MoonShine\Resources\ConfigurationResource;
use App\MoonShine\Resources\DictionarySpecialityResource;
use App\MoonShine\Resources\ReviewCustomFieldResource;
use App\MoonShine\Resources\ReviewResource;
use App\MoonShine\Resources\SpecialistResource;
use App\MoonShine\Resources\UserResource;
use App\MoonShine\Resources\UserRoleResource;
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
        return [
            new ReviewCustomFieldResource(),
        ];
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

            MenuGroup::make('Пользователи', [
                MenuItem::make('Пользователи', new UserResource(), 'heroicons.cog'),
                MenuItem::make('Роли', new UserRoleResource(), 'heroicons.cog'),
            ], 'heroicons.users'),

            MenuGroup::make('Основное меню', [
                MenuItem::make('Сервисы ИИ', new ApiAiResource(), 'heroicons.users'),
                MenuItem::make('Источники сообщений', new ApiChannelResource(), 'heroicons.users'),
                MenuItem::make('Cообщения/Посты', new ApiChannelPostResource(), 'heroicons.users'),
                MenuItem::make('Специалисты (аккаунты)', new ApiPostUserResource(), 'heroicons.users'),
                MenuItem::make('Специализации', new DictionarySpecialityResource(), 'heroicons.users'),
            ], 'heroicons.users'),

            MenuGroup::make('Проектирование', [
                MenuItem::make('Проектирование', new SpecialistResource(), 'heroicons.users'),
                MenuItem::make('Отзывы', new ReviewResource(), 'heroicons.users'),
            ], 'heroicons.users'),

            MenuGroup::make('Строительство', [
                MenuItem::make('Строительство', new BuilderResource(), 'heroicons.users'),
                MenuItem::make('Отзывы', new BuilderReviewResource(), 'heroicons.users'),
            ], 'heroicons.users'),

            MenuGroup::make('Вакансии', [
                MenuItem::make('Вакансии', new CompanyJobResource(), 'heroicons.users'),
                MenuItem::make('Отзывы', new CompanyJobReviewResource(), 'heroicons.users'),
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
