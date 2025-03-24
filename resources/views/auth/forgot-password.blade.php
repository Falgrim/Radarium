<x-global-layout>
    <div class="bg-body-tertiary">
        <div class="container py-3">
            <h2 class="text-body-emphasis">Восстановление пароля</h2>
            <div class="mb-4 text-sm text-gray-600">
                {{ __('Забыли ваш пароль? Укажите ваш почтовы адрес, на который регистрировали аккаунт и мы вышлем вам ссылку для сброса.') }}
            </div>

            <!-- Session Status -->
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <!-- Email Address -->
                <div class="mb-3 col-12 col-lg-4 col-md-6">
                    <x-input-label for="email" :value="__('Почта')" />
                    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end mt-4">
                    <x-primary-button>
                        {{ __('Отправить') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-global-layout>
