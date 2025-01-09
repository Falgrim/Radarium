<div class="accordion-item">
    <h2 class="accordion-header" id="specialist-id-{{ $companyJob->id }}">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#specialist-block-id-{{ $companyJob->id }}" aria-expanded="false" aria-controls="specialist-block-id-{{ $companyJob->id }}">
            ID {{ $companyJob->id }}. {{ $companyJob->apiPostUser->username }}.
            <br />Дата обновления: {{ $companyJob->created_at }}
        </button>
    </h2>
    <div id="specialist-block-id-{{ $companyJob->id }}" class="accordion-collapse collapse" aria-labelledby="specialist-id-{{ $companyJob->id }}" data-bs-parent="#{{ $parentId  }}">
        <div class="accordion-body" style="text-align: left;">
            <a href="{{ route('catalog.companyjob.view', ['id' => $companyJob->id]) }}" class="btn btn-primary" type="button">Открыть карточку</a>
            <dl class="row">
                <dt class="col-sm-3">Должность</dt>
                <dd class="col-sm-9">{{ $companyJob->position }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Название компании</dt>
                <dd class="col-sm-9">{{ $companyJob->company_name }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Предлагаемый оклад (мин.)</dt>
                <dd class="col-sm-9">{{ $companyJob->min_price }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Предлагаемый оклад (макс.)</dt>
                <dd class="col-sm-9">{{ $companyJob->max_price }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Обязанности</dt>
                <dd class="col-sm-9">{{ $companyJob->duty }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Требования</dt>
                <dd class="col-sm-9">{{ $companyJob->requirement }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">График</dt>
                <dd class="col-sm-9">{{ $companyJob->work_schedule }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Тип работы</dt>
                <dd class="col-sm-9">{{ $companyJob->type_of_work }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Описание проекта</dt>
                <dd class="col-sm-9">{{ $companyJob->description }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Срок найма</dt>
                <dd class="col-sm-9">{{ $companyJob->period }}</dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">Доп. условия</dt>
                <dd class="col-sm-9">{{ $companyJob->extra_conditions }}</dd>
            </dl>
        </div>
    </div>
</div>
