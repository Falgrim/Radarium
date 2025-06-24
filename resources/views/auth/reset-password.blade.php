<x-global-layout>
    <main>
        <div class="container">
            <div class="row">
                <div class="col">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('index') }}"><span>Главная</span></a></li>
                        <li class="breadcrumb-item active"><span>Сброс пароля</span></li>
                    </ol>
                </div>
            </div>
        </div>
        <div class="container pers-account">
            <div class="row">
                <div class="col">
                    <div class="div2cols">
                        <div class="box-simple">
                            <h3 class="box-heading c-blue">{{ __('Сброс пароля') }}</h3>

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('password.store') }}">
                                @csrf

                                <!-- Password Reset Token -->
                                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                                <div class="registration-form on-light">
                                    <input class="form-control" type="email" id="email" name="email" value="{{ old('email', $request->email) }}" placeholder="Почта" required="required">
                                    <input class="form-control" type="password" name="password" placeholder="Новый пароль">
                                    <input class="form-control" type="password" name="password_confirmation" placeholder="Подтвердите новый пароль">

                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" value="1" >
                                        <label class="form-check-label" for="remember_me">{{ __('Запомнить меня') }}</label>
                                    </div>

                                    <div class="box-bttn" style="margin-top: auto;">
                                        <button class="btn btn-normal btn-color" type="submit">{{ __('Сбросить пароль') }}</button>
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
