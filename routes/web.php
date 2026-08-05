<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardListController;
use App\Http\Controllers\BoardMemberController;
use App\Http\Controllers\CardAssigneeController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardLabelController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ChecklistItemController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\MyTaskController;
use App\Http\Controllers\SearchController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Route::get('my-tasks', [MyTaskController::class, 'index'])->name('my-tasks');
        Route::get('search', [SearchController::class, 'index'])->name('search');

        /*
         * Board routes scope {board} to the workspace in the URL, so a board
         * slug from another workspace 404s instead of resolving. Lists and
         * cards are shallow-nested (no scoping available from Team) and rely
         * on their policies, which resolve the owning board's workspace.
         */
        Route::scopeBindings()->group(function () {
            Route::get('boards', [BoardController::class, 'index'])->name('boards.index');
            Route::post('boards', [BoardController::class, 'store'])->name('boards.store');
            Route::get('boards/{board}', [BoardController::class, 'show'])->name('boards.show');
            Route::patch('boards/{board}', [BoardController::class, 'update'])->name('boards.update');
            Route::delete('boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');
            Route::post('boards/{board}/archive', [BoardController::class, 'archive'])->name('boards.archive');
            Route::post('boards/{board}/restore', [BoardController::class, 'restore'])->name('boards.restore');
            Route::post('boards/{board}/duplicate', [BoardController::class, 'duplicate'])->name('boards.duplicate');
            Route::post('boards/{board}/lists', [BoardListController::class, 'store'])->name('lists.store');
            Route::post('boards/{board}/members', [BoardMemberController::class, 'store'])->name('board-members.store');
            Route::patch('boards/{board}/members', [BoardMemberController::class, 'update'])->name('board-members.update');
            Route::delete('boards/{board}/members/{member}', [BoardMemberController::class, 'destroy'])->name('board-members.destroy');
        });

        Route::patch('lists/{list}', [BoardListController::class, 'update'])->name('lists.update');
        Route::delete('lists/{list}', [BoardListController::class, 'destroy'])->name('lists.destroy');
        Route::post('lists/{list}/archive', [BoardListController::class, 'archive'])->name('lists.archive');
        Route::post('lists/{list}/restore', [BoardListController::class, 'restore'])->name('lists.restore');
        Route::post('lists/{list}/cards', [CardController::class, 'store'])->name('cards.store');

        Route::get('cards/{card}', [CardController::class, 'show'])->name('cards.show');
        Route::patch('cards/{card}', [CardController::class, 'update'])->name('cards.update');
        Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');
        Route::post('cards/{card}/move', [CardController::class, 'move'])->name('cards.move');
        Route::post('cards/{card}/complete', [CardController::class, 'complete'])->name('cards.complete');
        Route::post('cards/{card}/archive', [CardController::class, 'archive'])->name('cards.archive');
        Route::post('cards/{card}/restore', [CardController::class, 'restore'])->name('cards.restore');

        Route::post('cards/{card}/comments', [CommentController::class, 'store'])->name('comments.store');
        Route::patch('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

        Route::post('cards/{card}/checklists', [ChecklistController::class, 'store'])->name('checklists.store');
        Route::patch('checklists/{checklist}', [ChecklistController::class, 'update'])->name('checklists.update');
        Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklists.destroy');

        Route::post('checklists/{checklist}/items', [ChecklistItemController::class, 'store'])->name('checklist-items.store');
        Route::patch('checklist-items/{item}', [ChecklistItemController::class, 'update'])->name('checklist-items.update');
        Route::post('checklist-items/{item}/toggle', [ChecklistItemController::class, 'toggle'])->name('checklist-items.toggle');
        Route::delete('checklist-items/{item}', [ChecklistItemController::class, 'destroy'])->name('checklist-items.destroy');

        Route::post('cards/{card}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::get('attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::get('attachments/{attachment}/preview', [AttachmentController::class, 'preview'])->name('attachments.preview');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        Route::post('boards/{board}/labels', [LabelController::class, 'store'])->name('labels.store');
        Route::patch('labels/{label}', [LabelController::class, 'update'])->name('labels.update');
        Route::delete('labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');
        Route::post('cards/{card}/labels/{label}/toggle', [CardLabelController::class, 'toggle'])->name('cards.labels.toggle');

        Route::post('cards/{card}/assignees', [CardAssigneeController::class, 'store'])->name('card-assignees.store');
        Route::delete('cards/{card}/assignees/{user}', [CardAssigneeController::class, 'destroy'])->name('card-assignees.destroy');
    });

require __DIR__.'/settings.php';
