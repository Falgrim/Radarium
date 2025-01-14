<?php

use App\Http\Controllers\Api\ApiCatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api'])->group(function () {
    Route::get('/specialists', [ApiCatalogController::class, 'specialists'])->name('api.catalog.specialists');
    Route::get('/specialists/specialist/{id}', [ApiCatalogController::class, 'specialistView'])->name('api.catalog.specialist.view');
    Route::get('/companyjobs', [ApiCatalogController::class, 'companyJobs'])->name('api.catalog.companyjobs');
    Route::get('/companyjobs/companyjob/{id}', [ApiCatalogController::class, 'companyJobView'])->name('api.catalog.companyjob.view');
});
