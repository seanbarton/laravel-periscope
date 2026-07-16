<?php

use TortoiseIT\LaravelPeriscope\Http\Controllers\EntryController;
use TortoiseIT\LaravelPeriscope\Http\Controllers\EntryListController;
use TortoiseIT\LaravelPeriscope\Http\Controllers\HomeController;
use TortoiseIT\LaravelPeriscope\Http\Controllers\AssetController;
use TortoiseIT\LaravelPeriscope\Http\Controllers\LifecycleController;
use TortoiseIT\LaravelPeriscope\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('periscope.index');
Route::post('/theme', ThemeController::class)->name('periscope.theme');
Route::get('/assets/{asset}', AssetController::class)->name('periscope.assets');
Route::get('/entries', EntryListController::class)->name('periscope.entries.index');
Route::get('/entries/{uuid}', EntryController::class)->name('periscope.entries.show');
Route::get('/entries/{uuid}/lifecycle', LifecycleController::class)->name('periscope.entries.lifecycle');
