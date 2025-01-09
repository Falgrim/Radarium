<x-global-layout>

    <x-search-specialists :$experienceList :$request :$errors />

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
                <h1 class="text-body-emphasis">Исполнители (всего: {{ $specialists->total() }})</h1>
                <div class="accordion accordion-flush" id="accordionFlushSpecialists">
                    @foreach ($specialists as $specialist)
                        <x-specialist-row :$specialist parent-id="accordionFlushSpecialists" />
                    @endforeach
                </div>
            </div>

            {{ $specialists->links() }}
        </div>
    </div>
</x-global-layout>
