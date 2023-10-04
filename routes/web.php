<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Migration;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});



Route::controller(Migration::class)->group(function () {

    Route::get('/migrate-database', 'migrateDatabase');
    Route::get('/migrate-wordpress', 'migrateWordpress');
    Route::get('/generate-redirects', 'generateRedirects');


});
