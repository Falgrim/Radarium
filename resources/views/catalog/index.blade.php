<x-global-layout>
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
