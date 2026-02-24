<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class TournamentBadge extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $type = 'scheduled',
        public ?string $label = null
    ) {
        if (!$this->label) {
            $this->label = $this->type === 'championship' ? 'Championship' : 'Scheduled';
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.tournament-badge');
    }
}
