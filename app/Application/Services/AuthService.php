<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\User;
use App\Domain\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $credentials
     * @return array{user: User, token: string}
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        $email = $credentials['email'] ?? '';
        $password = $credentials['password'] ?? '';

        if (empty($email) || empty($password)) {
            throw ValidationException::withMessages([
                'email' => ['The email field is required.'],
                'password' => ['The password field is required.'],
            ]);
        }

        $user = $this->userRepository->findByEmail($email);

        if ($user === null || !Hash::check($password, $this->getPasswordHash($user))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $this->createToken($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(int $userId): void
    {
        $model = \App\Models\User::find($userId);
        $model?->tokens()->delete();
    }

    private function getPasswordHash(User $user): string
    {
        $model = \App\Models\User::find($user->id);
        return $model?->password ?? '';
    }

    private function createToken(User $user): string
    {
        $model = \App\Models\User::find($user->id);
        if ($model === null) {
            throw new \RuntimeException('User model not found');
        }
        return $model->createToken('auth-token')->plainTextToken;
    }
}
