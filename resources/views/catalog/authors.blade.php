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
                            <th>&nbsp;</th>
                            <th>Открытые данные</th>
                            <th>Специализация</th>
                            <th>Профильные навыки</th>
                            <th>Последнее сообщение</th>
                            <th>Последний&nbsp;комментарий</th>
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
        <div class="container">
            <div class="row section-finish">
                <div class="col">
                    <div class="finish-item">
                        <div class="finish-text">
                            <h3 class="title-40semibold">Предложите<br>Свою Идею</h3>
                            <p>
                                <a href="#"><img class="social" src="{{ asset('v2/img/icon-telegram.svg') }}"></a>
                                <a href="#"><img class="social" src="{{ asset('v2/img/icon-whatsapp.svg') }}"></a>
                            </p>
                        </div>
                        <div class="finish-img"><img src="{{ asset('v2/img/plastic-metall.png') }}"></div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="finish-item">
                        <div class="finish-text">
                            <h1 class="title-40semibold">Техническая<br>Поддержка</h1>
                            <p>
                                <a href="#"><img class="social" src="{{ asset('v2/img/icon-telegram.svg') }}"></a>
                                <a href="#"><img class="social" src="{{ asset('v2/img/icon-whatsapp.svg') }}"></a>
                            </p>
                        </div>
                        <div class="finish-img"><img src="{{ asset('v2/img/plastic-base.png') }}"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

</x-global-layout>
