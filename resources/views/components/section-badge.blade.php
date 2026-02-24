@props(['type' => 'default', 'label' => null])

@php
    $config = [
        'mission' => [
            'label' => 'Our Mission',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.962 14.962 0 01-10.32 1.51m10.32-1.51l-1.44-1.44m-2.58 2.58L11 15.59m-4.54-4.54a14.95 14.95 0 01-1.51-10.32 14.96 14.96 0 0112.12 6.16m-10.61 4.16l-1.44-1.44" />',
            'color' => 'from-amber-400 to-yellow-600',
        ],
        'sponsor' => [
            'label' => 'Become a Sponsor',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />',
            'color' => 'from-rose-400 to-pink-600',
        ],
        'tournament-rules' => [
            'label' => 'Tournament Rules',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />',
            'color' => 'from-blue-400 to-indigo-600',
        ],
        'final-rules' => [
            'label' => 'Final Game Rules',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-1.533-3.007A3.75 3.75 0 0012 18z" />',
            'color' => 'from-orange-400 to-red-600',
        ],
        'betting' => [
            'label' => 'Betting Rules',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
            'color' => 'from-emerald-400 to-green-600',
        ],
        'behaviour' => [
            'label' => 'Behaviour Rules',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6.223c-.235 4.567 1.24 8.795 4.003 12.1 2.764 3.305 6.758 5.504 11.262 5.504a11.956 11.956 0 005.132-1.156M12 2.25c4.954 0 9.26 3.008 11.132 7.294" />',
            'color' => 'from-cyan-400 to-blue-600',
        ],
        'texas-holdem' => [
            'label' => "Texas Hold'em Rules",
            'icon' => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14l-5-4.87 6.91-1.01L12 2z" />',
            'color' => 'from-amber-400 to-yellow-600',
        ],
        'contact' => [
            'label' => 'Contact Us',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />',
            'color' => 'from-indigo-400 to-purple-600',
        ],
        'default' => [
            'label' => 'Information',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />',
            'color' => 'from-slate-400 to-slate-600',
        ]
    ];

    $activeConfig = $config[$type] ?? $config['default'];
    $displayLabel = $label ?? $activeConfig['label'];
    $gradientColor = $activeConfig['color'];
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-white/10 dark:bg-black/20 border border-white/20 dark:border-white/10 backdrop-blur-sm shadow-sm transition-all duration-300 hover:shadow-md hover:bg-white/20 dark:hover:bg-black/30 group']) }}>
    <div class="flex items-center justify-center w-6 h-6 rounded-full bg-gradient-to-br {{ $gradientColor }} text-white shadow-sm transition-transform duration-300 group-hover:scale-110 group-hover:rotate-12">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            {!! $activeConfig['icon'] !!}
        </svg>
    </div>
    <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 transition-colors duration-300 group-hover:text-gray-900 dark:group-hover:text-white">
        {{ $displayLabel }}
    </span>
</div>
