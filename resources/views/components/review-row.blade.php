<div class="card">
    <div class="card-header">
        {{ $review->user->name }}
    </div>
    <div class="card-body">
        <blockquote class="blockquote mb-0 review-block" data-id="{{ $review->id }}" data-rating="{{ $review->rating }}" data-text="{{ $review->text }}">
            <div class="review-text">
                <p class="rating">Оценка: {{ $review->rating }}</p>
                <p class="text">{!! $review->text !!}</p>
            </div>
            <div class="review-from"></div>
            <footer class="blockquote-footer" style="margin-top: 0;">
                {{ $review->created_at }}
                @if(Auth::check() AND $review->can_edit AND $review->user_id === Auth::user()->id)
                    [<a href="#" class="review-edit"><small>редактировать</small></a>]
                @endif
            </footer>
        </blockquote>
    </div>
</div>

@pushOnce('scripts')
    <script type="module">
        $(document).ready(function () {
            $('.review-edit').on('click', function (e) {
                e.preventDefault();

                var parent_block = $(this).closest('.review-block');
                var review_text = $(parent_block).data('text');

                $(parent_block).find('.review-text').hide();
                $(parent_block).find('.review-from').html(''
                    +'<div class="mb-3">'
                    +'<label for="text" class="form-label">Отзыв</label>'
                +'<textarea class="form-control" name="review_text" rows="4">'+review_text+'</textarea>'
                +'</div>'
                +'<div class="mb-3">'
                    +'<label for="rating" class="form-label">Ваша оценка</label>'
                    +'<input type="range" class="form-range" name="rating" min="0" max="5" value="'+$(parent_block).data('rating')+'">'
                +'</div>'
                +'<button type="button" class="btn btn-success review-edit-save">Сохранить</button>'
                +'<button type="button" class="btn btn-primary review-edit-cancel">Отменить</button>').show();

                return false;
            });

            $(document).on('click', '.review-edit-cancel', function(e) {
                e.preventDefault();
                var parent_block = $(this).closest('.review-block');
                $(parent_block).find('.review-from').html('').hide();
                $(parent_block).find('.review-text').show();
                return false;
            });

            $(document).on('click', '.review-edit-save', function(e) {
                e.preventDefault();
                var parent_block = $(this).closest('.review-block');

                $.ajax({
                    url: "{{ url()->current() }}/review/",
                    dataType: 'json',
                    type: 'POST',
                    data: {
                        '_token': "{{ csrf_token() }}",
                        'review_id': $(parent_block).data('id'),
                        'text': $(parent_block).find('textarea[name=review_text]').val(),
                        'rating': $(parent_block).find('input[name=rating]').val()
                    },
                    success:function(response){
                        $(parent_block).find('.review-text').html('Ваш отзыв изменен. После модерации он будет опубликован.')
                        $(parent_block).find('.blockquote-footer').remove();
                    },
                    error:function(response, responseCode){
                        console.log(responseCode);
                        console.log(response.responseJSON);
                    },
                });

                return false;
            });
        });
    </script>
@endPushOnce
