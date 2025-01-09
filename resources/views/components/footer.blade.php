<div class="container">
    <footer class="py-3 my-4">
        <ul class="nav justify-content-center border-bottom pb-3 mb-3">
            <li class="nav-item"><a href="{{ route('index') }}" class="nav-link px-2 text-body-secondary">Главная</a></li>
            <li class="nav-item"><a href="{{ route('catalog.specialists') }}" class="nav-link px-2 text-body-secondary">Специалисты</a></li>
            <li class="nav-item"><a href="{{ route('catalog.companyjobs') }}" class="nav-link px-2 text-body-secondary">Вакансии</a></li>
        </ul>
        <p class="text-center text-body-secondary">© {{ date('Y') }} Исполнители</p>
    </footer>
</div>
