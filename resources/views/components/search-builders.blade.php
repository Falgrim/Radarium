@php
    $formErrorClass = "";
@endphp

@if ($errors->any())
    @php
    $formErrorClass = "was-validated";
    @endphp
@endif

<div class="my-5">
    <div class="p-3 bg-body-tertiary">
        <div class="container">
            <div class="col-md-12 col-lg-12">
                <div class="row col-12">
                    <form class="needs-validation {{ $formErrorClass }} mt-2" id="search_form" method="GET" action="{{ route('catalog.builders') }}" novalidate="">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <x-input-label for="key_word" class="form-label" :value="__('Поиск в сообщении')" />
                                <input type="text" class="form-control" name="key_word" id="key_word" placeholder="" value="{{ old('key_word', $request->key_word) }}">
                                <x-input-validate :messages="$errors->get('key_word')" />
                                <div class="button-container" id="buttonsContainer">
                                    @if (isset($request->key_word_tags))
                                        @foreach ($request->key_word_tags as $keyWordTags)
                                            <button class="dynamic-button btn btn-outline-secondary btn-sm">{{ $keyWordTags }}</button>
                                            <input type="hidden" name="key_word_tags[]" value="{{ $keyWordTags }}" class="hidden-input">
                                        @endforeach
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <x-input-label for="speciality_id" class="form-label" :value="__('Специализация')" />
                                <select class="form-select select-search-multiple" name="speciality_id[]" id="speciality_id" multiple>
                                    <option value="">Выберите...</option>
                                    @foreach ($specialitiesList as $speciality)
                                        <option value="{{ $speciality['id'] }}" {{ (collect(old('speciality_id', $request['speciality_id']))->contains($speciality['id'])) ? 'selected':'' }}>{{ $speciality['value'] }}</option>
                                    @endforeach
                                </select>
                                <x-input-validate :messages="$errors->get('speciality_id')" />
                            </div>
                        </div>

                        <button class="w-10 btn btn-primary mt-3" type="submit" name="search">Поиск</button>
                        <button class="w-10 btn btn-primary mt-3" type="reset" onclick="window.location.href='{{route('catalog.builders')}}'">Сбросить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@pushOnce('scripts')
    <script type="module">
        $(document).ready(function() {
            $('.select-search-multiple').select2({
                theme: "bootstrap-5",
                selectionCssClass: 'select2--small',
                dropdownCssClass: "select2--small",
                placeholder: 'Выберите...',
                allowClear: true,
                language: 'ru',
                closeOnSelect: false
            });

            $('#key_word').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();

                    if($('.dynamic-button').length >= 7) {
                        return false;
                    }

                    const text = $(this).val().trim();
                    if (text.length >= 2) {
                        const button = $('<button>')
                            .addClass('dynamic-button btn btn-outline-secondary btn-sm')
                            .text(text)
                            .on('click', function() {
                                $(this).next('input').remove();
                                $(this).remove();
                            });

                        const hiddenInput = $('<input>')
                            .attr({
                                type: 'hidden',
                                name: 'key_word_tags[]',
                                value: text
                            })
                            .addClass('hidden-input');

                        // Добавляем кнопку в контейнер
                        $('#buttonsContainer').append(button).append(hiddenInput);;

                        // Очищаем input
                        $(this).val('');
                    }
                }
            });

            $('.dynamic-button').on('click', function() {
                $(this).next('input').remove();
                $(this).remove();
            });
        });
    </script>
@endPushOnce
