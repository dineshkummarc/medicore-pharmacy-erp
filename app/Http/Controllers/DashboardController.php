<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Landing page: the dashboard, or the most useful screen the user may open.
     */
    public function home(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        foreach (['dashboard.view' => 'dashboard', 'sales.create' => 'pos.index', 'medicines.view' => 'medicines.index', 'sales.view' => 'sales.index'] as $permission => $route) {
            if ($user->can($permission)) {
                return redirect()->route($route);
            }
        }

        return view('welcome-staff');
    }

    public function index(Request $request, ReportService $reports): View
    {
        $user = $request->user();
        $today = [today()->startOfDay(), today()->endOfDay()];
        $month = [today()->startOfMonth(), today()->endOfDay()];
        $lastMonth = [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()];
        $alertDays = (int) settings('expiry_alert_days', 90);

        $todaySummary = $reports->salesSummary(...$today);
        $monthSummary = $reports->salesSummary(...$month);
        $lastMonthNet = $reports->salesSummary(...$lastMonth)['net'];
        $showFinancials = $user->can('reports.financial');

        return view('dashboard', [
            'today' => $todaySummary,
            'month' => $monthSummary,
            'monthChange' => $lastMonthNet > 0 ? ($monthSummary['net'] - $lastMonthNet) / $lastMonthNet * 100 : null,
            'pnl' => $showFinancials ? $reports->profitAndLoss(...$month) : null,
            'inventory' => $showFinancials ? $reports->inventoryValuation() : null,
            'trend' => $reports->salesTrend(today()->subDays(29)->startOfDay(), today()->endOfDay()),
            'topMedicines' => $reports->salesByMedicine(today()->subDays(29)->startOfDay(), today()->endOfDay(), 5),
            'paymentMix' => $reports->paymentMethods(...$month),
            'counts' => [
                'medicines' => Medicine::query()->count(),
                'active' => Medicine::query()->active()->count(),
                'low' => Medicine::query()->active()->lowStock()->count(),
                'out' => Medicine::query()->active()->where('stock_quantity', '<=', 0)->count(),
                'expiring' => Batch::query()->inStock()->expiringWithin($alertDays)->count(),
                'expired' => Batch::query()->inStock()->expired()->count(),
            ],
            'receivable' => (float) Customer::query()->where('balance', '>', 0)->sum('balance'),
            'payable' => (float) Supplier::query()->where('balance', '>', 0)->sum('balance'),
            'expensesMonth' => $reports->expensesTotal(...$month),
            'recentSales' => Sale::query()->with(['customer', 'user'])->latest('sold_at')->limit(6)->get(),
            'recentPurchases' => $user->can('purchases.view')
                ? Purchase::query()->with('supplier')->latest('purchase_date')->latest('id')->limit(5)->get()
                : collect(),
            'lowStock' => Medicine::query()->active()->lowStock()->orderBy('stock_quantity')->limit(6)->get(['id', 'name', 'strength', 'stock_quantity', 'min_stock_level', 'unit']),
            'expiringBatches' => Batch::query()->with('medicine')->inStock()->expiringWithin($alertDays)->orderBy('expiry_date')->limit(6)->get(),
            'alertDays' => $alertDays,
        ]);
    }
}
