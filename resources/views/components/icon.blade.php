@props(['name', 'class' => 'size-5'])

<svg
    {{ $attributes->class([$class, 'shrink-0']) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.6"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>
    @switch($name)
        @case('fleet')
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
            <path d="M17 13.5v7M13.5 17h7" />
            @break
        @case('baby')
            <circle cx="12" cy="7.5" r="3.5" />
            <path d="M5.5 20.5c.7-3.4 3.3-5.5 6.5-5.5s5.8 2.1 6.5 5.5" />
            @break
        @case('logout')
            <path d="M9 4.5H5.5a1.5 1.5 0 0 0-1.5 1.5v12a1.5 1.5 0 0 0 1.5 1.5H9" />
            <path d="M15 8.5 18.5 12 15 15.5M18.5 12H9" />
            @break
        @case('arrow-left')
            <path d="M19 12H5M11 18l-6-6 6-6" />
            @break
        @case('arrow-right')
            <path d="M5 12h14M13 6l6 6-6 6" />
            @break
        @case('sliders')
            <path d="M4 7h9M17 7h3M4 17h3M11 17h9" />
            <circle cx="15" cy="7" r="2" />
            <circle cx="9" cy="17" r="2" />
            @break
        @case('thermometer')
            <path d="M10 13.6V5.5a2 2 0 1 1 4 0v8.1a4 4 0 1 1-4 0Z" />
            <path d="M12 11v5.5" />
            @break
        @case('droplets')
            <path d="M12 3.5c-2.4 3.2-4.5 5.7-4.5 8.4a4.5 4.5 0 0 0 9 0c0-2.7-2.1-5.2-4.5-8.4Z" />
            <path d="M9.5 12.5a2.5 2.5 0 0 0 2.5 2.5" />
            @break
        @case('wind')
            <path d="M3.5 8.5h11a2.5 2.5 0 1 0-2.4-3.2M3.5 12.5h15.2a2.3 2.3 0 1 1-2.2 3M3.5 16.5h6" />
            @break
        @case('flame')
            <path d="M12 3.5c.6 2.8 4.5 4.3 4.5 8.2a4.5 4.5 0 0 1-9 0c0-2 .9-3.4 2.2-5 .3 1.3 1 2.2 2.3 2.7.4-2-.1-3.7-.1-5.9Z" />
            @break
        @case('bulb')
            <path d="M9 16.5h6M10 20h4" />
            <path d="M8.4 13.2a5.5 5.5 0 1 1 7.2 0c-.9.8-1.2 1.5-1.2 2.3H9.6c0-.8-.3-1.5-1.2-2.3Z" />
            @break
        @case('signal')
            <path d="M4.5 15.5v3M9.5 11.5v7M14.5 7.5v11M19.5 4.5v14" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7.5V12l3 2" />
            @break
        @case('alert')
            <path d="M10.3 4.4 3.2 17a2 2 0 0 0 1.7 3h14.2a2 2 0 0 0 1.7-3L13.7 4.4a2 2 0 0 0-3.4 0Z" />
            <path d="M12 9v4M12 16.5v.5" />
            @break
        @case('check')
            <path d="m5 12.5 4.5 4.5L19 7" />
            @break
        @case('link')
            <path d="M10 13.5a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.6l-1.3 1.3" />
            <path d="M14 10.5a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.6l1.3-1.3" />
            @break
        @case('unlink')
            <path d="M15.5 8.5 18 6a3.5 3.5 0 0 1 5 5l-2.5 2.5" transform="translate(-3 0) scale(.92)" />
            <path d="M8.5 15.5 6 18a3.5 3.5 0 0 1-5-5l2.5-2.5" transform="translate(3 0) scale(.92)" />
            <path d="m9 15 6-6" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="6.5" />
            <path d="m16 16 4 4" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('chevron-right')
            <path d="m9 5 7 7-7 7" />
            @break
        @case('x')
            <path d="m6 6 12 12M18 6 6 18" />
            @break
        @default
            <circle cx="12" cy="12" r="8" />
    @endswitch
</svg>
