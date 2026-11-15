@props(['content' => ''])
@php
    // HTML from learning content must never be trusted. CommonMark escapes source HTML
    // and rejects unsafe URL schemes before output is treated as safe HTML markup.
    $learningHtml = new \Illuminate\Support\HtmlString(\Illuminate\Support\Str::markdown((string) $content, [
        'html_input' => 'escape',
        'allow_unsafe_links' => false,
    ]));
@endphp
<div class="class-course-document">{{ $learningHtml }}</div>
