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

    public function updatedBabyId(): void
    {
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->from = now()->subDay()->format('Y-m-d\TH:i');
        $this->to = now()->format('Y-m-d\TH:i');
        $this->resetPage();
    }

    public function render()
    {
        $this->validate([
            'babyId' => ['nullable', 'integer', 'exists:babies,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $baby = $this->babyId ? Baby::find($this->babyId) : null;

        $readings = $baby?->readings()
            ->when($this->from, fn ($q) => $q->where('recorded_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->where('recorded_at', '<=', $this->to))
            ->latest('recorded_at')
            ->paginate(25);

        return view('livewire.history', [
            'babies' => Baby::latest()->get(),
            'baby' => $baby,
            'readings' => $readings,
        ]);
    }
}
