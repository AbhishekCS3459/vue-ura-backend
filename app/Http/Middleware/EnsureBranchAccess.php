<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Entities\User;
use App\Infrastructure\Repositories\UserRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $authUser = $request->user();

        if ($authUser === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $repository = new UserRepository();
        $user = $repository->findById($authUser->id);

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $branchId = $request->input('branch_id') ?? $request->route('branch_id');

        if ($branchId !== null && !$user->canAccessBranch((int) $branchId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this branch',
            ], 403);
        }

        $request->merge(['current_user' => $user]);

        return $next($request);
    }
}
