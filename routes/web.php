<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BookController::class, 'index'])
    ->name('home');

Route::resource('books', BookController::class)
    ->except(['index', 'show'])
    ->middleware('auth');

Route::resource('books', BookController::class)
    ->only(['index', 'show']);

Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])
    ->name('reviews.store')
    ->middleware('auth');

Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])
    ->name('reviews.edit')
    ->middleware('auth');

Route::put('/reviews/{review}', [ReviewController::class, 'update'])
    ->name('reviews.update')
    ->middleware('auth');

Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])
    ->name('reviews.destroy')
    ->middleware('auth');

Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])
    ->name('reviews.like')
    ->middleware('auth');

Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])
    ->name('favorites.toggle')
    ->middleware('auth');

Route::get('/favorites', [FavoriteController::class, 'index'])
    ->name('favorites.index')
    ->middleware('auth');

Route::resource('genres', GenreController::class)
    ->middleware('auth');

Route::get('/ranking', [RankingController::class, 'index'])
    ->name('ranking.index');
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
