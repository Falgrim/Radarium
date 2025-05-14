<div class="container">
    <footer class="row row-cols-1 row-cols-sm-2 row-cols-md-5 py-5 my-5">
        <div class="col mb-3">
            <a href="/" class="d-flex align-items-center mb-3 link-body-emphasis text-decoration-none">
                <svg class="bi me-2" width="40" height="32"><use xlink:href="#bootstrap"></use></svg>
            </a>
            <p class="text-body-secondary">© {{ date('Y') }} Исполнители</p>
        </div>

        <div class="col mb-2">

        </div>

        <div class="col mb-4">
            <h5>Техническая поддержка</h5>
            <ul class="nav flex-column">
                <li class="nav-item mb-2"><a href="#" class="nav-link p-0 text-body-secondary">Telegram</a></li>
                <li class="nav-item mb-2"><a href="#" class="nav-link p-0 text-body-secondary">Telegram</a></li>
            </ul>
        </div>

        <div class="col mb-3">
            <h5>Предложить идею</h5>
            <ul class="nav flex-column">
                <li class="nav-item mb-2"><a href="#" class="nav-link p-0 text-body-secondary">Telegram</a></li>
                <li class="nav-item mb-2"><a href="#" class="nav-link p-0 text-body-secondary">Telegram</a></li>
            </ul>
        </div>
    </footer>
</div>


<!-- Modal -->
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
