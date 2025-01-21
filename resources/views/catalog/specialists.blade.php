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
        <div class="p-5 bg-body-tertiary">
            <div class="container py-5">
                <h1 class="text-body-emphasis">Исполнители (всего: {{ $specialists->total() }})</h1>
                <div class="table-responsive">
                    <table class="table table-striped table-sm table-bordered">
                        <thead>
                        <tr>
                            <th scope="col">Телеграм никнейм</th>
                            <th scope="col">ФИО</th>
                            <th scope="col">Заявленные специальности</th>
                            <th scope="col">Дата попадания в базу</th>
                            <th scope="col">Дата последнего обнаружения этого объявления в канале</th>
                            <th scope="col">Опыт работы по специальности</th>
                            <th scope="col">Владение ПО</th>
                            <th scope="col">Образование</th>
                            <th scope="col">Требуемый график работы</th>
                            <th scope="col">Общая продолжительность работы - проекта</th>
                            <th scope="col">Тип работы: офис, удаленка, гибрид</th>
                            <th scope="col">Желаемая оплата за час</th>
                            <th scope="col">Желаемая оплата - общая сумма выплат за проект</th>
                            <th scope="col">Желаемая оплата - фиксированная оплата за период времени (месяц)</th>
                            <th scope="col">О себе</th>
                            <th scope="col">Спец. требования</th>
                            <th scope="col">Исходный текст сообщения</th>
                            <th scope="col">Ссылка на резюме</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($specialists as $specialist)
                            <x-specialist-row :$specialist parent-id="accordionFlushSpecialists" />
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $specialists->links() }}
        </div>
    </div>
</x-global-layout>
