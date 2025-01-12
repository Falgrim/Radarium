<div class="card">
    <div class="card-header">
        {{ $review->user->name }}
    </div>
    <div class="card-body">
        <blockquote class="blockquote mb-0">
            <p>{!! $review->text !!}</p>
            <footer class="blockquote-footer">{{ $review->created_at }}</footer>
        </blockquote>
    </div>
</div>
