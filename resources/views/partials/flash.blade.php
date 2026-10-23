@foreach (['success', 'warning', 'error'] as $type)
    @if (session($type))
        <div class="alert alert-{{ $type }}" role="status">{{ session($type) }}</div>
    @endif
@endforeach
@if ($errors->any() && ! ($hideErrorSummary ?? false))
    <div class="alert alert-error" role="alert">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif
