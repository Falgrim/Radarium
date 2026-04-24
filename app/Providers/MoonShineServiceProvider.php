<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Pages\ActiveAuthorsReportPage;
use App\MoonShine\Pages\BuilderSystemPromptPage;
use App\MoonShine\Pages\SpecialistSystemPromptPage;
use App\MoonShine\Resources\ApiAiResource;
use App\MoonShine\Resources\ApiChannelPostResource;
use App\MoonShine\Resources\ApiChannelResource;
use App\MoonShine\Resources\ApiPostUserResource;
use App\MoonShine\Resources\BuilderResource;
use App\MoonShine\Resources\BuilderReviewResource;
use App\MoonShine\Resources\CompanyAuthorsResource;
use App\MoonShine\Resources\CompanyJobResource;
use App\MoonShine\Resources\CompanyJobReviewResource;
use App\MoonShine\Resources\ConfigurationResource;
use App\MoonShine\Resources\DictionarySpecialityResource;
use App\MoonShine\Resources\MailingMessageLogResource;
use App\MoonShine\Resources\MailingMessageResource;
use App\MoonShine\Resources\ModerationAlertResource;
use App\MoonShine\Resources\PaymentTariffResource;
use App\MoonShine\Resources\ReviewCustomFieldResource;
use App\MoonShine\Resources\ReviewResource;
use App\MoonShine\Resources\SpecialistResource;
use App\MoonShine\Resources\UserResource;
use App\MoonShine\Resources\UserRoleResource;
use App\MoonShine\Resources\UserTariffResource;
use Closure;
use MoonShine\Contracts\Resources\ResourceContract;
use MoonShine\Menu\MenuElement;
use MoonShine\Menu\MenuGroup;
use MoonShine\Menu\MenuItem;
use MoonShine\Pages\Page;
use MoonShine\Providers\MoonShineApplicationServiceProvider;
use MoonShine\Resources\MoonShineUserResource;
use MoonShine\Resources\MoonShineUserRoleResource;

class MoonShineServiceProvider extends MoonShineApplicationServiceProvider
{
    /**
     * @return list<ResourceContract>
     */
    protected function resources(): array
    {
        return [
            new ReviewCustomFieldResource,
            new MailingMessageLogResource,
        ];
    }

    /**
     * @return list<Page>
     */
    protected function pages(): array
    {
        return [
            new BuilderSystemPromptPage,
            new SpecialistSystemPromptPage,
            new ActiveAuthorsReportPage,
        ];
    }

    /**
     * @return Closure|list<MenuElement>
     */
    protected function menu(): array
    {
        return [
            MenuGroup::make(static fn () => __('moonshine::ui.resource.system'), [
                MenuItem::make(
                    static fn () => __('moonshine::ui.resource.admins_title'),
                    new MoonShineUserResource
                ),
                MenuItem::make(
                    static fn () => __('moonshine::ui.resource.role_title'),
                    new MoonShineUserRoleResource
                ),
                MenuItem::make('Настройки', new ConfigurationResource, 'heroicons.cog'),
            ]),

            MenuGroup::make('Пользователи', [
                MenuItem::make('Пользователи', new UserResource, 'heroicons.cog'),
                MenuItem::make('Подписки', new UserTariffResource, 'heroicons.cog'),
                MenuItem::make('Роли', new UserRoleResource, 'heroicons.cog'),
            ], 'heroicons.users'),

            MenuGroup::make('Отчёты', [
                MenuItem::make('Активные авторы', new ActiveAuthorsReportPage, 'heroicons.document-text'),
            ], 'heroicons.document-text'),

            MenuGroup::make('Основное меню', [
                MenuItem::make('Сервисы ИИ', new ApiAiResource, 'heroicons.users'),
                MenuItem::make('Источники сообщений', new ApiChannelResource, 'heroicons.users'),
                MenuItem::make('Cообщения/Посты', new ApiChannelPostResource, 'heroicons.users'),
                MenuItem::make('Авторы сообщений', new ApiPostUserResource, 'heroicons.users'),
                MenuItem::make('Специализации', new DictionarySpecialityResource, 'heroicons.users'),
                MenuItem::make('Модерация', new ModerationAlertResource, 'heroicons.users'),
            ], 'heroicons.users'),

            MenuItem::make('Управление тарифами', new PaymentTariffResource, 'heroicons.users'),

            MenuGroup::make('Продвижение', [
                MenuItem::make('Работодатели', new CompanyAuthorsResource, 'heroicons.users'),
                MenuItem::make('Рассылка', new MailingMessageResource, 'heroicons.users'),
                /* MenuItem::make('Рассылка. Лог', new MailingMessageLogResource(), 'heroicons.users'), */
            ], 'heroicons.users'),

            MenuGroup::make('Проектирование', [
                MenuItem::make('Проектирование', new SpecialistResource, 'heroicons.users'),
                MenuItem::make('Системный промпт', new SpecialistSystemPromptPage, 'heroicons.document-text'),
                MenuItem::make('Отзывы', new ReviewResource, 'heroicons.users'),
            ], 'heroicons.users'),

            MenuGroup::make('Строительство', [
                MenuItem::make('Строительство', new BuilderResource, 'heroicons.users'),
                MenuItem::make('Системный промпт', new BuilderSystemPromptPage, 'heroicons.document-text'),
                MenuItem::make('Отзывы', new BuilderReviewResource, 'heroicons.users'),
            ], 'heroicons.users'),

            MenuGroup::make('Вакансии', [
                MenuItem::make('Вакансии', new CompanyJobResource, 'heroicons.users'),
                MenuItem::make('Отзывы', new CompanyJobReviewResource, 'heroicons.users'),
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
