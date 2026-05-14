<?php

use App\Http\Controllers\Admin\ActiveAuthorsReportActionController;
use App\Http\Controllers\BuilderController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\ModerationAlertController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscribeController;
use App\Http\Controllers\TariffController;
use Illuminate\Support\Facades\Route;
use MoonShine\Http\Middleware\Authenticate as MoonShineAuthenticate;

$adminPrefix = trim((string) config('moonshine.route.prefix', 'admin'), '/');

Route::middleware(['web', MoonShineAuthenticate::class])
    ->prefix($adminPrefix)
    ->group(function () {
        Route::get('/active-authors-report/edit', [ActiveAuthorsReportActionController::class, 'edit'])
            ->name('admin.active-authors-report.edit');
        Route::post('/active-authors-report/update', [ActiveAuthorsReportActionController::class, 'update'])
            ->name('admin.active-authors-report.update');
        Route::get('/active-authors-report/export', [ActiveAuthorsReportActionController::class, 'export'])
            ->name('admin.active-authors-report.export');
        Route::post('/active-authors-report/delete', [ActiveAuthorsReportActionController::class, 'destroy'])
            ->name('admin.active-authors-report.destroy');
    });

Route::get('/', [IndexController::class, 'index'])->name('index');
Route::get('/tech', [IndexController::class, 'tech'])->name('tech');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/specialists', [CatalogController::class, 'authorsAsSpecialists'])->name('catalog.specialists');
    Route::get('/specialists/specialist/{id}', [CatalogController::class, 'authorAsSpecialistView'])->name('catalog.specialist.view');
    Route::post('/specialists/specialist/{id}', [CatalogController::class, 'specialistStoreReview'])->name('catalog.specialist.store');
    Route::post('/specialists/specialist/{id}/review', [CatalogController::class, 'specialistEditReview'])->name('catalog.specialist.edit_review');

    Route::post('/moderation-alert', [ModerationAlertController::class, 'store'])->name('moderationAlert.new');

    Route::get('/companyjobs', [CatalogController::class, 'companyJobs'])->name('catalog.companyjobs');
    Route::get('/companyjobs/companyjob/{id}', [CatalogController::class, 'companyJobView'])->name('catalog.companyjob.view');

    Route::get('/builders', [BuilderController::class, 'builders'])->name('catalog.builders');
    Route::get('/builders/builder/{id}', [BuilderController::class, 'builderView'])->name('catalog.builder.view');
    Route::post('/builders/builder/{id}', [BuilderController::class, 'builderStoreReview'])->name('catalog.builder.store');
    Route::post('/builders/builder/{id}/review', [BuilderController::class, 'builderEditReview'])->name('catalog.builder.edit_review');

    // Route::get('/profile/subscribe', [SubscribeController::class, 'main'])->name('profile.subscribe');
});

Route::get('/dashboard', function () {
    return redirect(route('catalog.builders'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/tariff/{id}', [TariffController::class, 'buy'])->name('tariff.buy');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
