<footer>
    <div class="container">
        <nav class="navbar">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="{{ route('index') }}">
                    <img src="{{ asset('v2/img/logo-radarium-w.svg') }}">
                </a>
                <div class="collapse navbar-collapse" hidden="">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item"><a class="nav-link active" href="#">Отрасли</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Почему Radarium</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Тарифы</a></li>
                        <!--<li class="nav-item"><a class="nav-link" href="#">Авторы</a></li>-->
                        <li class="nav-item"><a class="nav-link" href="#">Как работает?</a></li>
                    </ul>
                    <button class="btn btn-profile" type="button" data-bs-toggle="modal" data-bs-target="#rd-registr">
                        <img src="{{ asset('v2/img/icon-profile-w.svg') }}"><span>Войти</span>
                    </button>
                </div>
            </div>
        </nav>
    </div>
</footer>

<script>
    $(document).ready(function () {
        $('.moderation-alert').on('click', function (e) {
            e.preventDefault();

            $('#moderationAlert_type').val($(this).data('type'));
            $('#moderationAlert_row_id').val($(this).data('id'));

            return false;
        });

        $(document).on('click', '.moderation-alert-save', function(e) {
            e.preventDefault();

            $('#moderationAlert').modal('hide');

            $.ajax({
                url: "{{ route('moderationAlert.new') }}",
                dataType: 'json',
                type: 'POST',
                data: {
                    '_token': "{{ csrf_token() }}",
                    'type': $('#moderationAlert input[name=type]').val(),
                    'row_id': $('#moderationAlert input[name=row_id]').val(),
                    'description': $('#moderationAlert textarea[name=description]').val()
                },
                success:function(response){
                    $('#moderationAlertResult .modal-body p').html('Спасибо! В ближайшее время наша команда рассмотрит ваш запрос.')
                    $('#moderationAlertResult').modal('show');
                },
                error:function(response, responseCode){
                    $('#moderationAlertResult .modal-body p').html('Упс! Возникла ошибка, попробуйте повторить ваш запрос.')
                    $('#moderationAlertResult').modal('show');
                    console.log(responseCode);
                    console.log(response.responseJSON);
                },
            });

            return false;
        });
    });
</script>
