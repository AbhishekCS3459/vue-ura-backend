<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\Entities\PagePermission;

interface PagePermissionRepositoryInterface
{
    /**
     * @return array<PagePermission>
     */
    public function getAll(): array;

    public function findByKey(string $pageKey): ?PagePermission;

    public function findById(int $id): ?PagePermission;

    public function create(array $data): PagePermission;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    /**
     * @return array<string>
     */
    public function getAllowedPagesForRole(string $role): array;

    public function setRolePermission(string $role, int $pagePermissionId, bool $isAllowed): bool;
}
