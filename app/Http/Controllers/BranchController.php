<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\JsonResponse;

final class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        $branches = Branch::select('id', 'name', 'city')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $branches->map(function ($branch) {
                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'city' => $branch->city,
                ];
            }),
        ]);
    }
}
