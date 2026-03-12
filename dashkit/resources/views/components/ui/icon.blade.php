@props([
    'name' => 'sparkles',
    'lib' => 'hero',
    'size' => 'md',
    'faStyle' => 'solid',
])

@php
    $sizes = [
        'xs' => 'h-3.5 w-3.5',
        'sm' => 'h-4 w-4',
        'md' => 'h-5 w-5',
        'lg' => 'h-6 w-6',
    ];

    $svgSize = $sizes[$size] ?? $sizes['md'];
    $icon = strtolower(trim((string) $name));
    $library = strtolower(trim((string) $lib));
    $resolvedFaStyle = in_array($faStyle, ['solid', 'regular', 'brands'], true) ? $faStyle : 'solid';

    $aliases = [
        // Generic aliases
        'settings' => 'cog',
        'config' => 'cog',
        'email' => 'mail',
        'envelope' => 'mail',
        'dashboard' => 'home',
        'overview' => 'home',
        'chart' => 'reports',
        'analytics' => 'reports',
        'user' => 'profile',
        'users' => 'users',
        'profile' => 'profile',
        'search' => 'search',
        'notification' => 'bell',
        'notifications' => 'bell',
        'calendar' => 'calendar',
        'task' => 'tasks',
        'tasks' => 'tasks',
        'message' => 'message',
        'messages' => 'message',
        'support' => 'lifebuoy',
        'security' => 'shield',
        'billing' => 'wallet',
        'payment' => 'wallet',
        'payments' => 'wallet',
        // Preset aliases: ecommerce
        'products' => 'products',
        'product' => 'products',
        'orders' => 'orders',
        'order' => 'orders',
        'customers' => 'customers',
        'customer' => 'customers',
        'inventory' => 'inventory',
        // Preset aliases: crm
        'leads' => 'leads',
        'lead' => 'leads',
        'contacts' => 'contacts',
        'contact' => 'contacts',
        'deals' => 'deals',
        'deal' => 'deals',
        'activities' => 'activities',
        'activity' => 'activities',
        // Misc
        'logout' => 'logout',
        'login' => 'login',
        'reports' => 'reports',
        'sidebar' => 'sidebar',
        'layout' => 'layout',
        'sparkle' => 'sparkles',
    ];

    $icon = $aliases[$icon] ?? $icon;

    $heroIcons = [
        'home', 'cog', 'mail', 'layout', 'sidebar', 'search', 'sparkles', 'reports',
        'profile', 'users', 'products', 'orders', 'customers', 'inventory', 'leads',
        'contacts', 'deals', 'activities', 'bell', 'calendar', 'tasks', 'message',
        'wallet', 'shield', 'lifebuoy', 'logout', 'login', 'box',
    ];

    $hasHeroIcon = in_array($icon, $heroIcons, true);
@endphp

@if ($library === 'fa' || ($library === 'hero' && ! $hasHeroIcon))
    <i {{ $attributes->merge(['class' => 'fa-'.$resolvedFaStyle.' fa-'.$icon]) }} aria-hidden="true"></i>
