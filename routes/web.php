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

Route::get('/', function () {
    return view('welcome');
});

Route::get('/get-news', function(){
	Artisan::call('collection:news');
});

Route::get('/get-singers', function(){
	Artisan::call('collection:singer');
});

Route::get('/get-album', function(){
	Artisan::call('collection:album');
});
