<?php

use Illuminate\Support\Facades\Route;

// To jest mikroserwis - jedyny interfejs dla ludzi to panel operatorski.
Route::redirect('/', '/admin');
