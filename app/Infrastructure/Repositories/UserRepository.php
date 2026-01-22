<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\User;
use App\Domain\Interfaces\UserRepositoryInterface;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\Hash;

final class UserRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        $model = UserModel::where('email', $email)->first();

        if ($model === null) {
            return null;
        }

        return $this->mapToEntity($model);
    }

    public function findById(int $id): ?User
    {
        $model = UserModel::find($id);

        if ($model === null) {
            return null;
        }

        return $this->mapToEntity($model);
    }

    public function create(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $model = UserModel::create($data);

        return $this->mapToEntity($model);
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $model = UserModel::find($id);

        if ($model === null) {
            return false;
        }

        return $model->update($data);
    }

    public function delete(int $id): bool
    {
        $model = UserModel::find($id);

        if ($model === null) {
            return false;
        }

        return $model->delete();
    }

    /**
     * @return array<User>
     */
    public function getAll(): array
    {
        $models = UserModel::all();

        return $models->map(function (UserModel $model) {
            return $this->mapToEntity($model);
        })->toArray();
    }

    private function mapToEntity(UserModel $model): User
    {
        return new User(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            role: $model->role,
            branchId: $model->branch_id,
            pagePermissions: $model->page_permissions,
        );
    }
}
