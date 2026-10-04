<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Concerns\HandlesTableQueries;
use App\Models\Batch;
use App\Models\Medicine;
use App\Services\ActivityLogger;
use App\Services\InventoryService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BatchController extends Controller
{
    use HandlesTableQueries;

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Batch-level inventory with expiry status.
     */
    public function index(Request $request): View
    {
        $alertDays = (int) settings('expiry_alert_days', 90);
        $query = Batch::query()->with(['medicine', 'supplier'])
            ->whereHas('medicine', fn ($q) => $q->search($request->string('q')->limit(80)->value()));

        if ($request->query('stock') !== 'all') {
            $query->inStock();
        }

        match ($request->query('expiry')) {
            'expired' => $query->expired(),
            'soon' => $query->expiringWithin($alertDays),
            'ok' => $query->whereDate('expiry_date', '>', today()->addDays($alertDays)),
            default => null,
        };

        $this->applySort($query, $request, [
            'expiry' => 'expiry_date',
            'quantity' => 'quantity',
            'received' => 'received_at',
        ], 'expiry', 'asc');

        return view('batches.index', [
            'batches' => $query->paginate($this->perPage($request))->withQueryString(),
            'alertDays' => $alertDays,
            'filters' => $request->only(['q', 'expiry', 'stock']),
        ]);
    }

    public function create(Medicine $medicine): View
    {
        return view('batches.create', ['medicine' => $medicine]);
    }

    /**
     * Opening stock for a medicine that is not coming from a purchase.
     */
    public function store(Request $request, Medicine $medicine): RedirectResponse
    {
        $data = $request->validate([
            'batch_number' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9.\/\-_]+$/'],
            'expiry_date' => ['required', 'date', 'after:today'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'supplier_id' => ['nullable', 'integer', TenantRule::exists('suppliers', true)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $batch = $this->inventory->receive($medicine, [
            'batch_number' => $data['batch_number'],
            'expiry_date' => $data['expiry_date'],
            'quantity' => (int) $data['quantity'],
            'purchase_price' => $data['purchase_price'],
            'selling_price' => $data['selling_price'] ?? $medicine->selling_price,
            'supplier_id' => $data['supplier_id'] ?? null,
        ], StockMovementType::Opening, null, $data['note'] ?? 'Opening stock');

        $this->logger->log('inventory.opening', "Opening stock {$batch->quantity} × {$medicine->name} (batch {$batch->batch_number})", $batch, [
            'medicine_id' => $medicine->id,
            'quantity' => $batch->quantity,
        ]);

        return redirect()->route('medicines.show', $medicine)->with('success', 'Stock added.');
    }

    public function adjust(Request $request, Batch $batch): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:add,remove'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $delta = $data['direction'] === 'add' ? (int) $data['quantity'] : -(int) $data['quantity'];

        DB::transaction(function () use ($batch, $delta, $data) {
            $this->inventory->adjust($batch, $delta, $data['reason']);
            $this->logger->log('inventory.adjusted', "Stock adjusted {$delta} on batch {$batch->batch_number} of {$batch->medicine->name}", $batch, [
                'medicine_id' => $batch->medicine_id,
                'delta' => $delta,
                'reason' => $data['reason'],
            ]);
        });

        return back()->with('success', 'Stock adjusted.');
    }

    public function writeOff(Request $request, Batch $batch): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        if ($batch->quantity <= 0) {
            throw new BusinessRuleException('This batch has no stock to write off.');
        }

        if (! $batch->isExpired()) {
            throw new BusinessRuleException('Only expired batches can be written off. Use a stock adjustment for other losses.');
        }

        $quantity = $batch->quantity;

        DB::transaction(function () use ($batch, $quantity, $data) {
            $this->inventory->removeFromBatch($batch, $quantity, StockMovementType::ExpiredWriteOff, null, $data['reason'] ?? 'Expired stock written off');
            $this->logger->log('inventory.write_off', "Wrote off {$quantity} expired units of {$batch->medicine->name} (batch {$batch->batch_number})", $batch, [
                'medicine_id' => $batch->medicine_id,
                'quantity' => $quantity,
            ]);
        });

        return back()->with('success', "{$quantity} expired units written off.");
    }
}
