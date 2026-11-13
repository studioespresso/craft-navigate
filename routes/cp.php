<?php

use Illuminate\Support\Facades\Route;
use studioespresso\navigate\Http\Controllers\NavigationsController;
use studioespresso\navigate\Http\Controllers\NodesController;

Route::middleware(['auth', 'can:accessCp', 'can:accessPlugin-navigate'])->prefix('navigate')->group(function () {
    Route::get('', [NavigationsController::class, 'index']);
    Route::get('new', [NavigationsController::class, 'edit']);
    Route::post('new', [NavigationsController::class, 'store']);
    Route::get('settings/{navId}', [NavigationsController::class, 'edit'])->whereNumber('navId');
    Route::post('settings/{navId}', [NavigationsController::class, 'store'])->whereNumber('navId');
    Route::post('{navId}/delete', [NavigationsController::class, 'delete'])->whereNumber('navId');
    Route::delete('{navId}', [NavigationsController::class, 'delete'])->whereNumber('navId');
    Route::get('edit/{navId}', [NodesController::class, 'structure'])->whereNumber('navId');
    Route::get('nodes/edit', [NodesController::class, 'edit']);
    Route::post('nodes/edit', [NodesController::class, 'save']);
});
