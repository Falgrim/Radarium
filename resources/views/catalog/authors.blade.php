<x-global-layout>

    <x-search-specialists :$specialitiesList :$request :$errors />

    @if ($errors->any())

        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $key => $error)
                    <li>{{ $key }} - {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="container my-5">
        <div class="row justify-content-between border py-2">
            <div class="col-4">
                Результат выборки:
            </div>
            <div class="col-4 text-end">
                Найдено: {{ $authors->total() }}
            </div>
        </div>

        <div class="row table-responsive py-5">
            <table class="table table-striped table-sm result_table">
                <thead>
                <tr>
                    <th scope="col"></th>
                    <th scope="col">Рейтинг</th>
                    <th scope="col">Открытые данные</th>
                    <th scope="col">Специализация</th>
                    <th scope="col">Профильные навыки</th>
                    <th scope="col">Содержание сообщения</th>
                    <th scope="col">Последний комментарий</th>
                    <th scope="col"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($authors as $author)
                    <x-author-row :$author />
                @endforeach
                </tbody>
            </table>

            {{ $authors->links() }}
        </div>
    </div>
</x-global-layout>
