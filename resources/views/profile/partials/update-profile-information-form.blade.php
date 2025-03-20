<section>
    <h2 class="text-lg font-medium text-gray-900">
        {{ __('Личный кабинет') }}
    </h2>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div class="mb-3 col-12 col-lg-4">
            <x-input-label for="name" :value="__('Имя')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div class="mb-3 col-12 col-lg-4">
            <x-input-label for="company_inn" :value="__('ИНН компании')" />
            <x-text-input id="company_inn" name="company_inn" type="text" class="mt-1 block w-full" :value="old('company_inn', $user->company_inn)" autofocus />
            <x-input-error class="mt-2" :messages="$errors->get('company_inn')" />
        </div>

        <div class="mb-3 col-12 col-lg-4">
            <x-input-label for="company_title" :value="__('Название компании')" />
            <x-text-input id="company_title" name="company_title" type="text" class="mt-1 block w-full" :value="old('company_title', $user->company_title)" autofocus />
            <x-input-error class="mt-2" :messages="$errors->get('company_title')" />
        </div>

        <div class="mb-3 col-12 col-lg-4">
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

        <div class="mb-3 col-12 col-lg-4">
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
