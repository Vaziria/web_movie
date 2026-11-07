{{-- Compact ID / EN switch: links to the same page in the other language. --}}
@foreach (site_locales() as $code => $info)
    @if ($code === app()->getLocale())
        <span class="inline-flex h-8 min-w-9 items-center justify-center rounded-md bg-ink-800 px-2 font-medium uppercase text-white" aria-current="true">{{ $code }}</span>
    @else
        <a href="{{ current_url_in($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $info['name'] }}"
           class="inline-flex h-8 min-w-9 items-center justify-center rounded-md px-2 uppercase text-neutral-400 hover:text-white">{{ $code }}</a>
    @endif
@endforeach
