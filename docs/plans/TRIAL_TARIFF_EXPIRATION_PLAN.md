# Тест-драйв тариф: ограничение по времени — план реализации

> См. дизайн: `docs/plans/TRIAL_TARIFF_EXPIRATION_DESIGN.md`

**Цель:** тест-драйв (3 бесплатных контакта) действует не дольше 3 дней с первого входа;
после истечения — модалка в стиле главной при входе, с возможностью просто закрыть.

**Выполнение:** инлайн, по задачам, с тестами и коммитом после каждой.

---

## Задача 1: Миграция `first_login_at` + бэкофилл

**Файлы:**
- Создать: `database/migrations/2026_09_14_190000_add_first_login_at_to_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('first_login_at')->nullable()->after('last_login_at');
        });

        // Бэкофилл для уже существующих пользователей: точка отсчёта тест-драйва —
        // дата регистрации, чтобы не продлевать и не обнулять задним числом уже
        // идущий или истёкший демо-период.
        DB::table('users')->update(['first_login_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('first_login_at');
        });
    }
};
```

Проверка: `php artisan migrate` без ошибок; `RefreshDatabase` в тестах подтягивает новую колонку автоматически.

Коммит: `feat: add first_login_at column to users with backfill`

---

## Задача 2: `User` — методы тест-драйва

**Файлы:**
- Изменить: `app/Models/User.php`
- Тест: `tests/Unit/Models/UserTrialTest.php` (новый)

Изменения в `User.php`:
- добавить константу `TRIAL_DAYS = 3` рядом с `FREE_CONTACTS`;
- в `casts()` добавить `'first_login_at' => 'datetime'`;
- добавить методы `getTrialEndsAt()`, `isTrialActive()`, `isTrialExpired()`, `hasActivePaidTariff()`, `getTrialDaysLeft()`;
- в `getLeftContacts()` (ветка `else`, без активного платного тарифа) — `count_contacts_left` и `days_left` считать с учётом истечения триала.

Тесты (`UserTrialTest.php`):
- `test_trial_is_active_when_first_login_is_null`
- `test_trial_is_active_within_three_days`
- `test_trial_is_expired_after_three_days`
- `test_get_left_contacts_reports_zero_after_trial_expired_even_with_free_contacts`
- `test_get_left_contacts_days_left_reflects_trial_window`

Запуск: `php artisan test tests/Unit/Models/UserTrialTest.php`

Коммит: `feat: add trial window helpers to User model`

---

## Задача 3: `AppServiceProvider` — фиксация первого входа и флаг для модалки

**Файлы:**
- Изменить: `app/Providers/AppServiceProvider.php`
- Изменить: `tests/Feature/Auth/AuthenticationTest.php` (добавить тесты)

В листенере `Login`:
- `last_login_at` обновляется всегда (как сейчас);
- `first_login_at` проставляется один раз, если ещё `null`;
- если после этого триал истёк (`isTrialExpired()`) и нет активного платного тарифа
  (`!hasActivePaidTariff()`) — `session()->flash('show_trial_expired_modal', true)`.

Тесты:
- `test_first_login_at_is_set_on_first_login_only`
- `test_trial_expired_flash_is_set_when_trial_over_and_no_paid_tariff`
- `test_trial_expired_flash_is_not_set_when_trial_still_active`
- `test_trial_expired_flash_is_not_set_when_active_paid_tariff_exists`

Запуск: `php artisan test tests/Feature/Auth/AuthenticationTest.php`

Коммит: `feat: track first login and flag expired trial on login`

---

## Задача 4: `Tariff` — гейтинг бесплатных контактов по времени

**Файлы:**
- Изменить: `app/Services/Tariff.php`
- Тест: `tests/Unit/Services/TariffTrialAccessTest.php` (новый)

Добавить приватный метод `canUseFreeContacts(): bool` (`free_contacts > 0 AND !isTrialExpired()`)
и заменить три прямых обращения к `$this->user->free_contacts` в `checkContactAccess()`,
`checkAccessToOpen()` и `freeContactsMinus()` на вызов этого метода.

Тесты:
- `test_new_free_contact_denied_when_trial_expired_even_with_contacts_left`
- `test_new_free_contact_allowed_within_trial_window`
- `test_already_opened_contact_still_accessible_after_trial_expired`

Запуск: `php artisan test tests/Unit/Services/TariffTrialAccessTest.php`

Коммит: `feat: block new free contacts once trial has expired`

---

## Задача 5: Модалка «Тест-драйв завершён»

**Файлы:**
- Изменить: `resources/views/components/modals.blade.php`
- Тест: `tests/Feature/TrialExpiredNoticeTest.php` (новый)

Добавить модалку `#rd-trial-expired` рядом с `rd-tarif-todo` (те же классы:
`modal-registr modal-centered`, `tarif-name`, `box-btn-line`), с кнопкой закрытия
(`btn-close`, `data-bs-dismiss="modal"`) и двумя необязательными действиями —
ссылками на `{{ route('index') }}#buy_tariff` и `{{ route('profile.edit') }}`
(обе тоже с `data-bs-dismiss="modal"`, чтобы модалка закрывалась при переходе).

Сразу после блока модалки — условный автопоказ через уже существующий JS-хелпер
`rdModalShow()`:

```blade
@if (session('show_trial_expired_modal'))
    <script>
        $(document).ready(function () {
            rdModalShow('rd-trial-expired');
        });
    </script>
@endif
```

Тесты:
- `test_modal_autoshow_script_present_when_flash_flag_set`
- `test_modal_autoshow_script_absent_on_next_request_without_new_login` (флеш израсходован)
- `test_modal_autoshow_script_absent_when_flag_not_set`

Запуск: `php artisan test tests/Feature/TrialExpiredNoticeTest.php`

Коммит: `feat: show trial expired modal styled like landing page`

---

## Задача 6: Полный прогон тестов

```bash
php artisan test
```

Убедиться, что новые тесты проходят и ничего не сломалось в существующих (кроме уже
известного нерабочего `tests/Unit/TariffTest.php`, который ссылается на несуществующую
модель `App\Models\Tariff` и не связан с этой задачей — не трогаем).

Коммит (если требуются точечные правки по итогам прогона): `fix: address test fallout from trial expiration feature`
