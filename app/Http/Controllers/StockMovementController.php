<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Http\Controllers\Concerns\HandlesTableQueries;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    use HandlesTableQueries;

    public function index(Request $request): View
    {
        $request->validate(['type' => ['nullable', Rule::enum(StockMovementType::class)]]);
        [$from, $to] = $this->dateRange($request);

        $query = StockMovement::query()
            ->with(['medicine', 'batch', 'user'])
            ->when($request->filled('q'), fn ($q) => $q->whereHas('medicine', fn ($m) => $m->search($request->string('q')->limit(80)->value())))
            ->when($request->integer('medicine'), fn ($q, $id) => $q->where('medicine_id', $id))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest('id');

        return view('stock-movements.index', [
            'movements' => $query->paginate($this->perPage($request))->withQueryString(),
            'types' => StockMovementType::cases(),
            'filters' => $request->only(['q', 'type', 'from', 'to', 'medicine']),
        ]);
    }
}
