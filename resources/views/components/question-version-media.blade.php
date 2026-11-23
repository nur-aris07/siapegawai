@props(['version', 'mediaSlot', 'domain', 'attempt', 'item', 'classId' => null])
@php
    // A single immutable media component is used in QQ/CAT/Class take, feedback and result.
    // The image is intentionally NOT a link: clicking inside a radio label selects that answer.
    $mediaRole = $mediaSlot === 'stem' ? 'stem' : ($mediaSlot === 'explanation' ? 'explanation' : 'option');
@endphp
@if($version)
    @foreach($version->media->where('slot', $mediaSlot)->sortBy('position') as $figure)
        @php
            $target = $domain === 'qq' ? route('member.quick-quizzes.media', [$attempt->id, $item->id, $figure->id])
                : ($domain === 'cat' ? route('member.tryouts.media', [$attempt->id, $item->id, $figure->id])
                : route('member.class-practices.media', [$classId, $attempt->id, $item->id, $figure->id]));
        @endphp
        <figure class="fig-participant-figure fig-participant-figure--{{ $mediaRole }}">
            <img src="{{ $target }}" alt="{{ $figure->alt_text ?: 'Ilustrasi soal' }}" width="{{ $figure->width_px }}" height="{{ $figure->height_px }}" draggable="false" loading="lazy" decoding="async">
            @if($figure->caption)<figcaption>{{ $figure->caption }}</figcaption>@endif
        </figure>
    @endforeach
@endif
