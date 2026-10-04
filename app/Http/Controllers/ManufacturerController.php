<?php

namespace App\Http\Controllers;

use App\Models\Manufacturer;
use App\Services\ActivityLogger;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManufacturerController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): View
    {
        return view('catalog.index', [
            'type' => 'manufacturers',
            'title' => 'Manufacturers',
            'items' => Manufacturer::query()
                ->withCount('medicines')
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.addcslashes($request->string('q')->limit(80)->value(), '%_\\').'%'))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $manufacturer = Manufacturer::query()->create($this->validated($request));
        $this->logger->log('manufacturer.created', "Manufacturer {$manufacturer->name} created", $manufacturer);

        return back()->with('success', 'Manufacturer added.');
    }

    public function update(Request $request, Manufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->fill($this->validated($request, $manufacturer))->save();
        $this->logger->log('manufacturer.updated', "Manufacturer {$manufacturer->name} updated", $manufacturer, $this->logger->changes($manufacturer));

        return back()->with('success', 'Manufacturer updated.');
    }

    public function destroy(Manufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->delete();
        $this->logger->log('manufacturer.archived', "Manufacturer {$manufacturer->name} archived", $manufacturer);

        return back()->with('success', 'Manufacturer archived.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Manufacturer $manufacturer = null): array
    {
        $request->merge(['is_active' => $request->boolean('is_active', true)]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150', TenantRule::unique('manufacturers', 'name')->ignore($manufacturer?->id)->whereNull('deleted_at')],
            'country' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
