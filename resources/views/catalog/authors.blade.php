<x-global-layout>
    <main>
        <div class="container">
            <div class="row">
                <div class="col">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('catalog.specialists') }}"><span>Проектирование</span></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('catalog.specialists') }}"><span>Поиск</span></a></li>
                        <li class="breadcrumb-item active"><span>Специалист</span></li>
                    </ol>
                </div>
            </div>
        </div>

        <x-search-specialists :$authors :$specialitiesList :$request :$errors />

        <div class="container">
            <div class="table-box">
                <div class="table-responsive text-start">
                    <table class="table table-borderless tbl-default">
                        <thead>
                        <tr>
                            <th class="def-cell-01">&nbsp;</th>
                            <th class="def-cell-02">Открытые данные</th>
                            <th class="def-cell-03">Специализация</th>
                            <th class="def-cell-04">Профильные навыки</th>
                            <th class="def-cell-05">Последнее сообщение</th>
                            <th class="def-cell-06">Последний комментарий</th>
                        </tr>
                        </thead>
                        <tbody>

                        @foreach ($authors as $author)
                            <x-author-row :$author :$tariffAccess :$userOpenLog />
                        @endforeach

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="pagination-box">
                <nav class="bg-transparent border-0 shadow-none">
                    {{ $authors->links() }}
                </nav>
            </div>
        </div>

        <x-footer-finish />
    </main>

</x-global-layout>
