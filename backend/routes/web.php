<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/login', fn () => view('app', ['boot' => 'login']));
Route::get('/register', fn () => view('app', ['boot' => 'register']));
Route::get('/forgot-password', fn () => view('app', ['boot' => 'forgot']));
Route::get('/dashboard', fn () => view('app', ['boot' => 'dashboard']));
Route::get('/tests', fn () => view('app', ['boot' => 'tests']));
Route::get('/exam/{id}', fn ($id) => view('app', ['boot' => 'exam', 'param' => $id]));
Route::get('/result/{id}', fn ($id) => view('app', ['boot' => 'result', 'param' => $id]));
Route::get('/solution/{id}', fn ($id) => view('app', ['boot' => 'solution', 'param' => $id]));
Route::get('/history', fn () => view('app', ['boot' => 'history']));
Route::get('/leaderboard', fn () => view('app', ['boot' => 'leaderboard']));
Route::get('/profile', fn () => view('app', ['boot' => 'profile']));

Route::get('/up', fn () => response()->json(['status' => 'ok']));
