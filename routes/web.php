<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HoraireController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\PieuController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServantController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftRecruitmentNeedController;
use App\Http\Controllers\ShiftTemplateController;
use App\Http\Controllers\ShiftTransferRequestController;
use App\Http\Controllers\SystemBackupController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkflowStepController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/cgu', fn () => Inertia::render('Legal/Cgu'))->name('legal.cgu');
Route::get('/confidentialite', fn () => Inertia::render('Legal/Confidentialite'))->name('legal.confidentialite');

Route::get('/system/backup', [SystemBackupController::class, 'download'])
    ->middleware('throttle:5,1')
    ->withoutMiddleware([
        EncryptCookies::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        HandleInertiaRequests::class,
    ])
    ->name('system.backup');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::patch('/notifications/{notification}/lu', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

Route::middleware(['auth', 'verified', 'platform-owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/licences', [LicenseController::class, 'index'])->name('licenses.index');
    Route::patch('/licences/{organisation}', [LicenseController::class, 'update'])->name('licenses.update');
    Route::post('/organisations', [OrganisationController::class, 'store'])->name('organisations.store');
});

Route::middleware(['auth', 'verified', 'role:administrateur', 'license.active'])->group(function () {
    Route::resource('shifts', ShiftController::class)->except(['create', 'store']);
    Route::post('/shifts/{shift}/membres', [ShiftController::class, 'addMember'])->name('shifts.members.store');
    Route::delete('/shifts/{shift}/membres/{shiftMember}', [ShiftController::class, 'removeMember'])->name('shifts.members.destroy');
    Route::post('/shifts/{shift}/postes/{position}/affectation', [ShiftController::class, 'assignServant'])->name('shifts.positions.assign');
    Route::delete('/shifts/{shift}/postes/{position}/affectation/{assignment}', [ShiftController::class, 'endAssignment'])->name('shifts.positions.unassign');

    Route::resource('servants', ServantController::class)->except(['edit', 'update']);
    Route::post('/servants/{servant}/compte', [ServantController::class, 'storeAccount'])->name('servants.account.store');
    Route::delete('/servants/{servant}/compte', [ServantController::class, 'destroyAccount'])->name('servants.account.destroy');
    Route::patch('/servants/{servant}/anonymiser', [ServantController::class, 'anonymize'])->name('servants.anonymize');
    Route::get('/servants/{servant}/export', [ServantController::class, 'export'])->name('servants.export');

    Route::resource('shift-templates', ShiftTemplateController::class)
        ->parameters(['shift-templates' => 'shiftTemplate']);
    Route::post('/shift-templates/{shiftTemplate}/postes', [ShiftTemplateController::class, 'storePosition'])->name('shift-templates.positions.store');
    Route::delete('/shift-templates/{shiftTemplate}/postes/{position}', [ShiftTemplateController::class, 'destroyPosition'])->name('shift-templates.positions.destroy');

    Route::get('/rapports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/rapports/servants.csv', [ReportController::class, 'exportServantsCsv'])->name('reports.servants.csv');
    Route::get('/rapports/shifts-remplissage.pdf', [ReportController::class, 'exportShiftsFillingPdf'])->name('reports.shifts.pdf');

    Route::get('/parametres', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/parametres/journal', [ActivityLogController::class, 'index'])->name('settings.activity-log.index');
    Route::get('/manuel', [ManualController::class, 'download'])->name('manuel.download');

    Route::get('/parametres/pieux', [PieuController::class, 'index'])->name('settings.pieux.index');
    Route::post('/parametres/pieux', [PieuController::class, 'store'])->name('settings.pieux.store');
    Route::put('/parametres/pieux/{pieu}', [PieuController::class, 'update'])->name('settings.pieux.update');
    Route::delete('/parametres/pieux/{pieu}', [PieuController::class, 'destroy'])->name('settings.pieux.destroy');

    Route::get('/parametres/horaires', [HoraireController::class, 'index'])->name('settings.horaires.index');
    Route::post('/parametres/horaires', [HoraireController::class, 'store'])->name('settings.horaires.store');
    Route::put('/parametres/horaires/{horaire}', [HoraireController::class, 'update'])->name('settings.horaires.update');
    Route::delete('/parametres/horaires/{horaire}', [HoraireController::class, 'destroy'])->name('settings.horaires.destroy');

    Route::get('/parametres/roles', [RoleController::class, 'index'])->name('settings.roles.index');
    Route::post('/parametres/roles', [RoleController::class, 'store'])->name('settings.roles.store');
    Route::put('/parametres/roles/{role}', [RoleController::class, 'update'])->name('settings.roles.update');
    Route::delete('/parametres/roles/{role}', [RoleController::class, 'destroy'])->name('settings.roles.destroy');

    Route::get('/parametres/utilisateurs', [UserController::class, 'index'])->name('settings.users.index');
    Route::post('/parametres/utilisateurs', [UserController::class, 'store'])->name('settings.users.store');
    Route::put('/parametres/utilisateurs/{user}', [UserController::class, 'update'])->name('settings.users.update');
    Route::delete('/parametres/utilisateurs/{user}', [UserController::class, 'destroy'])->name('settings.users.destroy');

    Route::get('/parametres/parcours', [WorkflowStepController::class, 'index'])->name('settings.workflow-steps.index');
    Route::post('/parametres/parcours', [WorkflowStepController::class, 'store'])->name('settings.workflow-steps.store');
    Route::put('/parametres/parcours/{workflowStep}', [WorkflowStepController::class, 'update'])->name('settings.workflow-steps.update');
    Route::delete('/parametres/parcours/{workflowStep}', [WorkflowStepController::class, 'destroy'])->name('settings.workflow-steps.destroy');
});

Route::middleware(['auth', 'verified', 'role:administrateur,coordonnateur_equipe', 'license.active'])->group(function () {
    Route::get('/recrutement', [ShiftRecruitmentNeedController::class, 'index'])->name('recruitment.index');
    Route::put('/recrutement/{shift}', [ShiftRecruitmentNeedController::class, 'upsert'])->name('recruitment.upsert');

    Route::get('/mon-shift/{shift}', [ShiftController::class, 'monShift'])->name('shifts.mine.show');
    Route::get('/mes-servants/{servant}', [ServantController::class, 'mine'])->name('servants.mine.show');
    Route::get('/servants/{servant}/edit', [ServantController::class, 'edit'])->name('servants.edit');
    Route::put('/servants/{servant}', [ServantController::class, 'update'])->name('servants.update');
    Route::post('/servants/{servant}/parcours', [ServantController::class, 'storeWorkflowStep'])->name('servants.workflow.store');
    Route::patch('/servants/{servant}/parcours/{workflowStep}', [ServantController::class, 'updateWorkflowStep'])->name('servants.workflow.update');
    Route::delete('/servants/{servant}/parcours/{workflowStep}', [ServantController::class, 'destroyWorkflowStep'])->name('servants.workflow.destroy');
    Route::get('/servants/{servant}/photo', [ServantController::class, 'photo'])->name('servants.photo');

    Route::post('/candidats', [CandidateController::class, 'store'])->name('candidates.store');

    Route::get('/transferts', [ShiftTransferRequestController::class, 'index'])->name('shift-transfers.index');
    Route::get('/transferts/releves', [ShiftTransferRequestController::class, 'releves'])->name('shift-transfers.releves');
    Route::post('/transferts', [ShiftTransferRequestController::class, 'store'])->name('shift-transfers.store');
    Route::patch('/transferts/{shiftTransferRequest}', [ShiftTransferRequestController::class, 'update'])->name('shift-transfers.update');
    Route::patch('/transferts/{shiftTransferRequest}/valider-origine', [ShiftTransferRequestController::class, 'validerOrigine'])->name('shift-transfers.valider-origine');
    Route::patch('/transferts/{shiftTransferRequest}/valider-destination', [ShiftTransferRequestController::class, 'validerDestination'])->name('shift-transfers.valider-destination');
    Route::patch('/transferts/{shiftTransferRequest}/resoudre', [ShiftTransferRequestController::class, 'resolve'])->name('shift-transfers.resolve');
    Route::delete('/transferts/{shiftTransferRequest}', [ShiftTransferRequestController::class, 'destroy'])->name('shift-transfers.destroy');
});

Route::middleware(['auth', 'verified', 'role:administrateur,coordonnateur_equipe,secretaire', 'license.active'])->group(function () {
    Route::get('/candidats', [CandidateController::class, 'index'])->name('candidates.index');
    Route::patch('/candidats/{candidate}', [CandidateController::class, 'update'])->name('candidates.update');
    Route::delete('/candidats/{candidate}', [CandidateController::class, 'destroy'])->name('candidates.destroy');

    Route::get('/entretiens', [InterviewController::class, 'index'])->name('interviews.index');
    Route::post('/entretiens', [InterviewController::class, 'store'])->name('interviews.store');
    Route::patch('/entretiens/{interview}', [InterviewController::class, 'update'])->name('interviews.update');
    Route::patch('/entretiens/{interview}/annuler', [InterviewController::class, 'cancel'])->name('interviews.cancel');
    Route::patch('/entretiens/{interview}/resoudre', [InterviewController::class, 'resolve'])->name('interviews.resolve');
});

require __DIR__.'/auth.php';
