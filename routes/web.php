<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProjectSettingsController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RetentionController;
use App\Http\Controllers\DocTypeController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\InboundEmailController;
use App\Http\Controllers\PublicSignController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\WorkflowAdminController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\StructureController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public: client logos (the login page of a white-label domain needs them)
Route::get('/branding/logo/{client}', [BrandingController::class, 'logo'])->name('branding.logo');

// Public sharing links: the long random token is the key (F-SHR-01)
Route::post('/inbound/{alias}', [InboundEmailController::class, 'receive'])->middleware('throttle:60,1')->name('inbound');
Route::get('/sign/{token}', [PublicSignController::class, 'show'])->name('sign.show');
Route::get('/sign/{token}/page/{n}', [PublicSignController::class, 'page'])->whereNumber('n')->name('sign.page');
Route::get('/sign/{token}/file', [PublicSignController::class, 'file'])->name('sign.file');
Route::post('/sign/{token}', [PublicSignController::class, 'sign'])->middleware('throttle:20,1')->name('sign.submit');
Route::post('/sign/{token}/decline', [PublicSignController::class, 'decline'])->middleware('throttle:20,1')->name('sign.decline');
Route::get('/s/{token}', [PublicShareController::class, 'show'])->name('share.show');
Route::post('/s/{token}/unlock', [PublicShareController::class, 'unlock'])->middleware('throttle:10,1')->name('share.unlock');
Route::get('/s/{token}/file', [PublicShareController::class, 'file'])->name('share.file');
Route::get('/s/{token}/download', [PublicShareController::class, 'download'])->name('share.download');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/support/act', [\App\Http\Controllers\SupportSessionController::class, 'act'])->name('support.act');
    Route::post('/support/stop', [\App\Http\Controllers\SupportSessionController::class, 'stop'])->name('support.stop');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/help', [HelpController::class, 'index'])->name('help');

    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::post('/users/{user}/status', [UserController::class, 'status'])->name('users.status');
        Route::get('/audit-log', [AuditController::class, 'index'])->name('audit');
        Route::get('/branding', [BrandingController::class, 'edit'])->name('branding.edit');
        Route::put('/branding', [BrandingController::class, 'update'])->name('branding.update');
    });

    // Documents area: works inside a client (Client Admins and Users)
    Route::middleware('role:client')->group(function () {
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents');
        Route::get('/search', [DocumentController::class, 'search'])->name('search');
        Route::get('/search/suggest', [DocumentController::class, 'suggest'])->middleware('throttle:120,1')->name('search.suggest');
        Route::get('/projects/{project}', [DocumentController::class, 'project'])->name('projects.show');
        Route::get('/projects/{project}/gallery', [DocumentController::class, 'gallery'])->middleware('role:client_admin')->name('projects.gallery');
        Route::post('/projects/{project}/upload', [DocumentController::class, 'upload'])->name('projects.upload');
        Route::get('/projects/{project}/queue', [DocumentController::class, 'queue'])->name('projects.queue');
        Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        Route::get('/documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');
        Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::get('/documents/{document}/pages', [\App\Http\Controllers\AnnotationController::class, 'pages'])->name('pages.index');
        Route::get('/documents/{document}/pages/{n}', [\App\Http\Controllers\AnnotationController::class, 'page'])->whereNumber('n')->name('pages.show');
        Route::post('/documents/{document}/annotations', [\App\Http\Controllers\AnnotationController::class, 'store'])->name('annotations.store');
        Route::delete('/annotations/{annotation}', [\App\Http\Controllers\AnnotationController::class, 'destroy'])->name('annotations.destroy');
        Route::post('/documents/{document}/redact', [\App\Http\Controllers\AnnotationController::class, 'redact'])->name('documents.redact');
        Route::post('/documents/{document}/versions', [DocumentController::class, 'storeVersion'])->name('versions.store');
        Route::get('/document-versions/{version}/download', [DocumentController::class, 'downloadVersion'])->name('versions.download');
        Route::post('/document-versions/{version}/restore', [DocumentController::class, 'restoreVersion'])->name('versions.restore');
        Route::get('/recycle-bin', [DocumentController::class, 'recycle'])->name('recycle');
        Route::post('/recycle-bin/{id}/restore', [DocumentController::class, 'restore'])->name('recycle.restore');
    });

    // Workflow inbox, sharing and reports (everyone inside a client)
    Route::middleware('role:client')->group(function () {
        Route::get('/workflow', [WorkflowController::class, 'inbox'])->name('workflow');
        Route::get('/workflow/work', [WorkflowController::class, 'work'])->name('workflow.work');
        Route::post('/workflow-items/{item}/act', [WorkflowController::class, 'act'])->name('workflow.act');
        Route::post('/documents/{document}/workflow', [WorkflowController::class, 'start'])->name('workflow.start');
        Route::post('/documents/{document}/share', [ShareController::class, 'store'])->name('share.store');
        Route::delete('/shares/{share}', [ShareController::class, 'revoke'])->name('share.revoke');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::put('/documents/{document}/type', [DocTypeController::class, 'assign'])->name('documents.type');
        Route::post('/documents/{document}/forms/{form}', [FormController::class, 'submit'])->name('forms.submit');
        Route::post('/documents/{document}/signatures', [SignatureController::class, 'store'])->name('signatures.store');
        Route::delete('/signatures/{signature}', [SignatureController::class, 'cancel'])->name('signatures.cancel');
        Route::post('/signatures/{signature}/remind', [SignatureController::class, 'remind'])->name('signatures.remind');
        Route::get('/signatures/{signature}/certificate', [SignatureController::class, 'certificate'])->name('signatures.certificate');
    });

    // Workflow builder, retention and legal holds (Client Admin)
    Route::middleware('role:client_admin')->group(function () {
        Route::get('/workflows', [WorkflowAdminController::class, 'index'])->name('workflows');
        Route::post('/workflows', [WorkflowAdminController::class, 'store'])->name('workflows.store');
        Route::get('/workflows/{workflow}', [WorkflowAdminController::class, 'edit'])->name('workflows.edit');
        Route::put('/workflows/{workflow}', [WorkflowAdminController::class, 'save'])->name('workflows.save');
        Route::delete('/workflows/{workflow}', [WorkflowAdminController::class, 'destroy'])->name('workflows.destroy');

        Route::get('/doc-types', [DocTypeController::class, 'index'])->name('doctypes');
        Route::post('/doc-types', [DocTypeController::class, 'store'])->name('doctypes.store');
        Route::put('/doc-types/{doctype}', [DocTypeController::class, 'update'])->name('doctypes.update');
        Route::delete('/doc-types/{doctype}', [DocTypeController::class, 'destroy'])->name('doctypes.destroy');

        Route::get('/forms', [FormController::class, 'index'])->name('forms');
        Route::post('/forms', [FormController::class, 'store'])->name('forms.store');
        Route::get('/forms/{form}', [FormController::class, 'edit'])->name('forms.edit');
        Route::put('/forms/{form}', [FormController::class, 'save'])->name('forms.save');
        Route::delete('/forms/{form}', [FormController::class, 'destroy'])->name('forms.destroy');

        Route::post('/scheduled-reports', [\App\Http\Controllers\ScheduledReportController::class, 'store'])->name('schedules.store');
        Route::post('/scheduled-reports/{report}/toggle', [\App\Http\Controllers\ScheduledReportController::class, 'toggle'])->name('schedules.toggle');
        Route::post('/scheduled-reports/{report}/send', [\App\Http\Controllers\ScheduledReportController::class, 'sendNow'])->name('schedules.send');
        Route::delete('/scheduled-reports/{report}', [\App\Http\Controllers\ScheduledReportController::class, 'destroy'])->name('schedules.destroy');

        Route::get('/quarantine', [\App\Http\Controllers\QuarantineController::class, 'index'])->name('quarantine');
        Route::post('/quarantine/{quarantined}/release', [\App\Http\Controllers\QuarantineController::class, 'release'])->name('quarantine.release');
        Route::delete('/quarantine/{quarantined}', [\App\Http\Controllers\QuarantineController::class, 'destroy'])->name('quarantine.destroy');

        Route::get('/retention', [RetentionController::class, 'index'])->name('retention');
        Route::put('/retention/projects/{project}', [RetentionController::class, 'updateProject'])->name('retention.project');
        Route::post('/retention/review', [RetentionController::class, 'review'])->name('retention.review');
        Route::get('/retention/certificates/{certificate}', [RetentionController::class, 'certificate'])->name('retention.certificate');
        Route::post('/documents/{document}/hold', [RetentionController::class, 'hold'])->name('documents.hold');
        Route::delete('/documents/{document}/hold', [RetentionController::class, 'release'])->name('documents.release');
    });

    // Structure: departments, projects, index fields and access (Client Admin)
    Route::middleware('role:client_admin')->group(function () {
        Route::get('/structure', [StructureController::class, 'index'])->name('structure');
        Route::post('/departments', [StructureController::class, 'storeDepartment'])->name('departments.store');
        Route::put('/departments/{department}', [StructureController::class, 'updateDepartment'])->name('departments.update');
        Route::delete('/departments/{department}', [StructureController::class, 'destroyDepartment'])->name('departments.destroy');
        Route::post('/departments/{department}/projects', [StructureController::class, 'storeProject'])->name('projects.store');

        Route::get('/projects/{project}/settings', [ProjectSettingsController::class, 'edit'])->name('projects.settings');
        Route::put('/projects/{project}', [ProjectSettingsController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectSettingsController::class, 'destroy'])->name('projects.destroy');
        Route::put('/projects/{project}/access', [ProjectSettingsController::class, 'updateAccess'])->name('projects.access');
        Route::post('/projects/{project}/fields', [ProjectSettingsController::class, 'storeField'])->name('fields.store');
        Route::put('/fields/{field}', [ProjectSettingsController::class, 'updateField'])->name('fields.update');
        Route::delete('/fields/{field}', [ProjectSettingsController::class, 'destroyField'])->name('fields.destroy');
        Route::post('/fields/{field}/move', [ProjectSettingsController::class, 'moveField'])->name('fields.move');
        Route::post('/fields/{field}/restore', [ProjectSettingsController::class, 'restoreField'])->name('fields.restore');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/blueprints', [\App\Http\Controllers\BlueprintController::class, 'index'])->name('blueprints');
        Route::get('/blueprints/{blueprint}', [\App\Http\Controllers\BlueprintController::class, 'show'])->name('blueprints.show');
        Route::delete('/blueprints/{blueprint}', [\App\Http\Controllers\BlueprintController::class, 'destroy'])->name('blueprints.destroy');
        Route::post('/clients/{client}/blueprint', [\App\Http\Controllers\BlueprintController::class, 'store'])->name('blueprints.store');
        Route::get('/support', [\App\Http\Controllers\SupportController::class, 'index'])->name('support');
        Route::post('/support/{client}/view-as/{user}', [\App\Http\Controllers\SupportSessionController::class, 'start'])->name('support.start');
        Route::get('/support/{client}/export', [\App\Http\Controllers\SupportController::class, 'export'])->name('support.export');
        Route::get('/support/{client}', [\App\Http\Controllers\SupportController::class, 'show'])->name('support.show');
        Route::resource('clients', ClientController::class)->except('show');
        Route::post('/clients/{client}/status', [ClientController::class, 'status'])->name('clients.status');
    });
});
