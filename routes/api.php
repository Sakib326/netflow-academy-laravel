<?php


// routes/api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * @OA\Get(
 *     path="/api/health",
 *     summary="Health check",
 *     @OA\Response(response=200, description="API is working")
 * )
 */
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
