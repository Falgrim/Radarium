@php
    $formErrorClass = "";
@endphp

@if ($errors->any())
    @php
    $formErrorClass = "was-validated";
    @endphp
@endif

<div class="my-5">
    <div class="p-5 text-center bg-body-tertiary">
        <div class="container">
            <div class="col-md-12 col-lg-12">
                <h4 class="mb-3">Фильтр исполнителей</h4>
                <form class="needs-validation {{ $formErrorClass }}" method="GET" action="{{ route('catalog.specialists') }}" novalidate="">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <x-input-label for="key_word" class="form-label" :value="__('Ключевое слово в опыте')" />
                            <input type="text" class="form-control" name="key_word" id="key_word" placeholder="" value="{{ old('key_word', $request->key_word) }}">
                            <x-input-validate :messages="$errors->get('key_word')" />
                        </div>

                        <div class="col-md-6">
                            <x-input-label for="experience_id" class="form-label" :value="__('Специальность')" />
                            <select class="form-select" name="experience_id" id="experience_id">
                                <option value="">Выберите...</option>
                                @foreach ($experienceList as $experience)
                                    <option value="{{ $experience['id'] }}" {{ (collect(old('experience_id', $request['experience_id']))->contains($experience['id'])) ? 'selected':'' }}>{{ $experience['value'] }}</option>
                                @endforeach
                            </select>
                            <x-input-validate :messages="$errors->get('experience_id')" />
                        </div>
                    </div>

                    <hr class="my-4">

                    <button class="w-50 btn btn-primary btn-lg" type="submit">Поиск</button>
                </form>
            </div>
        </div>
    </div>
</div>
