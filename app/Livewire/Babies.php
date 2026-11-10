<?php

namespace App\Livewire;

use App\Models\Baby;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Bayi')]
class Babies extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $weight_grams = '';

    public bool $premature = false;

    public ?string $status = null;

    public function rules(): array
    {
        return Baby::rules();
    }

    public function save()
    {
        $data = $this->validate();
        $data['weight_grams'] = (int) $data['weight_grams'];

        if ($this->editingId) {
            Baby::findOrFail($this->editingId)->update($data);
            $this->status = 'Data bayi diperbarui.';
        } else {
            Baby::create($data);
            $this->status = 'Bayi ditambahkan.';
        }

        $this->reset('editingId', 'name', 'weight_grams', 'premature');
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $baby = Baby::findOrFail($id);

        $this->editingId = $baby->id;
        $this->name = $baby->name;
        $this->weight_grams = (string) $baby->weight_grams;
        $this->premature = $baby->premature;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'weight_grams', 'premature');
        $this->resetValidation();
    }

    public function activate(int $id): void
    {
        Baby::findOrFail($id)->activate();
        $this->status = 'Bayi diaktifkan di inkubator.';
    }

    public function deactivate(int $id): void
    {
        Baby::findOrFail($id)->deactivate();
        $this->status = 'Sesi inkubator diakhiri.';
    }

    public function delete(int $id): void
    {
        Baby::findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->cancel();
        }

        $this->status = 'Bayi dihapus.';
    }

    public function render()
    {
        return view('livewire.babies', [
            'babies' => Baby::latest()->get(),
            'activeBaby' => Baby::active(),
        ]);
    }
}
