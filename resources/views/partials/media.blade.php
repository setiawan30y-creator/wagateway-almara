{{-- Tampilan media pesan — @2026-Alifiandra --}}
@if ($m->hasMedia())
    @php $u = $m->mediaUrl(); @endphp
    <div class="media">
        @if ($m->media_type === 'image')
            <a href="{{ $u }}" target="_blank" rel="noopener"><img src="{{ $u }}" alt="{{ $m->media_name }}" loading="lazy"></a>
        @elseif ($m->media_type === 'video')
            <video src="{{ $u }}" controls preload="metadata"></video>
        @elseif ($m->media_type === 'audio')
            <audio src="{{ $u }}" controls preload="none"></audio>
        @else
            <a class="doc" href="{{ $u }}?download=1">📄 <span>{{ $m->media_name }}</span> <small>{{ number_format(($m->media_size ?? 0) / 1024, 0, ',', '.') }} KB</small></a>
        @endif
    </div>
@endif
