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
        $(document).on('click', '.moderation-alert', function () {
            $('#moderationAlert_type').val($(this).data('type'));
            $('#moderationAlert_row_id').val($(this).data('id'));
            var postId = $(this).data('api-channel-post-id');
            $('#moderationAlert_api_channel_post_id').val(postId !== undefined && postId !== null ? postId : '');
        });

        $(document).on('click', '.moderation-alert-save', function(e) {
            e.preventDefault();

            $('#moderationAlert').modal('hide');

            var payload = {
                    '_token': "{{ csrf_token() }}",
                    'type': $('#moderationAlert input[name=type]').val(),
                    'row_id': $('#moderationAlert input[name=row_id]').val(),
                    'description': $('#moderationAlert textarea[name=description]').val()
            };
            var postField = $('#moderationAlert input[name=api_channel_post_id]').val();
            if (postField !== undefined && postField !== null && String(postField).trim() !== '') {
                payload.api_channel_post_id = postField;
            }

            $.ajax({
                url: "{{ route('moderationAlert.new') }}",
                dataType: 'json',
                type: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                data: payload,
                success:function(response){
                    $('#moderationAlertResult .modal-body p').html('Спасибо! В ближайшее время наша команда рассмотрит ваш запрос.')
                    $('#moderationAlertResult').modal('show');
                },
                error:function(xhr){
                    var msg = 'Упс! Возникла ошибка, попробуйте повторить ваш запрос.';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON.errors) {
                            var parts = [];
                            $.each(xhr.responseJSON.errors, function (key, val) {
                                if (val && val[0]) {
                                    parts.push(val[0]);
                                }
                            });
                            if (parts.length) {
                                msg = parts.join(' ');
                            }
                        }
                    }
                    $('#moderationAlertResult .modal-body p').html(msg);
                    $('#moderationAlertResult').modal('show');
                },
            });

            return false;
        });
    });
</script>
