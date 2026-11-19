@props(['status' => 'offline', 'label' => null, 'dot' => true])

@php
    $state = strtolower((string) $status);
    $tone = match ($state) {
        'online', 'active', 'paired', 'ok', 'applied' => 'ok',
        'warning', 'stale', 'pending', 'awaiting' => 'warn',
        'error', 'fault', 'danger', 'disconnected' => 'danger',
        default => 'neutral',
    };
    $text = $label ?? ucfirst($state);
@endphp

<span {{ $attributes->class(['badge', 'badge-'.$tone]) }}>
    @if ($dot)
        <span class="status-dot" aria-hidden="true"></span>
    @endif
    <span data-status-text>{{ $text }}</span>
</span>
