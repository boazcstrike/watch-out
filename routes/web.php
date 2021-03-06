<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes(['verify' => true]);

Route::get('/', 'StreamController@home');

Route::resource('users', 'UserController')
    ->middleware('auth');
Route::put('users/{user}/password', 'UserController@updatePassword')
    ->middleware('auth')
    ->name('users.update_password');

Route::resource('streams', 'StreamController');
Route::get('/screen', 'StreamController@screen');
Route::get('/streams/{stream}/chunks', 'StreamController@pull')
    ->name('streams.pull');
Route::get('/streams/{stream}/full', 'StreamController@full')
    ->name('streams.full')
    ->middleware(['auth', 'verified']);
Route::post('/streams/{stream}/chunks', 'StreamController@push')
    ->middleware(['auth', 'verified'])
    ->name('streams.push');

Route::get('/record', 'StreamController@record')
    ->middleware(['auth', 'verified'])
    ->name('streams.record');

Route::get('/history', 'StreamController@history')
    ->middleware(['auth', 'verified'])
    ->name('streams.history');

