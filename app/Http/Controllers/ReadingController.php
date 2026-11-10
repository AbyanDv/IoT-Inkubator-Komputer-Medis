<?php

namespace App\Http\Controllers;

use App\Models\Baby;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    public function latest(Baby $baby)
    {
        return response()->json($baby->readings()->latest('recorded_at')->first());
    }

    /**
     * History, newest first. Filter with ?from=&to= (ISO dates) and cap with ?limit=.
     */
    public function index(Request $request, Baby $baby)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $readings = $baby->readings()
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('recorded_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('recorded_at', '<=', $to))
            ->latest('recorded_at')
            ->limit($filters['limit'] ?? 100)
            ->get();

        return response()->json($readings);
    }
}
