<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\ActivityLogger;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentMethodController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', TenantRule::unique('payment_methods', 'name')],
        ]);

        $code = Str::slug($data['name'], '_');
        $method = PaymentMethod::query()->create([
            'name' => $data['name'],
            'code' => PaymentMethod::query()->where('code', $code)->exists() ? $code.'_'.Str::lower(Str::random(4)) : $code,
            'is_active' => true,
            'sort_order' => (int) PaymentMethod::query()->max('sort_order') + 1,
        ]);

        $this->logger->log('payment_method.created', "Payment method {$method->name} added", null, ['name' => $method->name]);

        return back()->with('success', 'Payment method added.');
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', TenantRule::unique('payment_methods', 'name')->ignore($paymentMethod->id)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paymentMethod->fill(['name' => $data['name'], 'is_active' => $request->boolean('is_active')])->save();

        if (! PaymentMethod::query()->where('is_active', true)->exists()) {
            $paymentMethod->forceFill(['is_active' => true])->save();

            return back()->with('error', 'At least one payment method must stay active.');
        }

        return back()->with('success', 'Payment method updated.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $used = Payment::query()->where('payment_method_id', $paymentMethod->id)->exists()
            || Expense::withTrashed()->where('payment_method_id', $paymentMethod->id)->exists();

        if ($used) {
            return back()->with('error', 'This method has transactions; deactivate it instead of deleting.');
        }

        if (PaymentMethod::query()->where('is_active', true)->whereKeyNot($paymentMethod->id)->doesntExist()) {
            return back()->with('error', 'At least one payment method must remain.');
        }

        $paymentMethod->delete();

        return back()->with('success', 'Payment method removed.');
    }
}
