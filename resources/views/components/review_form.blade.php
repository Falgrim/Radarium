@if(Auth::check())
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Добавить отзыв</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    @if (Auth::user()->user_role_id == 2)
                        @if ($errors->review->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->review->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="" />
                            @csrf

                            <input type="hidden" name="author_id" value="{{ $rowId }}" />
                            <input type="hidden" name="specialist_id" value="" />
                            <div class="mb-3">
                                <label for="text" class="form-label">Напишите ваш отзыв</label>
                                <textarea class="form-control @error('text', 'review') is-invalid @enderror" id="text" name="text" rows="4">{{ old('text', $request->text) }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label for="rating" class="form-label">Ваша оценка (от 1 до 5)</label>
                                <input type="range" class="form-range @error('rating', 'review') is-invalid @enderror" name="rating" min="0" max="5" value="{{ old('rating', $request->rating) }}" id="rating">
                            </div>
                            <div class="mb-3">
                                <p>Вы можете добавит свое дополнительное поле для отзыва</p>
                            </div>
                            <div class="input-group mb-3">
                                <span class="input-group-text">Название</span>
                                <input type="text" class="form-control @error('extra_row.*.title', 'review') is-invalid @enderror" placeholder="" name="extra_row[0][title]" value="{{ old('extra_row.0.title', $request->extra_row ? $request->extra_row[0]['title'] : '') }}" aria-label="">
                                <span class="input-group-text">Значение</span>
                                <input type="text" class="form-control @error('extra_row.*.value', 'review') is-invalid @enderror" placeholder="" name="extra_row[0][value]" value="{{ old('extra_row.0.value', $request->extra_row ? $request->extra_row[0]['value'] : '') }}" aria-label="">
                            </div>
                            <button type="submit" class="btn btn-primary">Отправить</button>
                        </form>
                    @else
                        <p>Для добавления отзыва авторизуйтесь от лица компании</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
