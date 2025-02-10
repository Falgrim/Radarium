@php
    $formErrorClass = "";
@endphp

@if ($errors->any())
    @php
    $formErrorClass = "was-validated";
    @endphp
@endif

<div class="my-5">
    <div class="p-5 bg-body-tertiary">
        <div class="container">
            <div class="col-md-12 col-lg-12">
                <h4 class="mb-3">Фильтр исполнителей</h4>
                <form class="needs-validation {{ $formErrorClass }}" method="GET" action="{{ route('catalog.specialists') }}" novalidate="">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <x-input-label for="key_word" class="form-label" :value="__('Ключевое слово')" />
                            <input type="text" class="form-control" name="key_word" id="key_word" placeholder="" value="{{ old('key_word', $request->key_word) }}">
                            <x-input-validate :messages="$errors->get('key_word')" />
                        </div>

                        <div class="col-md-6">
                            <x-input-label for="speciality_id" class="form-label" :value="__('Специальность')" />
                            <select class="form-select" name="speciality_id" id="speciality_id">
                                <option value="">Выберите...</option>
                                @foreach ($specialitiesList as $speciality)
                                    <option value="{{ $speciality['id'] }}" {{ (collect(old('speciality_id', $request['speciality_id']))->contains($speciality['id'])) ? 'selected':'' }}>{{ $speciality['value'] }}</option>
                                @endforeach
                            </select>
                            <x-input-validate :messages="$errors->get('speciality_id')" />
                        </div>
                    </div>

                    <hr class="my-4">

                    <button class="w-50 btn btn-primary btn-lg" type="submit">Поиск</button>
                </form>
            </div>
        </div>
    </div>
</div>
