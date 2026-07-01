<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Plugins\ChatbotFlow\Controllers\ChatbotFlowController;

Route::group(['prefix' => LaravelLocalization::setLocale(), 'middleware' => ['auth', '2fa']], function () {
    Route::get('/chatbot-flow', [ChatbotFlowController::class, 'index'])->name('chatbot-flow')->middleware('permissions');
    Route::get('/chatbot-flow/create', [ChatbotFlowController::class, 'create'])->name('chatbot-flow.create')->middleware('permissions');
    Route::post('/chatbot-flow', [ChatbotFlowController::class, 'store'])->name('chatbot-flow.store')->middleware('permissions');
    Route::get('/chatbot-flow/{id}/edit', [ChatbotFlowController::class, 'edit'])->name('chatbot-flow.edit')->middleware('permissions');
    Route::post('/chatbot-flow/{id}/update', [ChatbotFlowController::class, 'update'])->name('chatbot-flow.update')->middleware('permissions');
    Route::post('/chatbot-flow/destroy', [ChatbotFlowController::class, 'destroy'])->name('chatbot-flow.destroy')->middleware('permissions');
    Route::post('/chatbot-flow/{id}/status', [ChatbotFlowController::class, 'status'])->name('chatbot-flow.status')->middleware('permissions');
    Route::post('/chatbot-flow/{id}/save', [ChatbotFlowController::class, 'save'])->name('chatbot-flow.save')->middleware('permissions');
});
