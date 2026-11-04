<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1/private')
    ->middleware(['store.management'])
    ->group(function () {

        //pass

    });
