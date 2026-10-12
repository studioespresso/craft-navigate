<?php

use Illuminate\Support\Facades\Route;
use studioespresso\navigate\controllers\NodesController;

// Requests from the structure editor. Also mounted on the site action trigger, hence the CP checks.
Route::middleware(['auth', 'can:accessCp', 'can:accessPlugin-navigate'])->prefix('nodes')->group(function () {
    Route::get('tree', [NodesController::class, 'tree']);
    Route::post('add-elements', [NodesController::class, 'addElements']);
    Route::post('delete', [NodesController::class, 'delete']);
    Route::post('save-tree', [NodesController::class, 'saveTree']);
});
