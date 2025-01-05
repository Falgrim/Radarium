<div class="container">
    <header class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-between py-3 mb-4 border-bottom">
        <div class="col-md-3 mb-2 mb-md-0">
            <a href="/" class="d-inline-flex link-body-emphasis text-decoration-none">
                Исполнители
            </a>
        </div>

        <ul class="nav col-12 col-md-auto mb-2 justify-content-center mb-md-0">
            <li><a href="{{ route('index') }}" class="nav-link px-2 link-secondary">Главная</a></li>
            <li><a href="{{ route('catalog.index') }}" class="nav-link px-2">Каталог</a></li>
        </ul>

        <div class="col-md-3 text-end">
            <button type="button" class="btn btn-outline-primary me-2">Вход</button>
            <button type="button" class="btn btn-primary">Регистрация</button>
        </div>
    </header>
</div>
