<x-global-layout>
<div class="my-5">
    <div class="p-5 bg-body-tertiary">
        <div class="container py-5">
            <section>
                <h2 class="text-lg font-medium text-gray-900">
                    Регистрация
                </h2>

                <p>{{ __('Спасибо за регистрацию! Для продолжения требуется активация, на вашу почту была отправлена ссылка для активации.') }}</p>

                @if (session('status') == 'verification-link-sent')
                    <p>
                        {{ __('На вашу почту было направлено новое сообщение с ссылкой активации.') }}
                    </p>
                @endif

                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <div class="mb-3 col-12 col-lg-4 col-md-6">
                        <button type="submit" class="btn btn-primary">
                            {{ __('Выход') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>
</x-global-layout>
