@php
    $formErrorClass = "";
    $contactsLimit = Auth::check() ? Auth::user()->getLeftContacts() : [];
@endphp

@if ($errors->any())
    @php
    $formErrorClass = "was-validated";
    @endphp
@endif

<div class="container">
    <div class="row">
        <div class="col">
            <div class="row-flex">
                <h5 class="tab-heading">Проектирование</h5>
                <button class="btn btn-link btn-trans btn-trash" type="reset" onclick="window.location.href='{{route('catalog.specialists')}}'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="-32 0 512 512" width="1em" height="1em" fill="currentColor">
                        <path d="M135.2 17.7C140.6 6.8 151.7 0 163.8 0H284.2c12.1 0 23.2 6.8 28.6 17.7L320 32h96c17.7 0 32 14.3 32 32s-14.3 32-32 32H32C14.3 96 0 81.7 0 64S14.3 32 32 32h96l7.2-14.3zM32 128H416V448c0 35.3-28.7 64-64 64H96c-35.3 0-64-28.7-64-64V128zm96 64c-8.8 0-16 7.2-16 16V432c0 8.8 7.2 16 16 16s16-7.2 16-16V208c0-8.8-7.2-16-16-16zm96 0c-8.8 0-16 7.2-16 16V432c0 8.8 7.2 16 16 16s16-7.2 16-16V208c0-8.8-7.2-16-16-16zm96 0c-8.8 0-16 7.2-16 16V432c0 8.8 7.2 16 16 16s16-7.2 16-16V208c0-8.8-7.2-16-16-16z"></path>
                    </svg>
                    <span>Очистить поиск</span>
                </button>
            </div>
            <form class="needs-validation {{ $formErrorClass }}" id="search_form" method="GET" action="{{ route('catalog.specialists') }}" novalidate="">
                <div class="search-field">
                    <div class="search-info search-item">
                        <h4>Найдено<span>{{ $authors->total() }}</span></h4>
                        <div class="form-check">
                            <input class="form-check-input" name="open_contacts" value="1" type="checkbox" id="formCheck-5" @checked(old('open_contacts', $request->open_contacts))>
                            <label class="form-check-label" for="formCheck-5">Показать только открытые</label>
                        </div>
                        <hr>
                        @if(Auth::check())
                        <h4>Доступно<span><span>{{ $contactsLimit['days_left'] }} Дня</span>|<span>{{ $contactsLimit['count_contacts_left'] }} Открытия</span></span></h4>
                        @endif
                    </div>
                    <div class="search-action search-item">
                        <div class="row">
                            <div class="col-lg-12 col-xl-6">
                                <select class="form-select select-search-multiple" name="speciality_id[]" id="speciality_id" multiple>
                                    <option value="">Выберите специализацию</option>
                                    @foreach ($specialitiesList as $speciality)
                                        <option value="{{ $speciality['id'] }}" {{ (collect(old('speciality_id', $request['speciality_id']))->contains($speciality['id'])) ? 'selected':'' }}>{{ $speciality['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-12 col-xl-6">
                                <span class="search-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="1em" height="1em" fill="currentColor">
                                        <path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path>
                                    </svg>
                                    <input class="form-control" type="text" name="key_word" id="key_word" {{ old('key_word', $request->key_word) }} placeholder="Введите запрос и нажмите Enter" title="Введите запрос и нажмите Enter">
                                </span>
                                <ul class="list-group tags" id="buttonsContainer">
                                    @if (isset($request->key_word_tags))
                                        @foreach ($request->key_word_tags as $keyWordTags)
                                            <li class="list-group-item">
                                                <input type="hidden" name="key_word_tags[]" value="{{ $keyWordTags }}" />
                                                <span>{{ $keyWordTags }}</span>
                                                <button class="btn btn-link dynamic-button" type="button" aria-label="Close">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="-64 0 512 512" width="1em" height="1em" fill="currentColor">
                                                        <path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"></path>
                                                    </svg>
                                                </button>
                                            </li>
                                        @endforeach
                                    @endif
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="search-item search-button">
                        <button class="btn btn-color btn-accent" type="submit" name="search">Обновить поиск</button>
                    </div>
                </div>
            </form>
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
                        const button = '<li class="list-group-item">'+
                            '   <input type="hidden" name="key_word_tags[]" value="'+text+'" />'+
                            '   <span>'+text+'</span>'+
                            '   <button class="btn btn-link dynamic-button" type="button" aria-label="Close">'+
                            '       <svg xmlns="http://www.w3.org/2000/svg" viewBox="-64 0 512 512" width="1em" height="1em" fill="currentColor">'+
                            '           <path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"></path>'+
                            '       </svg>'+
                            '    </button>'+
                            '</li>'

                        dynamicButtonRemove();

                        // Добавляем кнопку в контейнер
                        $('#buttonsContainer').append(button);

                        // Очищаем input
                        $(this).val('');
                    }
                }
            });

            dynamicButtonRemove();
        });

        function dynamicButtonRemove() {
            $(document).on("click", ".dynamic-button", function() {
                $(this).closest('.list-group-item').remove();
            });
        }
    </script>
@endPushOnce
