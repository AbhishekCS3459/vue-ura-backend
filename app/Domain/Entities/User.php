<?php

declare(strict_types=1);

namespace App\Domain\Entities;

final readonly class User
{
    /**
     * @param array<string>|null $pagePermissions
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public ?int $branchId = null,
        public ?array $pagePermissions = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (!in_array($this->role, ['super_admin', 'branch_manager', 'staff'], true)) {
            throw new \InvalidArgumentException('Invalid user role');
        }

        if ($this->role !== 'super_admin' && $this->branchId === null) {
            throw new \InvalidArgumentException('Branch ID is required for non-super-admin users');
        }
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function canAccessBranch(?int $branchId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->branchId === $branchId;
    }
}
