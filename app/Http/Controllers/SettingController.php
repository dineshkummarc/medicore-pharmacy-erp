<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Services\ActivityLogger;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActivityLogger $logger,
    ) {}

    public function edit(): View
    {
        return view('settings.edit', [
            'settings' => $this->settings->all(),
            'methods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'store_tagline' => ['nullable', 'string', 'max:160'],
            'store_address' => ['nullable', 'string', 'max:500'],
            'store_phone' => ['nullable', 'string', 'max:40'],
            'store_email' => ['nullable', 'email', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:80'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            'currency_code' => ['required', 'string', 'size:3', 'alpha'],
            'invoice_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'purchase_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'return_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'tax_enabled' => ['nullable', 'boolean'],
            'tax_label' => ['required', 'string', 'max:20'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000'],
            'expiry_alert_days' => ['required', 'integer', 'min:1', 'max:730'],
            'max_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice_footer' => ['nullable', 'string', 'max:500'],
            // Raster images only: SVG can carry scripts.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $data['tax_enabled'] = $request->boolean('tax_enabled') ? '1' : '0';
        $data['currency_code'] = strtoupper($data['currency_code']);
        $current = $this->settings->get('store_logo');

        if ($request->hasFile('logo')) {
            $data['store_logo'] = $request->file('logo')->store('logos', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $data['store_logo'] = null;
        }

        if (array_key_exists('store_logo', $data) && $current) {
            Storage::disk('public')->delete($current);
        }

        unset($data['logo'], $data['remove_logo']);
        $this->settings->put($data);
        $this->logger->log('settings.updated', 'Store settings updated', null, ['keys' => array_keys($data)]);

        return back()->with('success', 'Settings saved.');
    }
}
