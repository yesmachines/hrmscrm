<?php

namespace App\Http\Middleware;

use App\Support\SalesCrmRoles;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    use ApiResponse;

    /**
     * Handle an incoming request and verify the user has at least one of the required roles.
     *
     * Usage examples:
     *   ->middleware('role:hr')
     *   ->middleware('role:admin,hr')   // comma or pipe separators are supported
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        // Normalize roles (supports both 'role:admin,hr' and 'role:admin|hr')
        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => preg_split('/[,|]/', $role) ?: [])
            ->map(fn (string $role) => strtolower(trim($role)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Default to HR module roles if no specific role parameters were passed.
        if ($allowedRoles === []) {
            $allowedRoles = ['hr', 'admin', 'finance'];
        }

        $userId = (int) $user->id;

        // Check standard SalesCRM roles (e.g., 'admin', 'hr', 'finance', etc.)
        if (SalesCrmRoles::userHasAnyRole($userId, $allowedRoles)) {
            return $next($request);
        }

        return $this->errorResponse('Unauthorized. You do not have the required role to access this resource.', 403);
    }
}
