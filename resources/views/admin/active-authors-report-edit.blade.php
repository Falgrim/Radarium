@extends('moonshine::layouts.app')

@section('content')
    <div class="p-4 space-y-4 max-w-3xl">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold">Статусы ИИ: {{ $type->label() }}</h1>
            <a href="{{ $reportUrl }}" class="btn btn-secondary">← К отчёту</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="rounded-lg border border-gray-200 dark:border-dark-600 p-4 text-sm space-y-2">
            <p><span class="text-gray-500">ID записи:</span> {{ $domain->id }}</p>
            @if($domain->post)
                <p><span class="text-gray-500">ID сообщения:</span> {{ $domain->post->id }}</p>
            @endif
        </div>

        <form method="post" action="{{ route('admin.active-authors-report.update') }}" class="space-y-4 rounded-lg border border-gray-200 dark:border-dark-600 p-4">
            @csrf
            <input type="hidden" name="type" value="{{ $type->value }}">
            <input type="hidden" name="domain_id" value="{{ $domain->id }}">

            <div>
                <label class="block text-sm font-medium mb-1">Статус записи ИИ (каталог)</label>
                <select name="domain_status" class="form-select w-full max-w-md rounded-md border-gray-300 dark:border-dark-500 dark:bg-dark-900" required>
                    @foreach($domainStatusList as $val => $label)
                        <option value="{{ $val }}" @selected((int) $val === (int) $domain->status->value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if($post)
                <div>
                    <label class="block text-sm font-medium mb-1">Статус ИИ сообщения</label>
                    <select name="post_ai_status" class="form-select w-full max-w-md rounded-md border-gray-300 dark:border-dark-500 dark:bg-dark-900">
                        @foreach($postAiStatusList as $val => $label)
                            <option value="{{ $val }}" @selected((int) $val === (int) $post->ai_parse_status->value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <button type="submit" class="btn btn-primary">Сохранить</button>
        </form>
    </div>
@endsection
