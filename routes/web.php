<?php

use App\Http\Controllers\FlashcardSetController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/flashcard-sets', [FlashcardSetController::class, 'store'])->name('flashcard-sets.store');
    Route::get('/flashcard-sets/{flashcardSet}', [FlashcardSetController::class, 'show'])->name('flashcard-sets.show');
});

require __DIR__.'/auth.php';