@else
    @if ($icon === 'home')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M3.5 9.5L10 4l6.5 5.5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M5.5 8.8v7h9v-7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @elseif ($icon === 'cog')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M10 12.75a2.75 2.75 0 100-5.5 2.75 2.75 0 000 5.5z"/>
            <path d="M16 10a6.8 6.8 0 01-.05.83l1.6 1.25-1.5 2.58-1.93-.6a6.97 6.97 0 01-1.43.84l-.33 2.01h-3l-.33-2.01a6.97 6.97 0 01-1.43-.84l-1.93.6-1.5-2.58 1.6-1.25A6.8 6.8 0 014 10c0-.28.02-.56.05-.83L2.45 7.92l1.5-2.58 1.93.6c.44-.34.92-.62 1.43-.84l.33-2.01h3l.33 2.01c.51.22.99.5 1.43.84l1.93-.6 1.5 2.58-1.6 1.25c.03.27.05.55.05.83z" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @elseif ($icon === 'mail')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <rect x="3" y="5" width="14" height="10" rx="1.5"/>
            <path d="M3.5 6l6.5 5 6.5-5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @elseif ($icon === 'layout')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <rect x="3" y="4" width="14" height="12" rx="1.5"/>
            <path d="M8 4.5v11"/>
        </svg>
    @elseif ($icon === 'sidebar')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <rect x="3" y="4" width="14" height="12" rx="1.5"/>
            <path d="M7.2 4.5v11"/>
        </svg>
    @elseif ($icon === 'search')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <circle cx="9" cy="9" r="4.5"/>
            <path d="M12.5 12.5L16 16" stroke-linecap="round"/>
        </svg>
    @elseif ($icon === 'reports')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M4.5 15.5h11" stroke-linecap="round"/>
            <path d="M6.5 12.5V8.5m3.5 4V6m3.5 6.5V9.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @elseif ($icon === 'profile')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M10 10.25a3.25 3.25 0 100-6.5 3.25 3.25 0 000 6.5z"/>
            <path d="M4 16.25c.9-2.02 2.9-3.25 6-3.25s5.1 1.23 6 3.25" stroke-linecap="round"/>
        </svg>
    @elseif ($icon === 'users')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M7.5 10a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm5 1.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z"/>
            <path d="M2.9 15.5c.7-1.5 2.2-2.4 4.6-2.4s3.9.9 4.6 2.4M11.8 15.5c.45-.85 1.3-1.35 2.7-1.35 1.35 0 2.2.5 2.65 1.35" stroke-linecap="round"/>
        </svg>
    @elseif ($icon === 'products' || $icon === 'inventory' || $icon === 'box')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M10 3l6 3.5v7L10 17l-6-3.5v-7L10 3z" stroke-linejoin="round"/>
            <path d="M4 6.5l6 3.5 6-3.5" stroke-linejoin="round"/>
        </svg>
    @elseif ($icon === 'orders')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M3.5 5.5h13l-1.5 7.5h-10z" stroke-linejoin="round"/>
            <path d="M7.5 14.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm5 0a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/>
        </svg>
    @elseif ($icon === 'customers' || $icon === 'contacts')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M10 10.25a3.25 3.25 0 100-6.5 3.25 3.25 0 000 6.5z"/>
            <path d="M4 16.25c.9-2.02 2.9-3.25 6-3.25s5.1 1.23 6 3.25" stroke-linecap="round"/>
        </svg>
    @elseif ($icon === 'leads')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M10 3.5l1.4 3.1L14.5 8l-3.1 1.4L10 12.5 8.6 9.4 5.5 8l3.1-1.4L10 3.5z" stroke-linejoin="round"/>
            <path d="M6.3 16.2c.7-1.4 2-2.2 3.7-2.2s3 .8 3.7 2.2" stroke-linecap="round"/>
        </svg>
    @elseif ($icon === 'deals')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M3.5 6.5h8l5 5-4.8 4.8-5-5v-8z" stroke-linejoin="round"/>
            <circle cx="7" cy="8" r="1"/>
        </svg>
    @elseif ($icon === 'activities' || $icon === 'tasks')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M3.5 10h3l1.7-3 2.3 6 1.7-3h4.3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @elseif ($icon === 'bell')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M5.5 8.5a4.5 4.5 0 119 0v3l1.5 2H4l1.5-2v-3z" stroke-linejoin="round"/>
            <path d="M8 15.2a2 2 0 004 0" stroke-linecap="round"/>
        </svg>
    @elseif ($icon === 'calendar')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <rect x="3" y="4.5" width="14" height="12" rx="1.5"/>
            <path d="M6.5 3.5v2M13.5 3.5v2M3 8h14" stroke-linecap="round"/>
        </svg>
    @elseif ($icon === 'message')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M4 5.5h12v8H9l-4 3v-3H4z" stroke-linejoin="round"/>
        </svg>
    @elseif ($icon === 'wallet')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <rect x="3" y="6" width="14" height="9" rx="1.5"/>
            <path d="M13.5 10.5h3.5M3 8l10-2" stroke-linecap="round"/>
        </svg>
    @elseif ($icon === 'shield')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M10 3l5.5 2v4.5c0 3.2-2.2 5.8-5.5 7-3.3-1.2-5.5-3.8-5.5-7V5L10 3z" stroke-linejoin="round"/>
        </svg>
    @elseif ($icon === 'lifebuoy')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <circle cx="10" cy="10" r="6.5"/>
            <circle cx="10" cy="10" r="2.5"/>
            <path d="M6.1 6.1l2.1 2.1M13.9 6.1l-2.1 2.1M6.1 13.9l2.1-2.1M13.9 13.9l-2.1-2.1" stroke-linecap="round"/>
        </svg>
    @elseif ($icon === 'logout')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M8 4.5h-3.5v11H8" stroke-linecap="round"/>
            <path d="M11 10h6m0 0l-2-2m2 2l-2 2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @elseif ($icon === 'login')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M12 4.5h3.5v11H12" stroke-linecap="round"/>
            <path d="M9 10H3m0 0l2-2m-2 2l2 2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @elseif ($icon === 'sparkles')
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M10 3.5l1.4 3.1L14.5 8l-3.1 1.4L10 12.5 8.6 9.4 5.5 8l3.1-1.4L10 3.5z" stroke-linejoin="round"/>
            <path d="M15 12.5l.7 1.6 1.8.8-1.8.8-.7 1.6-.7-1.6-1.8-.8 1.8-.8.7-1.6zM4.5 11l.5 1.2 1.3.6-1.3.6-.5 1.2-.5-1.2-1.3-.6 1.3-.6.5-1.2z" stroke-linejoin="round"/>
        </svg>
    @else
        <svg {{ $attributes->merge(['class' => $svgSize]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M4.5 4.5h11v11h-11z" stroke-linejoin="round"/>
        </svg>
    @endif
@endif
