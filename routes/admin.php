<?php

use SkillDo\Support\Facades\Route;

Route::middleware('auth:admin')->prefix('admin/reels')->group(function ()
{
    $controller = \Reels\Controllers\Admin\ReelController::class;

    Route::match(['get', 'post'], '/', $controller . '@index')->name('admin.reels.index');

    Route::match(['get', 'post'], '/add', $controller . '@add')->name('admin.reels.add');

    Route::match(['get', 'post'], '/edit/{id}', $controller . '@edit')
        ->where('id', '[0-9]+')
        ->name('admin.reels.edit');
});

