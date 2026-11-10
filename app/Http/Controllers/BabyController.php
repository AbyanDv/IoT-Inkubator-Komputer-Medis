<?php

namespace App\Http\Controllers;

use App\Models\Baby;
use Illuminate\Http\Request;

class BabyController extends Controller
{
    public function index()
    {
        return response()->json(Baby::latest()->get());
    }

    public function store(Request $request)
    {
        $baby = Baby::create($this->validated($request));

        return response()->json($baby, 201);
    }

    public function show(Baby $baby)
    {
        return response()->json($baby);
    }

    public function update(Request $request, Baby $baby)
    {
        $baby->update($this->validated($request));

        return response()->json($baby);
    }

    public function destroy(Baby $baby)
    {
        $baby->delete();

        return response()->json(['message' => 'Baby deleted.']);
    }

    /**
     * Put a baby in the incubator. Any previous occupant is released first.
     */
    public function activate(Baby $baby)
    {
        $baby->activate();

        return response()->json($baby->fresh());
    }

    public function deactivate(Baby $baby)
    {
        $baby->deactivate();

        return response()->json($baby->fresh());
    }

    protected function validated(Request $request): array
    {
        return $request->validate(Baby::rules());
    }
}
