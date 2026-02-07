<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\PagePermission;
use App\Domain\Entities\User;
use App\Domain\Interfaces\PagePermissionRepositoryInterface;
use App\Domain\Interfaces\UserRepositoryInterface;

final class IAMService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PagePermissionRepositoryInterface $pagePermissionRepository,
    ) {
    }

    /**
     * @return array<array{id: int, name: string, email: string, role: string, branch_id: int|null, page_permissions: array<string>|null}>
     */
    public function getAllUsers(User $currentUser): array
    {
        $users = $this->userRepository->getAll();

        $filteredUsers = array_filter($users, function (User $user) use ($currentUser) {
            return $currentUser->canAccessBranch($user->branchId);
        });

        return array_map(function (User $user) {
            // If user has custom page_permissions, use them; otherwise use role-based permissions
            $pagePermissions = $user->pagePermissions;
            if ($pagePermissions === null || $pagePermissions === []) {
                $pagePermissions = $this->pagePermissionRepository->getAllowedPagesForRole($user->role);
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'branch_id' => $user->branchId,
                'page_permissions' => $pagePermissions,
            ];
        }, $filteredUsers);
    }

    public function createUser(User $currentUser, array $data): User
    {
        $role = $data['role'] ?? 'staff';
        if (in_array($role, ['branch_manager', 'staff'], true) && empty($data['branch_id'])) {
            throw new \RuntimeException('Branch allocation is required for branch managers and staff');
        }

        if (!$currentUser->isSuperAdmin() && !$currentUser->canAccessBranch($data['branch_id'] ?? null)) {
            throw new \RuntimeException('You do not have permission to create users for this branch');
        }

        return $this->userRepository->create($data);
    }

    public function updateUser(User $currentUser, int $userId, array $data): bool
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            return false;
        }

        // Only super admins can change user roles
        if (isset($data['role']) && !$currentUser->isSuperAdmin()) {
            throw new \RuntimeException('Only super admins can change user roles');
        }

        // Check branch access permission
        if (!$currentUser->canAccessBranch($user->branchId)) {
            throw new \RuntimeException('You do not have permission to update this user');
        }

        // If changing role, ensure only super admin can do it
        if (isset($data['role']) && $data['role'] !== $user->role && !$currentUser->isSuperAdmin()) {
            throw new \RuntimeException('Only super admins can change user roles');
        }

        return $this->userRepository->update($userId, $data);
    }

    public function deleteUser(User $currentUser, int $userId): bool
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            return false;
        }

        if (!$currentUser->canAccessBranch($user->branchId)) {
            throw new \RuntimeException('You do not have permission to delete this user');
        }

        return $this->userRepository->delete($userId);
    }

    /**
     * @return array<PagePermission>
     */
    public function getAllPagePermissions(): array
    {
        return $this->pagePermissionRepository->getAll();
    }

    /**
     * @return array<string>
     */
    public function getAllowedPagesForRole(string $role): array
    {
        return $this->pagePermissionRepository->getAllowedPagesForRole($role);
    }

    public function setRolePagePermission(string $role, int $pagePermissionId, bool $isAllowed): bool
    {
        return $this->pagePermissionRepository->setRolePermission($role, $pagePermissionId, $isAllowed);
    }
}
