<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::controller(\App\Http\Controllers\ApiController::class)->group(function (){
    Route::post('/register','register')->name('resgister.user');
    Route::post('/login','login')->name('login.user');
});

Route::middleware('auth:sanctum')->group( function (){
    Route::post('/logout',[\App\Http\Controllers\ApiController::class,'logout']);
    Route::post('/getUser',[\App\Http\Controllers\ApiController::class,'getDataUser']);
    Route::post('/get-date',[\App\Http\Controllers\ApiController::class,'getDate']);
    Route::post('/get-fecha',[\App\Http\Controllers\ApiController::class,'getFecha']);
    Route::post('/reserve-track',[\App\Http\Controllers\ApiController::class,'reservarPista']);
    Route::post('/get-user',[\App\Http\Controllers\ApiController::class,'getDataUserAll']);
    Route::post('/get-game-open',[\App\Http\Controllers\ApiController::class,'getGameOpen']);
    Route::post('/sign-up-game',[\App\Http\Controllers\ApiController::class,'signUpGame']);
    Route::get('/get-games-user',[\App\Http\Controllers\ApiController::class, 'getGamesUser']);
    Route::post('/delete-reserve',[\App\Http\Controllers\ApiController::class, 'deleteReserve']);
    Route::post('/delete-user-open-game',[\App\Http\Controllers\ApiController::class,'deleteUserOpenGame']);
    Route::get('/get-id-user',[\App\Http\Controllers\ApiController::class,'getIdUser']);
});

Route::get('/check-open-games',[\App\Http\Controllers\ApiController::class, 'checkOpenGames']);


