@props(['type' => 'scheduled', 'label' => null])

@php
    $isChampionship = $type === 'championship';
    $badgeLabel = $label ?? ($isChampionship ? __('Championship') : __('Scheduled'));
    
    $baseClasses = "relative inline-flex items-center justify-center rounded-full font-bold select-none transition-all duration-300 transform hover:scale-110";
    $sizeClasses = "w-24 h-24 text-[10px]";
    
    // Premium Token Styles
    $tokenStyles = $isChampionship 
        ? "bg-gradient-to-br from-amber-300 via-yellow-500 to-amber-600 border-4 border-amber-200 shadow-[0_0_15px_rgba(245,158,11,0.5)] text-amber-950"
        : "bg-gradient-to-br from-slate-700 via-slate-900 to-black border-4 border-slate-600 shadow-[0_0_10px_rgba(0,0,0,0.5)] text-slate-100";
@endphp

<div {{ $attributes->merge(['class' => "$baseClasses $sizeClasses $tokenStyles"]) }}>
    <!-- Inner Ring -->
    <div class="absolute inset-1 border-[1px] border-dashed rounded-full pointer-events-none {{ $isChampionship ? 'border-amber-100/50' : 'border-slate-500/50' }}"></div>
    
    <!-- Token Marks (Poker Chip Style) -->
    <div class="absolute inset-0 flex items-center justify-center">
        @foreach([0, 45, 90, 135, 180, 225, 270, 315] as $angle)
            <div class="chip-mark chip-mark--{{ $angle }} {{ $isChampionship ? 'bg-amber-200/40' : 'bg-slate-400/30' }}"></div>
        @endforeach
    </div>

    <!-- Center Content -->
    <div class="relative z-10 flex flex-col items-center justify-center text-center px-2">
        @if($isChampionship)
            <svg class="w-6 h-6 mb-1" fill="currentColor" viewBox="0 0 24 24">
                <path d="M5 16L3 5L8.5 10L12 4L15.5 10L21 5L19 16H5M19 19C19 19.6 18.6 20 18 20H6C5.4 20 5 19.6 5 19V18H19V19Z" />
            </svg>
        @else
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        @endif
        <span class="uppercase tracking-tighter leading-none">{{ $badgeLabel }}</span>
    </div>
    
    <!-- Outer Glow (Hover) -->
    <div class="absolute inset-0 rounded-full opacity-0 hover:opacity-100 transition-opacity duration-300 {{ $isChampionship ? 'shadow-[0_0_25px_rgba(251,191,36,0.8)]' : 'shadow-[0_0_20px_rgba(99,102,241,0.4)]' }}"></div>
</div>

