<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Baby;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View as ViewFacade;

class BabyController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:active,completed'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $babies = Baby::query()
            ->with('currentIncubator')
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('baby_code', 'like', '%'.$search.'%')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return ViewFacade::make('babies.index', compact('babies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        /** @var User|null $user */
        $user = Auth::user();
        $baby = Baby::create([...$validated, 'created_by' => $user?->id, 'status' => 'active']);

        return redirect()->route('babies.show', $baby)->with('status', 'Baby created.');
    }

    public function show(Baby $baby): View
    {
        $baby->load(['devicePairings.incubator', 'telemetries' => fn ($query) => $query->latest('recorded_at')->take(100)]);

        return ViewFacade::make('babies.show', ['baby' => $baby, 'history' => $baby->telemetries->reverse()->values()]);
    }

    public function update(Request $request, Baby $baby): RedirectResponse
    {
        $baby->update($request->validate($this->rules($baby)));

        return redirect()->route('babies.show', $baby)->with('status', 'Baby updated.');
    }

    public function destroy(Request $request, Baby $baby): RedirectResponse
    {
        DB::transaction(function () use ($request, $baby): void {
            $baby = Baby::query()->lockForUpdate()->findOrFail($baby->id);
            $pairing = $baby->currentPairing()->lockForUpdate()->first();
            if ($pairing) {
                $pairing->update(['status' => 'completed', 'unpaired_at' => Carbon::now()]);
                $pairing->incubator()->update(['current_baby_id' => null]);
            }
            $baby->update(['status' => 'completed']);
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'baby.completed',
                'target_type' => Baby::class,
                'target_id' => $baby->id,
                'details' => [],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('babies.index')->with('status', 'Baby marked completed.');
    }

    public function deactivate(Request $request, Baby $baby): RedirectResponse
    {
        return $this->destroy($request, $baby);
    }

    /** @return array<string, array<int, string>> */
    private function rules(?Baby $baby = null): array
    {
        return [
            'baby_code' => ['required', 'string', 'max:255', 'unique:babies,baby_code'.($baby ? ','.$baby->id : '')],
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'gestational_age_weeks' => ['required', 'integer', 'between:20,45'],
            'weight_grams' => ['required', 'integer', 'min:100', 'max:10000'],
            'length_cm' => ['required', 'numeric', 'between:10,100'],
            'gender' => ['required', 'in:male,female'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
