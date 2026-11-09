{{-- JSON is escaped by Blade; JavaScript reads as text and never inserts it as HTML. --}}
@if(session('success'))<p hidden data-toast-message data-toast-type="success">{{ session('success') }}</p>@endif
@if(session('status'))<p hidden data-toast-message data-toast-type="info">{{ session('status') }}</p>@endif
<div id="notification-region" class="notification-region" role="region" aria-label="Notifikasi" aria-live="polite" aria-relevant="additions"></div>
