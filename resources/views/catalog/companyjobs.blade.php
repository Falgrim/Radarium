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
        <div class="p-5 bg-body-tertiary">
            <div class="container py-5">
                <h1 class="text-body-emphasis">Вакансий (всего: {{ $companyJobs->total() }})</h1>
                <div class="accordion accordion-flush" id="accordionFlushSpecialists">
                    @foreach ($companyJobs as $companyJob)
                        <x-companyjob-row :$companyJob parent-id="accordionFlushSpecialists" />
                    @endforeach
                </div>
            </div>

            {{ $companyJobs->links() }}
        </div>
    </div>
</x-global-layout>
