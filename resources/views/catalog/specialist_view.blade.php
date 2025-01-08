<x-global-layout>
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $key => $error)
                    <li>{{ $key }} - {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="my-5">
        <div class="p-5 text-center bg-body-tertiary">
            <div class="container py-5">
                <h1 class="text-body-emphasis">Исполнитель ID {{ $specialist->id }}</h1>
                <div class="accordion accordion-flush" id="accordionFlushSpecialists">
                    <x-specialist-row :$specialist parent-id="accordionFlushSpecialists" />
                </div>
            </div>
        </div>

        <div class="p-5 text-center bg-body-tertiary">
            <div class="container py-5">
                <h1 class="text-body-emphasis">Отзывы</h1>

            </div>
        </div>
    </div>
</x-global-layout>
