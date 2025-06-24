<x-global-layout>
    <main>
        <div class="container">
            <div class="row">
                <div class="col">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('index') }}"><span>Главная</span></a></li>
                        <li class="breadcrumb-item active"><span>Восстановление пароля</span></li>
                    </ol>
                </div>
            </div>
        </div>
        <div class="container pers-account">
            <div class="row">
                <div class="col">
                    <div class="div2cols">
                        <div class="box-simple">
                            <h3 class="box-heading c-blue">{{ __('Восстановление пароля') }}</h3>

                            <p>{{ __('Забыли ваш пароль? Укажите ваш почтовы адрес, на который регистрировали аккаунт и мы вышлем вам ссылку для сброса.') }}</p>

                            <!-- Session Status -->
                            <x-auth-session-status class="mb-4" :status="session('status')" />

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('password.email') }}">
                                @csrf

                                <div class="registration-form on-light">
                                    <input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="Почта" required="required">

                                    <div class="box-bttn" style="margin-top: auto;">
                                        <button class="btn btn-normal btn-color" type="submit">{{ __('Отправить') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-footer-finish />
    </main>
</x-global-layout>
