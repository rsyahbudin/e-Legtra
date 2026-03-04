<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    // Tickets Routes (main feature)
    Volt::route('tickets', 'contracts.index')
        ->middleware('permission:tickets.view')
        ->name('tickets.index');
    Volt::route('tickets/create', 'contracts.create')
        ->middleware('permission:tickets.create')
        ->name('tickets.create');
    Volt::route('tickets/{contract}', 'contracts.show')
        ->middleware('permission:tickets.view')
        ->name('tickets.show');
    Volt::route('tickets/{contract}/edit', 'contracts.edit')
        ->middleware('permission:tickets.edit')
        ->name('tickets.edit');

    // Ticket Document Preview & Download
    Route::middleware('permission:tickets.view')->prefix('tickets/{ticketNumber}/documents')->group(function () {
        Route::get('preview/{path}', [\App\Http\Controllers\TicketDocumentController::class, 'preview'])
            ->where('path', '.*')
            ->name('tickets.documents.preview');
        Route::get('download/{path}', [\App\Http\Controllers\TicketDocumentController::class, 'download'])
            ->where('path', '.*')
            ->name('tickets.documents.download');
    });

    // Contract Repository (Asset View)
    Volt::route('contracts', 'contracts.repository')
        ->middleware('permission:contracts.view')
        ->name('contracts.repository');

    // Departments Management
    Route::get('/departments', fn () => view('livewire.departments.index'))->middleware('permission:departments.manage')->name('departments.index');

    // Contracts Export
    Route::get('contracts-export', function (\Illuminate\Http\Request $request) {
        if (! auth()->user()->hasPermission('reports.export')) {
            abort(403);
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ContractsExport(
                $request->get('status'),
                $request->get('type'),
                $request->get('division') ? (int) $request->get('division') : null
            ),
            'contracts_'.now()->format('Y-m-d_His').'.xlsx'
        );
    })->middleware('permission:reports.export')->name('contracts.export');

    // Tickets Export
    Route::get('tickets-export', function (\Illuminate\Http\Request $request) {
        if (! auth()->user()->hasPermission('reports.export')) {
            abort(403);
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\TicketsExport(
                $request->get('status'),
                $request->get('type'),
                $request->get('division') ? (int) $request->get('division') : null,
                $request->get('start_date'),
                $request->get('end_date')
            ),
            'tickets_'.now()->format('Y-m-d_His').'.xlsx'
        );
    })->middleware('permission:reports.export')->name('tickets.export');

    // Divisions
    Volt::route('divisions', 'divisions.index')
        ->middleware('permission:divisions.view')
        ->name('divisions.index');
});

// Admin routes
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Volt::route('users', 'admin.users.index')
        ->middleware('permission:users.view')
        ->name('admin.users.index');
    Volt::route('roles', 'admin.roles.index')
        ->middleware('permission:roles.view')
        ->name('admin.roles.index');
    Volt::route('settings', 'admin.settings.index')
        ->middleware('permission:settings.view')
        ->name('admin.settings.index');
    Volt::route('email-templates', 'admin.email-templates')
        ->middleware('permission:email_templates.edit')
        ->name('admin.email-templates');
});

Route::get('/csrf-token', function () {
    return response()->json([
        'csrf_token' => csrf_token(),
    ]);
});
