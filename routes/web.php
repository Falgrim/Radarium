<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\IndexController;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndexController::class, 'index'])->name('index');

Route::get('/specialists', [CatalogController::class, 'specialists'])->name('catalog.specialists');
Route::get('/specialists/specialist/{id}', [CatalogController::class, 'specialistView'])->name('catalog.specialist.view');

Route::get('/companyjobs', [CatalogController::class, 'companyJobs'])->name('catalog.companyjobs');
Route::get('/companyjobs/companyjob/{id}', [CatalogController::class, 'companyJobView'])->name('catalog.companyjob.view');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
