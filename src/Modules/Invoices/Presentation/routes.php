<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Presentation\Http\Controllers\InvoiceController;

Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->whereUuid('invoice')->name('invoices.show');
Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->whereUuid('invoice')->name('invoices.send');
