<x-global-layout>
<div class="my-5">
    <div class="p-5 bg-body-tertiary">
        <div class="container py-5">
            <section>
                <h2 class="text-lg font-medium text-gray-900">
                    Регистрация
                </h2>

                @if (session('verification_mail_failed') || session('status') == 'verification-link-failed')
                    <p class="text-danger">{{ __('Спасибо за регистрацию! Для продолжения требуется активация почты, но письмо со ссылкой отправить не удалось. Нажмите «Отправить письмо повторно» через несколько минут. Если письмо по-прежнему не приходит, обратитесь в поддержку.') }}</p>
                @else
                    <p>{{ __('Спасибо за регистрацию! Для продолжения требуется активация, на вашу почту была отправлена ссылка для активации.') }}</p>
                @endif

                @if (session('status') == 'verification-link-sent')
                    <p>
                        {{ __('На вашу почту было направлено новое сообщение с ссылкой активации.') }}
                    </p>
                @endif

                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">
                                {{ __('Отправить письмо повторно') }}
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <div class="mb-3">
                            <button type="submit" class="btn btn-outline-secondary">
                                {{ __('Выход') }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>
</x-global-layout>
