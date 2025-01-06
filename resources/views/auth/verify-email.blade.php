<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Спасибо за регистрацию! Для продолжения требуется активация, на вашу почту была отправлена ссылка для активации.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('На вашу почту было направлено новое сообщение с ссылкой активации.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Отправить письмо еще раз') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Выход') }}
            </button>
        </form>
    </div>
</x-guest-layout>
