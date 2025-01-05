<div class="accordion-item">
    <h2 class="accordion-header" id="specialist-id-{{ $specialist->id }}">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#specialist-block-id-{{ $specialist->id }}" aria-expanded="false" aria-controls="specialist-block-id-{{ $specialist->id }}">
            ID {{ $specialist->id }}. {{ $specialist->user->username }}.
            <br />Дата обновления: {{ $specialist->created_at }}
        </button>
    </h2>
    <div id="specialist-block-id-{{ $specialist->id }}" class="accordion-collapse collapse" aria-labelledby="specialist-id-{{ $specialist->id }}" data-bs-parent="#{{ $parentId  }}">
        <div class="accordion-body" style="text-align: left;">
            <a href="{{ route('catalog.specialist.view', ['id' => $specialist->id]) }}" class="btn btn-primary" type="button">Открыть карточку</a>
            <dl class="row">
                <dt class="col-sm-3">Опыт работы по специальности</dt>
                <dd class="col-sm-9">{{ $specialist->experience }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Владение ПО</dt>
                <dd class="col-sm-9">{{ $specialist->soft_experience }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Образование</dt>
                <dd class="col-sm-9">{{ $specialist->education }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Требуемый график работы</dt>
                <dd class="col-sm-9">{{ $specialist->work_schedule }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Общая продолжительность работы (проект)</dt>
                <dd class="col-sm-9">{{ $specialist->total_work_project }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Тип работы</dt>
                <dd class="col-sm-9">{{ $specialist->type_of_work }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Желаемая оплата (за час)</dt>
                <dd class="col-sm-9">{{ $specialist->price_by_hour }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Желаемая оплата (за проект)</dt>
                <dd class="col-sm-9">{{ $specialist->price_by_project }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Желаемая оплата (в месяц)</dt>
                <dd class="col-sm-9">{{ $specialist->price_by_month }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">О себе</dt>
                <dd class="col-sm-9">{{ $specialist->about }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Спец. требования</dt>
                <dd class="col-sm-9">{{ $specialist->spec_requirements }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Резюме</dt>
                <dd class="col-sm-9">{{ $specialist->link_resume }}</dd>
            </dl>
        </div>
    </div>
</div>
