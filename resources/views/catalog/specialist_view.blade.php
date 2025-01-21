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
                            <x-specialist-row :$specialist parent-id="accordionFlushSpecialists" />
                        </tbody>
                    </table>
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
