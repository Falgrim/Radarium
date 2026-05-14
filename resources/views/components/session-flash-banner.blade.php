@if (session('csrf_alert'))
    <div class="container py-2">
        <div class="alert alert-warning alert-dismissible fade show mb-0" role="alert">
            {{ session('csrf_alert') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    </div>
@elseif (request()->boolean('idle_timeout'))
    <div class="container py-2">
        <div class="alert alert-info alert-dismissible fade show mb-0" role="alert">
            Сессия закрыта из‑за отсутствия активности более {{ max(1, (int) config('session.lifetime', 30)) }} минут. При необходимости войдите снова.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    </div>
@endif
