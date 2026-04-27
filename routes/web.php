<?php

use App\Http\Controllers\SubscriptionStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/subscription/inactive', [SubscriptionStatusController::class, 'inactive'])
    ->name('subscription.inactive');
