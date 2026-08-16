<?php

use App\Http\Controllers\CandidateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GovernanceRequestController;
use App\Http\Controllers\HoraireController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\ManualController;
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
use App\Http\Controllers\WorkflowStepController;
use Illuminate\Support\Facades\Route;
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
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
    ])
    ->name('system.backup');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'platform-owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/licences', [LicenseController::class, 'index'])->name('licenses.index');
    Route::patch('/licences/{organisation}', [LicenseController::class, 'update'])->name('licenses.update');
    Route::post('/organisations', [OrganisationController::class, 'store'])->name('organisations.store');
});

Route::middleware(['auth', 'verified', 'role:administrateur', 'license.active'])->group(function () {
    Route::resource('shifts', ShiftController::class);
    Route::post('/shifts/{shift}/membres', [ShiftController::class, 'addMember'])->name('shifts.members.store');
    Route::delete('/shifts/{shift}/membres/{shiftMember}', [ShiftController::class, 'removeMember'])->name('shifts.members.destroy');
    Route::post('/shifts/{shift}/postes', [ShiftController::class, 'storePosition'])->name('shifts.positions.store');
    Route::delete('/shifts/{shift}/postes/{position}', [ShiftController::class, 'destroyPosition'])->name('shifts.positions.destroy');
    Route::post('/shifts/{shift}/postes/{position}/affectation', [ShiftController::class, 'assignServant'])->name('shifts.positions.assign');
    Route::delete('/shifts/{shift}/postes/{position}/affectation/{assignment}', [ShiftController::class, 'endAssignment'])->name('shifts.positions.unassign');

    Route::resource('servants', ServantController::class);
    Route::patch('/servants/{servant}/parcours/{workflowStep}', [ServantController::class, 'updateWorkflowStep'])->name('servants.workflow.update');
    Route::post('/servants/{servant}/compte', [ServantController::class, 'storeAccount'])->name('servants.account.store');
    Route::delete('/servants/{servant}/compte', [ServantController::class, 'destroyAccount'])->name('servants.account.destroy');

    Route::resource('shift-templates', ShiftTemplateController::class)
        ->parameters(['shift-templates' => 'shiftTemplate']);
    Route::post('/shift-templates/{shiftTemplate}/postes', [ShiftTemplateController::class, 'storePosition'])->name('shift-templates.positions.store');
    Route::delete('/shift-templates/{shiftTemplate}/postes/{position}', [ShiftTemplateController::class, 'destroyPosition'])->name('shift-templates.positions.destroy');

    Route::get('/gouvernance', [GovernanceRequestController::class, 'index'])->name('governance.index');
    Route::post('/gouvernance', [GovernanceRequestController::class, 'store'])->name('governance.store');
    Route::patch('/gouvernance/{governanceRequest}/valider', [GovernanceRequestController::class, 'validateRequest'])->name('governance.validate');
    Route::patch('/gouvernance/{governanceRequest}/rejeter', [GovernanceRequestController::class, 'rejectRequest'])->name('governance.reject');

    Route::get('/rapports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/rapports/servants.csv', [ReportController::class, 'exportServantsCsv'])->name('reports.servants.csv');
    Route::get('/rapports/shifts-remplissage.pdf', [ReportController::class, 'exportShiftsFillingPdf'])->name('reports.shifts.pdf');

    Route::get('/parametres', [SettingsController::class, 'index'])->name('settings.index');
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
    Route::put('/parametres/roles/{role}', [RoleController::class, 'update'])->name('settings.roles.update');

    Route::get('/parametres/parcours', [WorkflowStepController::class, 'index'])->name('settings.workflow-steps.index');
    Route::post('/parametres/parcours', [WorkflowStepController::class, 'store'])->name('settings.workflow-steps.store');
    Route::put('/parametres/parcours/{workflowStep}', [WorkflowStepController::class, 'update'])->name('settings.workflow-steps.update');
    Route::delete('/parametres/parcours/{workflowStep}', [WorkflowStepController::class, 'destroy'])->name('settings.workflow-steps.destroy');
});

Route::middleware(['auth', 'verified', 'role:administrateur,chef_equipe,chef_adjoint,coordinateur,coordinateur_adjoint', 'license.active'])->group(function () {
    Route::get('/recrutement', [ShiftRecruitmentNeedController::class, 'index'])->name('recruitment.index');
    Route::put('/recrutement/{shift}', [ShiftRecruitmentNeedController::class, 'upsert'])->name('recruitment.upsert');

    Route::get('/candidats', [CandidateController::class, 'index'])->name('candidates.index');
    Route::post('/candidats', [CandidateController::class, 'store'])->name('candidates.store');
    Route::patch('/candidats/{candidate}', [CandidateController::class, 'update'])->name('candidates.update');
    Route::delete('/candidats/{candidate}', [CandidateController::class, 'destroy'])->name('candidates.destroy');

    Route::get('/entretiens', [InterviewController::class, 'index'])->name('interviews.index');
    Route::post('/entretiens', [InterviewController::class, 'store'])->name('interviews.store');
    Route::patch('/entretiens/{interview}/resoudre', [InterviewController::class, 'resolve'])->name('interviews.resolve');

    Route::get('/mon-shift/{shift}', [ShiftController::class, 'monShift'])->name('shifts.mine.show');

    Route::get('/transferts', [ShiftTransferRequestController::class, 'index'])->name('shift-transfers.index');
    Route::post('/transferts', [ShiftTransferRequestController::class, 'store'])->name('shift-transfers.store');
    Route::patch('/transferts/{shiftTransferRequest}', [ShiftTransferRequestController::class, 'update'])->name('shift-transfers.update');
    Route::patch('/transferts/{shiftTransferRequest}/resoudre', [ShiftTransferRequestController::class, 'resolve'])->name('shift-transfers.resolve');
    Route::delete('/transferts/{shiftTransferRequest}', [ShiftTransferRequestController::class, 'destroy'])->name('shift-transfers.destroy');
});

require __DIR__.'/auth.php';
