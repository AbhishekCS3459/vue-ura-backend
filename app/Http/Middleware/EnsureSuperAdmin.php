<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $dbUser = \App\Models\User::find($user->id);
        if ($dbUser === null || $dbUser->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can perform this action',
            ], 403);
        }

        return $next($request);
    }
}
