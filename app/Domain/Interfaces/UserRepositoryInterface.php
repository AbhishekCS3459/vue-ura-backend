<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\Entities\User;

interface UserRepositoryInterface
{
    /**
     * @return array<User>
     */
    public function getAll(): array;

    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    public function create(array $data): User;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
