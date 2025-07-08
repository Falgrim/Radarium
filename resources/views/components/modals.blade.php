<div class="modal fade modal-registr" role="dialog" tabindex="-1" id="rd-registr">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Вход в личный кабинет</h4>
                <button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img src="{{ asset('v2/img/icon-close-white.svg') }}"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('login') }}" id="rd-registr-form">
                    @csrf
                    <div class="registration-form">
                        <div id="rd-registr-form-message" class="mt-3"></div>

                        <input class="form-control" type="email" name="email" placeholder="Почта" required="required">
                        <input class="form-control" type="password" name="password" placeholder="Пароль" required="required">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="formCheck-1" name="remember">
                            <label class="form-check-label" for="formCheck-1">Запомнить меня</label>
                        </div>
                        <div class="box-btn-line">
                            <button class="btn btn-color btn-accent" id="rd-registr-form-btn" type="submit">Вход</button>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}">Забыли пароль?</a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <div class="ftr-img"><img src="{{ asset('v2/img/icon-man-grey.svg') }}"></div>
                <div>
                    <h4 class="footer-title">У вас нет аккаунта?</h4>
                    <p><button class="btn btn-trans btn-link" type="submit" data-bs-toggle="modal" data-bs-target="#rd-account">Пройдите быструю регистрацию</button></p>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade modal-registr" role="dialog" tabindex="-1" id="rd-account">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Регистрация аккаунта</h4>
                <button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img src="{{ asset('v2/img/icon-close-white.svg') }}"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('register') }}" id="rd-account-form">
                    @csrf
                    <div class="registration-form">
                        <div id="rd-account-form-message" class="mt-3"></div>

                        <input class="form-control" type="text" name="name" placeholder="ФИО" required="required">
                        <input class="form-control input_tel" type="text" name="phone" placeholder="+7 (916) 111-22-33" required="required">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="from_company" value="1" id="formCheck-2">
                            <label class="form-check-label" for="formCheck-2">Представляю компанию</label>
                        </div>

                        <div class="vanishing box-flexcol">
                            <input class="form-control" type="text" name="company_title" placeholder="Название компании">
                            <input class="form-control" type="text" name="company_inn" placeholder="ИНН">
                        </div>

                        <input class="form-control" type="email" name="email" placeholder="Почта" required="required">
                        <!--<select class="form-select" name="user_role_id">
                            @foreach ($userRoleList as $userRole)
                                <option value="{{ $userRole['id'] }}">{{ $userRole['value'] }}</option>
                            @endforeach
                        </select> -->
                        <hr>
                        <h4>Пароль</h4>
                        <input class="form-control" type="password" name="password" placeholder="Пароль" required="required">
                        <input class="form-control" type="password" name="password_confirmation" placeholder="Подтвердите пароль" required="required">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="user_agree" value="1" id="formCheck-user_agree" required>
                            <label class="form-check-label" for="formCheck-user_agree"><a href="{{ asset('storage/documents/user-agreement.pdf') }}" target="_blank">{{ __('С пользовательским соглашением ознакомлен') }}</a></label>
                        </div>
                        <div class="box-btn-line">
                            <button class="btn btn-normal btn-color" id="rd-account-form-btn" type="submit">Зарегистрироваться</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade modal-registr" role="dialog" tabindex="-1" id="rd-drive">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-accent">
                <h4 class="modal-title">Получи тест-драйв</h4>
                <button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img src="{{ asset('v2/img/icon-close-white.svg') }}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-4">Тест-драйв доступен для&nbsp;всех новых пользователей при&nbsp;входе в&nbsp;систему. Вы можете попробовать функции поиска, а&nbsp;также иметь доступ к&nbsp;открытию контактов и&nbsp;карточек исполнителей для просмотра функций системы. Пожалуйста, зарегистрируйтесь</p>
                <form method="POST" action="{{ route('register') }}" id="rd-drive-form">
                    @csrf
                    <div class="registration-form">
                        <div id="rd-drive-form-message" class="mt-3"></div>

                        <input class="form-control" type="text" name="name" placeholder="ФИО" required="required">
                        <input class="form-control input_tel" type="text" name="phone" placeholder="+7 (916) 111-22-33" required="required">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="from_company" value="1" id="formCheck-3">
                            <label class="form-check-label" for="formCheck-3">Представляю компанию</label>
                        </div>

                        <div class="vanishing box-flexcol">
                            <input class="form-control" type="text" name="company_title" placeholder="Название компании">
                            <input class="form-control" type="text" name="company_inn" placeholder="ИНН">
                        </div>

                        <input class="form-control" type="email" name="email" placeholder="Почта" required="required">
                        <!--<select class="form-select" name="user_role_id">
                            @foreach ($userRoleList as $userRole)
                                <option value="{{ $userRole['id'] }}">{{ $userRole['value'] }}</option>
                            @endforeach
                        </select>-->
                        <hr>
                        <h4>Пароль</h4>
                        <input class="form-control" type="password" name="password" placeholder="Пароль" required="required">
                        <input class="form-control" type="password" name="password_confirmation" placeholder="Подтвердите пароль" required="required">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="user_agree" value="1" id="formCheck-user_agree2" required>
                            <label class="form-check-label" for="formCheck-user_agree2"><a href="{{ asset('storage/documents/user-agreement.pdf') }}" target="_blank">{{ __('С пользовательским соглашением ознакомлен') }}</a></label>
                        </div>
                        <div class="box-btn-line">
                            <button class="btn btn-normal btn-color" id="rd-drive-form-btn" type="submit">Зарегистрироваться</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade modal-registr" role="dialog" tabindex="-1" id="rd-industry">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Добавить новую отрасль</h4><button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img src="{{ asset('v2/img/icon-close-white.svg') }}"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="registration-form"><input class="form-control" type="text" placeholder="ФИО" required="required"><input class="form-control" type="text" placeholder="Телефон" required="required"><input class="form-control" type="text" placeholder="Новая отрасль" required="required"><textarea class="form-control" placeholder="Комментарий"></textarea>
                        <div class="box-btn-line"><button class="btn btn-normal btn-color" type="submit">Добавить</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade modal-registr" role="dialog" tabindex="-1" id="rd-question">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Остались вопросы?</h4><button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img src="{{ asset('v2/img/icon-close-white.svg') }}"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="registration-form">
                        <input class="form-control" type="text" placeholder="ФИО" required="required">
                        <input class="form-control" type="text" placeholder="Телефон" required="required">
                        <textarea class="form-control" placeholder="Ваш вопрос"></textarea>
                        <div class="box-btn-line"><button class="btn btn-normal btn-color" type="submit">Отправить</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade modal-registr" role="dialog" tabindex="-1" id="rd-public">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Оставить публичный отзыв</h4>
                <button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img src="{{ asset('v2/img/icon-close-white.svg') }}"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="registration-form">
                        <input class="form-control" type="text" placeholder="ФИО" required="required" hidden="">
                        <input class="form-control" type="text" placeholder="Телефон" required="required" hidden="">
                        <textarea class="form-control" placeholder="Ваш отзыв"></textarea>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="formCheck-4">
                            <label class="form-check-label" for="formCheck-4">Оставить отзыв анонимно</label>
                        </div>
                        <div class="box-btn-line"><button class="btn btn-normal btn-color" type="submit">Отправить</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade modal-registr" role="dialog" tabindex="-1" id="rd-tarif">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Оформить тариф</h4><button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img src="{{ asset('v2/img/icon-close-white.svg') }}"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="registration-form">
                        <h4 class="tarif-name">Тариф:&nbsp;<span>1 Год</span></h4>
                        <div class="tarif-description"><span>Количество дней</span><span>365</span></div>
                        <div class="tarif-description"><span>Количество контактов</span><span>365</span></div>
                        <div class="tarif-note">
                            <p>Для оформления тарифа сначала нужно авторизоваться или&nbsp;зарегистрироваться</p><button class="btn btn-trans btn-link" type="button" data-bs-toggle="modal" data-bs-target="#rd-account">Войти / Зарегистрироваться</button>
                        </div>
                        <div class="box-btn-line"><button class="btn btn-normal btn-color" type="submit">Оплатить</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="moderationAlert" tabindex="-1" aria-labelledby="moderationAlertLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="moderationAlertLabel">Есть ошибка!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                    <form method="POST" id="moderationAlertForm" name="moderationAlertForm">
                        <input type="hidden" name="type" id="moderationAlert_type" value="" />
                        <input type="hidden" name="row_id" id="moderationAlert_row_id" value="" />

                        <div class="mb-3">
                            <label for="message-text" class="col-form-label">Комментарий:</label>
                            <textarea class="form-control" name="description" id="message-text"></textarea>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary moderation-alert-save">Отправить</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="moderationAlertResult" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Результат запроса</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="loginAlert" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Доступ ограничен.</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p>Для просмотра контактных данных Вам необходимо <a href="/#buy_tariff">купить подписку</a></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Ошибка!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-normal btn-color" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal-registr modal-centered" role="dialog" tabindex="-1" id="rd-testdrive-auth">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header head-empty"><button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img class="img-fluid" width="24" height="24" src="{{ asset('v2/img/icon-close-dark.svg') }}"></button></div>
            <div class="modal-body">
                <form>
                    <div class="registration-form">
                        <h4 class="tarif-name">Тест-Драйв</h4>
                        <div>
                            <p>
                                Тариф тест драйв доступен сразу после регистрации пользователя в системе.
                                После окончания тарифа ТЕСТ ДРАЙВ подключайте любой другой удобный Вам тариф на главной странице системы Радариум
                            </p>
                        </div>
                        <div class="box-btn-line"><button class="btn btn-normal btn-color" type="button" aria-label="Close" data-bs-dismiss="modal">Вернуться на главную</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade modal-registr modal-centered" role="dialog" tabindex="-1" id="rd-testdrive-guest">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header head-empty"><button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img class="img-fluid" width="24" height="24" src="{{ asset('v2/img/icon-close-dark.svg') }}"></button></div>
            <div class="modal-body">
                <form>
                    <div class="registration-form">
                        <h4 class="tarif-name">Тест-Драйв</h4>
                        <div>
                            <p>
                                Для подключения тарифа необходимо войти или зарегистрироваться в системе.
                            </p>
                        </div>
                        <div class="box-btn-line"><button class="btn btn-normal btn-color" type="button" id="rd-testdrive-guest-login-btn">Войти или зарегистрироваться</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade modal-registr modal-centered" role="dialog" tabindex="-1" id="rd-tariff-guest">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header head-empty"><button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img class="img-fluid" width="24" height="24" src="{{ asset('v2/img/icon-close-dark.svg') }}"></button></div>
            <div class="modal-body">
                <form>
                    <div class="registration-form">
                        <h4 class="tarif-name">Оформить тариф</h4>
                        <div>
                            <p>
                                Для подключения тарифа необходимо войти или зарегистрироваться в системе.
                            </p>
                        </div>
                        <div class="box-btn-line">
                            <button class="btn btn-normal btn-color" type="button" id="rd-tariff-guest-login-btn">Войти или зарегистрироваться</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal-registr modal-centered" role="dialog" tabindex="-1" id="rd-tarif-todo">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header head-empty"><button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img class="img-fluid" width="24" height="24" src="{{ asset('v2/img/icon-close-dark.svg') }}"></button></div>
            <div class="modal-body">
                <form>
                    <div class="registration-form">
                        <h4 class="tarif-name">Оформить тариф:&nbsp;<span>1 Месяц</span></h4>
                        <div>
                            <p>Подключайте тариф и&nbsp;пользуйтесь&nbsp;им в&nbsp;течение&nbsp;<span>30</span>&nbsp;<span>дней</span></p>
                        </div>
                        <div class="box-btn-line"><button class="btn btn-normal btn-color" type="button" aria-label="Close" data-bs-dismiss="modal">Вернуться на главную</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#rd-drive-form').on('submit', function(e) {
            e.preventDefault();
            authRegistrationForm('#rd-drive-form')
        });

        $('#rd-account-form').on('submit', function(e) {
            e.preventDefault();
            authRegistrationForm('#rd-account-form')
        });

        $('#rd-registr-form').on('submit', function(e) {
            e.preventDefault();
            authRegistrationForm('#rd-registr-form')
        });
    });

    // Правка от 08.07 АГ
          // Обработчик для кнопки "Войти или зарегистрироваться" в модальном окне тарифа для гостей
        $(document).on('click', '#rd-tariff-guest-login-btn', function() {
            $('#rd-tariff-guest').modal('hide');
            setTimeout(function() {
                $('#rd-registr').modal('show');
            }, 400);
        });

        // Обработчик для кнопки "Войти или зарегистрироваться" в модальном окне тест-драйва для гостей
        $(document).on('click', '#rd-testdrive-guest-login-btn', function() {
            $('#rd-testdrive-guest').modal('hide');
            setTimeout(function() {
                $('#rd-registr').modal('show');
            }, 400);
        });
   





    function authRegistrationForm(form_id) {

        $('#errorModal').modal('hide');
        $('#errorModal .modal-body .alert').html('');

        $(form_id+'-btn').prop('disabled', true);

        $.ajax({
            url: $(form_id).attr('action'),
            type: 'POST',
            data: $(form_id).serialize(),
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function(response) {
                // Успешная регистрация
                $(form_id+'-message').html(
                    '<div class="alert alert-success">' + response.message + '</div>'
                );

                if (response.redirect) {
                    window.location.href = response.redirect;
                }
            },
            error: function(xhr) {

                $('#errorModal').modal('show');
                $('#errorModal .modal-title').html('Ошибка!');

                // Обработка ошибок
                if (xhr.status === 422) {
                    // Валидационные ошибки
                    var errors = xhr.responseJSON.errors;
                    $.each(errors, function(key, value) {
                        $('#errorModal .modal-body .alert').append('- '+value[0]+'<br />');
                    });
                } else {
                    // Другие ошибки
                    var errorMessage = xhr.responseJSON?.message ||
                        'An error occurred during registration.';

                    $('#errorModal .modal-body .alert').html(errorMessage);
                }

            },
            complete: function() {
                $(form_id+'-btn').prop('disabled', false);
            }
        });
    }
</script>
