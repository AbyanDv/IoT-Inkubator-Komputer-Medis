<?php

namespace App\Livewire;

use App\Models\Baby;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Riwayat')]
class History extends Component
{
    use WithPagination;

    public ?int $babyId = null;

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->babyId = Baby::active()?->id ?? Baby::latest()->value('id');
        $this->from = now()->subDay()->format('Y-m-d\TH:i');
        $this->to = now()->format('Y-m-d\TH:i');
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['babyId', 'from', 'to'], true)) {
            return;
        }

        $this->resetPage();

        $this->validate([
            'babyId' => ['nullable', 'integer', 'exists:babies,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    public function resetFilters(): void
    {
        $this->from = now()->subDay()->format('Y-m-d\TH:i');
        $this->to = now()->format('Y-m-d\TH:i');
        $this->resetPage();
    }

    public function render()
    {
        $baby = $this->babyId ? Baby::find($this->babyId) : null;

        // Ignore unparsable dates so a bad input cannot break the query.
        $from = $this->from !== '' && strtotime($this->from) ? $this->from : null;
        $to = $this->to !== '' && strtotime($this->to) ? $this->to : null;

        $readings = $baby?->readings()
            ->when($from, fn ($q) => $q->where('recorded_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('recorded_at', '<=', $to))
            ->latest('recorded_at')
            ->paginate(25);

        return view('livewire.history', [
            'babies' => Baby::latest()->get(),
            'baby' => $baby,
            'readings' => $readings,
        ]);
    }
}
