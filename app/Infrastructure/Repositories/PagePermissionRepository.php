<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\PagePermission;
use App\Domain\Interfaces\PagePermissionRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class PagePermissionRepository implements PagePermissionRepositoryInterface
{
    public function getAll(): array
    {
        $models = DB::table('page_permissions')->get();

        return $models->map(function ($model) {
            return $this->mapToEntity($model);
        })->toArray();
    }

    public function findByKey(string $pageKey): ?PagePermission
    {
        $model = DB::table('page_permissions')
            ->where('page_key', $pageKey)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->mapToEntity($model);
    }

    public function findById(int $id): ?PagePermission
    {
        $model = DB::table('page_permissions')->find($id);

        if ($model === null) {
            return null;
        }

        return $this->mapToEntity($model);
    }

    public function create(array $data): PagePermission
    {
        $id = DB::table('page_permissions')->insertGetId([
            'page_key' => $data['page_key'],
            'page_name' => $data['page_name'],
            'description' => $data['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findById($id);
    }

    public function update(int $id, array $data): bool
    {
        return DB::table('page_permissions')
            ->where('id', $id)
            ->update([
                'page_key' => $data['page_key'] ?? null,
                'page_name' => $data['page_name'] ?? null,
                'description' => $data['description'] ?? null,
                'updated_at' => now(),
            ]) > 0;
    }

    public function delete(int $id): bool
    {
        return DB::table('page_permissions')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * @return array<string>
     */
    public function getAllowedPagesForRole(string $role): array
    {
        $pages = DB::table('role_page_permissions')
            ->join('page_permissions', 'role_page_permissions.page_permission_id', '=', 'page_permissions.id')
            ->where('role_page_permissions.role', $role)
            ->where('role_page_permissions.is_allowed', true)
            ->pluck('page_permissions.page_key')
            ->toArray();

        return array_map('strval', $pages);
    }

    public function setRolePermission(string $role, int $pagePermissionId, bool $isAllowed): bool
    {
        return DB::table('role_page_permissions')
            ->updateOrInsert(
                [
                    'role' => $role,
                    'page_permission_id' => $pagePermissionId,
                ],
                [
                    'is_allowed' => $isAllowed,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
    }

    private function mapToEntity(object $model): PagePermission
    {
        return new PagePermission(
            id: (int) $model->id,
            pageKey: (string) $model->page_key,
            pageName: (string) $model->page_name,
            description: $model->description ?? null,
        );
    }
}
