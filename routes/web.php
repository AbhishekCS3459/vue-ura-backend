<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api-docs', function () {
    return view('api-docs');
});

Route::get('/api-docs/vecura-api.yaml', function () {
    $path = storage_path('app/vecura-api.yaml');
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, [
        'Content-Type' => 'application/x-yaml',
    ]);
});

// Dashboard SPA - serve static files if exist, else index.html for client-side routing
Route::get('/dashboard/{path?}', function (?string $path = null) {
    $basePath = public_path('dashboard');
    $requestPath = $path ? "dashboard/{$path}" : 'dashboard';
    $filePath = $basePath . ($path ? "/{$path}" : '/index.html');

    if ($path && file_exists($filePath) && is_file($filePath)) {
        return response()->file($filePath);
    }
    $indexPath = $basePath . '/index.html';
    if (file_exists($indexPath)) {
        return response()->file($indexPath, ['Content-Type' => 'text/html']);
    }
    return redirect('/');
})->where('path', '.*');
