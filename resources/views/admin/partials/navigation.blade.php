@php($adminNavItems = \App\Support\AdminNavigation::forUser(auth()->user(), request()->route()?->getName()))
<nav class="admin-nav" aria-label="Menu utama administrasi" data-admin-nav>
  @foreach($adminNavItems as $entry)
    @if($entry['type'] === 'link')
      <a class="admin-nav-link {{ $entry['active'] ? 'current' : '' }}" href="{{ route($entry['route']) }}" aria-label="{{ $entry['label'] }}" @if($entry['active']) aria-current="page" @endif>
        <svg class="icon" aria-hidden="true"><use href="#ic-{{ $entry['icon'] }}"></use></svg><span class="admin-nav-label">{{ $entry['label'] }}</span>
      </a>
    @else
      <details class="admin-nav-group {{ $entry['active'] ? 'is-current' : '' }}" data-admin-nav-group @if($entry['active']) open @endif>
        <summary class="admin-nav-group-toggle" aria-label="{{ $entry['label'] }}" title="{{ $entry['label'] }}">
          <svg class="icon" aria-hidden="true"><use href="#ic-{{ $entry['icon'] }}"></use></svg>
          <span class="admin-nav-label">{{ $entry['label'] }}</span>
          <svg class="icon admin-nav-chevron" aria-hidden="true"><use href="#ic-chevron-down"></use></svg>
        </summary>
        <div class="admin-nav-submenu">
          @foreach($entry['items'] as $item)
            <a class="admin-nav-link admin-nav-child {{ $item['active'] ? 'current' : '' }}" href="{{ route($item['route']) }}" @if($item['active']) aria-current="page" @endif>
              <span>{{ $item['label'] }}</span>
            </a>
          @endforeach
        </div>
      </details>
    @endif
  @endforeach
</nav>
