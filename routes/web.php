<?php

use App\Http\Controllers\BoardController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Route::get('boards', [BoardController::class, 'index'])->name('boards.index');
        Route::post('boards', [BoardController::class, 'store'])->name('boards.store');
        Route::get('boards/{board}', [BoardController::class, 'show'])->name('boards.show');
        Route::patch('boards/{board}', [BoardController::class, 'update'])->name('boards.update');
        Route::delete('boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');
        Route::post('boards/{board}/archive', [BoardController::class, 'archive'])->name('boards.archive');
        Route::post('boards/{board}/restore', [BoardController::class, 'restore'])->name('boards.restore');
    });

require __DIR__.'/settings.php';
