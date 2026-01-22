<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\IAMService;
use App\Domain\Entities\User;
use App\Infrastructure\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class IAMController extends Controller
{
    public function __construct(
        private readonly IAMService $iamService,
    ) {
    }

    public function getUsers(Request $request): JsonResponse
    {
        $currentUser = $this->getCurrentUser($request);
        $users = $this->iamService->getAllUsers($currentUser);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function createUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'string', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:super_admin,branch_manager,staff'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $currentUser = $this->getCurrentUser($request);
            $user = $this->iamService->createUser($currentUser, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'branch_id' => $user->branchId,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['sometimes', 'string', 'in:super_admin,branch_manager,staff'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'page_permissions' => ['sometimes', 'array'],
            'page_permissions.*' => ['string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $currentUser = $this->getCurrentUser($request);
            $success = $this->iamService->updateUser($currentUser, $id, $validator->validated());

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function deleteUser(Request $request, int $id): JsonResponse
    {
        try {
            $currentUser = $this->getCurrentUser($request);
            $success = $this->iamService->deleteUser($currentUser, $id);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function getPagePermissions(Request $request): JsonResponse
    {
        $permissions = $this->iamService->getAllPagePermissions();

        return response()->json([
            'success' => true,
            'data' => array_map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'page_key' => $permission->pageKey,
                    'page_name' => $permission->pageName,
                    'description' => $permission->description,
                ];
            }, $permissions),
        ]);
    }

    public function getRolePermissions(Request $request, string $role): JsonResponse
    {
        $pages = $this->iamService->getAllowedPagesForRole($role);

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'allowed_pages' => $pages,
            ],
        ]);
    }

    public function setRolePermission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => ['required', 'string', 'in:super_admin,branch_manager,staff'],
            'page_permission_id' => ['required', 'integer', 'exists:page_permissions,id'],
            'is_allowed' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $success = $this->iamService->setRolePagePermission(
            $data['role'],
            $data['page_permission_id'],
            $data['is_allowed']
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Permission updated successfully' : 'Failed to update permission',
        ]);
    }

    private function getCurrentUser(Request $request): User
    {
        $authUser = $request->user();
        $repository = new UserRepository();

        $user = $repository->findById($authUser->id);

        if ($user === null) {
            throw new \RuntimeException('User not found');
        }

        return $user;
    }
}
