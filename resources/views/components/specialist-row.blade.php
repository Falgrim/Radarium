<tr id="specialist-id-{{ $specialist->id }}">
    <td>
        <a href="https://t.me/{{ $specialist->user->username ? $specialist->user->username : $specialist->user->user_id }}" target="_blank">{{ $specialist->user->username ? $specialist->user->username : $specialist->user->user_id }}</a>
        <a href="{{ route('catalog.specialist.view', ['id' => $specialist->id]) }}" class="btn btn-primary btn-sm">Подробнее</a>
    </td>
    <td>{{ trim($specialist->last_name.' '.$specialist->first_name) }}</td>
    <td>{{ implode(";\r\n", $specialist->specialtiesWithTitle()) }}</td>
    <td>{{ $specialist->created_at }}</td>
    <td>{{ $specialist->post_date }}</td>
    <td>{{ $specialist->experience }}</td>
    <td>{{ $specialist->soft_experience }}</td>
    <td>{{ $specialist->education }}</td>
    <td>{{ $specialist->work_schedule }}</td>
    <td>{{ $specialist->total_work_project }}</td>
    <td>{{ $specialist->type_of_work }}</td>
    <td>{{ $specialist->price_by_hour }}</td>
    <td>{{ $specialist->price_by_project }}</td>
    <td>{{ $specialist->price_by_month }}</td>
    <td>{{ $specialist->about }}</td>
    <td>{{ $specialist->spec_requirements }}</td>
    <td>{{ $specialist->post?->post }}</td>
    <td>{{ $specialist->link_resume }}</td>
</tr>
