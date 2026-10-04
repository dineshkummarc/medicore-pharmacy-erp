<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentPharmacy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's pharmacy as the tenant for this request.
 */
class SetCurrentPharmacy
{
    public function __construct(private readonly CurrentPharmacy $pharmacy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $this->pharmacy->set($user->pharmacy_id);
        }

        return $next($request);
    }
}
