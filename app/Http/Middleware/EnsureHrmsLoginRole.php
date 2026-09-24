<?php

namespace App\Http\Middleware;

use App\Support\SalesCrmRoles;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureHrmsLoginRole
{
    /**
     * Only Sales CRM users with admin, hr, or finance roles may use HRMS CRM.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! SalesCrmRoles::userHasLoginAccess((int) $user->id)) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('Only Admin, HR, and Finance users can access this application.'),
                ]);
        }

        return $next($request);
    }
}
