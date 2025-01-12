<x-global-layout>
    @session('status')
    <div class="alert alert-info">
        <ul>
            <li>{{ $value }}</li>
        </ul>
    </div>
    @endsession

    @if ($errors->specialist->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->specialist->all() as $key => $error)
                    <li>{{ $key }} - {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="my-5">
        <div class="p-5 bg-body-tertiary">
            <div class="container py-5">
                <h1 class="text-body-emphasis">Исполнитель ID {{ $specialist->id }}</h1>
                <div class="accordion accordion-flush" id="accordionFlushSpecialists">
                    <x-specialist-row :$specialist parent-id="accordionFlushSpecialists" />
                </div>
            </div>
        </div>

        <div class="bg-body-tertiary">
            <div class="container py-3">
                <h1 class="text-body-emphasis">Отзывы</h1>
                @foreach ($reviews as $review)
                    <x-review-row :rowId="$specialist->id" :$review :$request :$errors />
                @endforeach
            </div>
        </div>

        <div class="bg-body-tertiary">
            <div class="container py-3">
                <x-review_form :rowId="$specialist->id" :$request :$errors />
            </div>
        </div>
    </div>
</x-global-layout>
