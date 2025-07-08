<div class="par2cols">
    <div class="review-block" data-id="{{ $review->id }}" data-rating="{{ $review->rating }}" >
        <p class="date-name">
            <span class="dn-date">{{ $review->created_at->format('d.m.Y') }}</span>
            <span class="dn-name">{{ $review->user->name }}</span>
        </p>
        <p class="review-text">{!! $review->text !!}</p>
        @if(Auth::check() AND $review->can_edit AND $review->user_id === Auth::user()->id)
            {{--<div class="review-from" data-text="{{ $review->text }}"></div>
            <p>
            [<a href="#" class="review-edit"><small>редактировать</small></a>]
            </p>--}}
        @endif
    </div>
    <div class="rate-stars">
        @for ($i = 1; $i <= 5; $i++)
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -32 576 576" width="1em" height="1em" fill="currentColor" class="@if ($review->rating >= $i) yes @endif star">
            <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
            <path d="M287.9 0c9.2 0 17.6 5.2 21.6 13.5l68.6 141.3 153.2 22.6c9 1.3 16.5 7.6 19.3 16.3s.5 18.1-5.9 24.5L433.6 328.4l26.2 155.6c1.5 9-2.2 18.1-9.6 23.5s-17.3 6-25.3 1.7l-137-73.2L151 509.1c-8.1 4.3-17.9 3.7-25.3-1.7s-11.2-14.5-9.7-23.5l26.2-155.6L31.1 218.2c-6.5-6.4-8.7-15.9-5.9-24.5s10.3-14.9 19.3-16.3l153.2-22.6L266.3 13.5C270.4 5.2 278.7 0 287.9 0zm0 79L235.4 187.2c-3.5 7.1-10.2 12.1-18.1 13.3L99 217.9 184.9 303c5.5 5.5 8.1 13.3 6.8 21L171.4 443.7l105.2-56.2c7.1-3.8 15.6-3.8 22.6 0l105.2 56.2L384.2 324.1c-1.3-7.7 1.2-15.5 6.8-21l85.9-85.1L358.6 200.5c-7.8-1.2-14.6-6.1-18.1-13.3L287.9 79z"></path>
        </svg>
        @endfor
    </div>
</div>

@pushOnce('scripts')
    <script type="module">
        $(document).ready(function () {
            $('.review-edit').on('click', function (e) {
                e.preventDefault();

                var parent_block = $(this).closest('.review-block');
                var review_text = $(parent_block).find('.review-from').data('text');

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
                        $(parent_block).html('<p>Ваш отзыв изменен. После модерации он будет опубликован.</p>')
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
