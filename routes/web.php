<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['fr', 'en', 'ja'])) {
        session(['locale' => $locale]);
    }
    
    return redirect()->back();
})->name('lang.switch');