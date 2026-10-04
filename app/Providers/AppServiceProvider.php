<?php

namespace App\Providers;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\Tenancy\CurrentPharmacy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentPharmacy::class);
        $this->app->scoped(SettingsService::class);
    }

    public function boot(): void
    {
        // Catch N+1 queries, silently discarded attributes and typos in development.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Store short aliases instead of class names in polymorphic columns.
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'medicine' => Medicine::class,
            'batch' => Batch::class,
            'category' => Category::class,
            'manufacturer' => Manufacturer::class,
            'customer' => Customer::class,
            'supplier' => Supplier::class,
            'purchase' => Purchase::class,
            'sale' => Sale::class,
            'sale_return' => SaleReturn::class,
            'payment' => Payment::class,
            'expense' => Expense::class,
            'setting' => Setting::class,
        ]);

        if ($this->app->isProduction()) {
            URL::forceHttps();
        }

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        Paginator::defaultView('components.pagination');

        $this->registerGates();
        $this->registerRateLimiters();
    }

    private function registerGates(): void
    {
        // Admins can do everything; inactive accounts can do nothing.
        Gate::before(function (User $user) {
            if (! $user->is_active) {
                return false;
            }

            return $user->isAdmin() ? true : null;
        });

        foreach (config('pharmacy.permissions') as $permissions) {
            foreach (array_keys($permissions) as $permission) {
                Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
            }
        }
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = mb_strtolower((string) $request->input('email')).'|'.$request->ip();

            return [Limit::perMinute(5)->by($key), Limit::perMinute(20)->by($request->ip())];
        });

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
    }
}
