<div class="row justify-content-betweenpy-2 mt-3">
    <div class="col-4">
        {{ $review->created_at->format('d.m.Y') }}
    </div>
    <div class="col-8 text-end">
        {{ $review->rating }}
    </div>
</div>
<div class="row mt-2 bg-block-blue pt-3" data-id="{{ $review->id }}" data-rating="{{ $review->rating }}" data-text="{{ $review->text }}">
    <strong>{{ $review->user->name }}:</strong><br />
    <p>{!! $review->text !!}</p>
    @if(Auth::check() AND $review->can_edit AND $review->user_id === Auth::user()->id)
        <br />
        [<a href="#" class="review-edit"><small>редактировать</small></a>]
    @endif
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
