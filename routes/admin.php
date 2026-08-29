<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Support\Http\Controllers\SupportController;
use WebBlocks\Support\SupportServiceProvider;

SupportServiceProvider::registerViewNamespace();

Route::get('/support', [SupportController::class, 'index'])->name('support.index');
Route::get('/support/new', [SupportController::class, 'create'])->name('support.create');
Route::post('/support', [SupportController::class, 'store'])->name('support.store');
Route::post('/support/connection', [SupportController::class, 'connect'])->name('support.connection.store');
Route::post('/support/connection/refresh', [SupportController::class, 'refreshActivation'])->name('support.connection.refresh');
Route::delete('/support/connection', [SupportController::class, 'disconnect'])->name('support.connection.destroy');
Route::get('/support/{ticket}', [SupportController::class, 'show'])->name('support.show');
Route::post('/support/{ticket}/replies', [SupportController::class, 'comment'])->name('support.comment');
