<section>
    <div class="mb-3 col-12 col-lg-4 col-md-6">
        <a href="{{ route('profile.subscribe') }}" class="btn btn-medium btn-outline-primary">Ваша подписка</a>
    </div>

    <h2 class="text-lg font-medium text-gray-900">
        {{ __('Личный кабинет') }}
    </h2>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div class="mb-3 col-12 col-lg-4 col-md-6">
            <x-input-label for="name" :value="__('Имя')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div class="mb-3 col-12 col-lg-4 col-md-6">
            <label>
                <input type="checkbox" name="from_company" value="1" @if($user->company_title OR $user->company_inn) checked @endif /> {{ __('Представляю компанию') }}
            </label>
            <x-input-error :messages="$errors->get('from_company')" class="mt-2" />
        </div>

        <div class="mb-3 col-12 col-lg-4 col-md-6 company_field" @if(!$user->company_title AND !$user->company_inn) style="display: none;" @endif>
            <x-input-label for="company_title" :value="__('Название компании')" />
            <x-text-input id="company_title" class="block mt-1 w-full" type="text" name="company_title" :value="old('company_title', $user->company_title)" autofocus autocomplete="company_title" />
            <x-input-error :messages="$errors->get('company_title')" class="mt-2" />
        </div>

        <div class="mb-3 col-12 col-lg-4 col-md-6 company_field" style="display: none;">
            <x-input-label for="company_inn" :value="__('ИНН')" />
            <x-text-input id="company_inn" class="block mt-1 w-full" type="text" name="company_inn" :value="old('company_inn', $user->company_inn)" autofocus autocomplete="company_inn" />
            <x-input-error :messages="$errors->get('company_inn')" class="mt-2" />
        </div>

        <div class="mb-3 col-12 col-lg-4 col-md-6">
            <x-input-label for="email" :value="__('Почта')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Ваша почта не подтверждена.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Нажмите для повторной отправки письма для подтверждения.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('Новая ссылка для подтверждения была выслана на вашу почту.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="mb-3 col-12 col-lg-4 col-md-6">
            <x-primary-button>{{ __('Сохранить') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Сохранено.') }}</p>
            @endif
        </div>
    </form>
</section>

@pushOnce('scripts')
    <script type="module">
        $(document).ready(function() {
            $('input[name=from_company]').on('change', function () {
                if ($(this).is(':checked')) {
                    $('.company_field').show();
                } else {
                    $('.company_field').hide();
                }
            });
        });
    </script>
@endPushOnce
