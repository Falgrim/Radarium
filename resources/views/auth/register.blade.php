<x-global-layout>
    <main>
        <div class="container">
            <div class="row">
                <div class="col">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('index') }}"><span>Главная</span></a></li>
                        <li class="breadcrumb-item active"><span>Регистрация</span></li>
                    </ol>
                </div>
            </div>
        </div>
        <div class="container pers-account">
            <div class="row">
                <div class="col">
                    <div class="div2cols">
                        <div class="box-simple">
                            <h3 class="box-heading c-blue">{{ __('Регистрация') }}</h3>

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('register') }}">
                                @csrf

                                <div class="registration-form on-light">
                                    <input class="form-control" type="text" placeholder="ФИО" id="name"  name="name" value="{{ old('name') }}" required="required">
                                    <input class="form-control input_tel" type="text" placeholder="+7 (916) 111-22-33" id="phone" name="phone" value="{{ old('phone') }}" required="required">

                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="from_company" name="from_company" value="1" >
                                        <label class="form-check-label" for="from_company">Представляю компанию</label>
                                    </div>

                                    <input class="form-control company_field" style="display: none;" type="text" name="company_title" value="{{ old('company_title') }}" placeholder="Название компании">
                                    <input class="form-control company_field" style="display: none;" type="text" name="company_inn" value="{{ old('company_inn') }}" placeholder="ИНН компании">

                                    <select class="form-select" id="role_id" name="user_role_id" required>
                                        <option value="">Выберите...</option>
                                        @foreach ($userRoleList as $userRole)
                                            <option value="{{ $userRole['id'] }}" {{ (collect(old('user_role_id', $request['user_role_id']))->contains($userRole['id'])) ? 'selected':'' }}>{{ $userRole['value'] }}</option>
                                        @endforeach
                                    </select>

                                    <input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="Почта" required="required">

                                    <input class="form-control" type="password" name="password" placeholder="Новый пароль">
                                    <input class="form-control" type="password" name="password_confirmation" placeholder="Повторите новый пароль">

                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="user_agree" name="user_agree" value="1" required>
                                        <label class="form-check-label" for="user_agree"><a href="{{ asset('storage/documents/user-agreement.pdf') }}" target="_blank">{{ __('С пользовательским соглашением ознакомлен') }}</a></label>
                                    </div>

                                    <div class="box-bttn" style="margin-top: auto;">
                                        <button class="btn btn-normal btn-color" type="submit">{{ __('Регистрация') }}</button>
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

</x-global-layout>
