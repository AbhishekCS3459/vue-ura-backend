<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\AuthService;
use App\Domain\Entities\User;
use App\Infrastructure\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->authService->login($request->only(['email', 'password']));

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $result['user']->id,
                        'name' => $result['user']->name,
                        'email' => $result['user']->email,
                        'role' => $result['user']->role,
                        'branch_id' => $result['user']->branchId,
                    ],
                    'token' => $result['token'],
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
                'errors' => $e->errors(),
            ], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $this->getCurrentUser($request);
        $this->authService->logout($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'string', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['role'] = 'staff'; // All new users are staff by default
            $data['password'] = $data['password']; // Will be hashed in repository

            $repository = new UserRepository();
            $user = $repository->create($data);

            $token = $this->createTokenForUser($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'branch_id' => $user->branchId,
                    ],
                    'token' => $token,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->getCurrentUser($request);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'branch_id' => $user->branchId,
            ],
        ]);
    }

    private function createTokenForUser(int $userId): string
    {
        $model = \App\Models\User::find($userId);
        if ($model === null) {
            throw new \RuntimeException('User model not found');
        }
        return $model->createToken('auth-token')->plainTextToken;
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
