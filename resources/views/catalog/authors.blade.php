<x-global-layout>
    <main>
        <div class="container">
            <div class="row">
                <div class="col">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('index') }}"><span>Главная</span></a></li>
                        <li class="breadcrumb-item active"><span>Поиск</span></li>
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
                <h5 hidden="">Показать&nbsp;еще 20&nbsp;соискателей</h5>
                <nav class="bg-transparent border-0 shadow-none">
                     <style>
                        .pagination-box .pagination {
                            font-size: 0.875rem;
                        }
                        .pagination-box .page-link {
                            font-size: 0.875rem;
                            padding: 0.375rem 0.75rem;
                        }
                    </style>
                    {{ $authors->links() }}
                </nav>
            </div>
        </div>

        <x-footer-finish />
    </main>

</x-global-layout>
