<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/books', [BookController::class, 'index']);
    Route::post('/books', [BookController::class, 'store']);

    Route::get('/books/{book}', [BookController::class, 'show'])
        ->missing(fn () => response()->json(['message' => '書籍が見つかりません。'], 404));
    Route::put('/books/{book}', [BookController::class, 'update'])
        ->missing(fn () => response()->json(['message' => '書籍が見つかりません。'], 404));
    Route::delete('/books/{book}', [BookController::class, 'destroy'])
        ->missing(fn () => response()->json(['message' => '書籍が見つかりません。'], 404));
});
