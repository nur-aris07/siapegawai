<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FreePracticeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\Questions\QuestionManagementController;
use App\Http\Controllers\Admin\SubjectCatalogController;
use App\Http\Controllers\Admin\TryoutManagementController;
use App\Http\Controllers\Admin\ParticipantManagementController;
use App\Http\Controllers\Admin\AdminSecurityController;
use App\Http\Controllers\Member\MemberTryoutController;
use App\Http\Controllers\Member\MemberHistoryController;
use App\Http\Controllers\Member\MemberProfileController;
use App\Http\Controllers\Member\MemberClassController;
use App\Http\Controllers\Admin\ClassManagementController;
use App\Http\Controllers\Admin\LearningMaterialRevisionController;
use App\Http\Controllers\Admin\ClassPracticeManagementController;
use App\Http\Controllers\Admin\ClassMonitoringController;
use App\Http\Controllers\Admin\ClassMediaManagementController;
use App\Http\Controllers\Admin\QuickQuizManagementController;
use App\Http\Controllers\Admin\QuickQuizQuotaController;
use App\Http\Controllers\Member\MemberClassMediaController;
use App\Http\Controllers\Member\MemberClassPracticeController;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAdminSessionVersion;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

Route::middleware(AddSecurityHeaders::class)->group(function () {
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/bank-soal', [QuestionController::class, 'index'])->name('questions.index');
Route::get('/latihan-gratis', [FreePracticeController::class, 'intro'])->name('practice.intro');
Route::get('/latihan-gratis/kerjakan', [FreePracticeController::class, 'take'])->name('practice.take');
Route::post('/latihan-gratis/hasil', [FreePracticeController::class, 'submit'])->middleware('throttle:20,1')->name('practice.submit');
Route::get('/latihan-gratis/hasil', [FreePracticeController::class, 'result'])->name('practice.result');

Route::get('/tentang', [PageController::class, 'about'])->name('page.tentang');
Route::get('/kontak', [PageController::class, 'contact'])->name('page.kontak');
Route::get('/privasi', [PageController::class, 'privacy'])->name('page.privasi');
Route::get('/syarat-layanan', [PageController::class, 'terms'])->name('page.syarat-layanan');
Route::get('/kelas', [PageController::class, 'classes'])->name('page.kelas');
Route::get('/paket', [PageController::class, 'packages'])->name('page.paket');

Route::middleware('guest')->group(function () {
    Route::get('/daftar', [RegistrationController::class, 'create'])->name('register');
    Route::post('/daftar', [RegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/lupa-password', [PasswordController::class, 'requestForm'])->name('password.request');
    Route::post('/lupa-password', [PasswordController::class, 'sendLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/pendaftaran/lanjut-whatsapp', [RegistrationController::class, 'whatsapp'])->name('registration.whatsapp');
    Route::get('/aktivasi', [RegistrationController::class, 'status'])->name('registration.status');
    Route::middleware([EnsureActiveAccount::class, EnsureRole::class.':participant'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'participant'])->name('dashboard');
        // S7: private participant profile and manually enrolled classes.
        Route::get('/profil', [MemberProfileController::class,'edit'])->name('member.profile.edit');
        Route::patch('/profil', [MemberProfileController::class,'update'])->middleware('throttle:12,1')->name('member.profile.update');
        Route::post('/profil/password', [MemberProfileController::class,'changePassword'])->middleware('throttle:5,1')->name('member.profile.password');
        Route::get('/kelas-saya', [MemberClassController::class,'index'])->name('member.classes.index');
        Route::get('/kelas-saya/{class}', [MemberClassController::class,'show'])->name('member.classes.show');
        // S7.5 private PDF: auth + live enrollment, no public storage URLs.
        Route::get('/kelas-saya/{class}/materi/{material}/media/{media}/pdf', [MemberClassMediaController::class,'pdf'])
            ->middleware('throttle:60,1')->name('member.classes.material.media.pdf');
        Route::post('/kelas-saya/{class}/materi/{material}/selesai', [MemberClassController::class,'complete'])->middleware('throttle:30,1')->name('member.classes.material.complete');
        // S7.3: Latihan Kelas, isolated from CAT attempts/history.
        Route::prefix('kelas-saya/{class}/latihan')->group(function () {
            Route::get('/', [MemberClassPracticeController::class,'index'])->name('member.class-practices.index');
            Route::get('/sesi/{attempt}', [MemberClassPracticeController::class,'take'])->name('member.class-practices.take');
            Route::patch('/sesi/{attempt}/jawaban', [MemberClassPracticeController::class,'save'])->middleware('throttle:120,1')->name('member.class-practices.save');
            Route::post('/sesi/{attempt}/kumpulkan', [MemberClassPracticeController::class,'submit'])->middleware('throttle:15,1')->name('member.class-practices.submit');
            Route::get('/sesi/{attempt}/hasil', [MemberClassPracticeController::class,'result'])->name('member.class-practices.result');
            Route::get('/{practice}', [MemberClassPracticeController::class,'show'])->name('member.class-practices.show');
            Route::post('/{practice}/mulai', [MemberClassPracticeController::class,'start'])->middleware('throttle:10,1')->name('member.class-practices.start');
        });
        Route::prefix('peserta/tryout')->group(function () {
            Route::get('/', [MemberTryoutController::class, 'index'])->name('member.tryouts.index');
            Route::get('/riwayat', [MemberHistoryController::class, 'index'])->name('member.tryouts.history');
            Route::post('/{package}/mulai', [MemberTryoutController::class, 'start'])->middleware('throttle:10,1')->name('member.tryouts.start');
            Route::get('/sesi/{attempt}', [MemberTryoutController::class, 'take'])->name('member.tryouts.take');
            Route::patch('/sesi/{attempt}/jawaban', [MemberTryoutController::class, 'save'])->middleware('throttle:120,1')->name('member.tryouts.save');
            Route::post('/sesi/{attempt}/kumpulkan', [MemberTryoutController::class, 'submit'])->middleware('throttle:15,1')->name('member.tryouts.submit');
            Route::get('/sesi/{attempt}/hasil', [MemberTryoutController::class, 'result'])->name('member.tryouts.result');
        });
    });
});
Route::prefix('admin')->middleware(['auth', EnsureActiveAccount::class, EnsureRole::class.':operational_admin,superadmin', EnsureAdminSessionVersion::class])->group(function () {
    Route::get('/', [DashboardController::class, 'admin'])->name('admin.dashboard');
    // S7: class content is manageable by admins; enrollment grant/revoke is Superadmin only.
    Route::get('/kelas', [ClassManagementController::class,'index'])->name('admin.classes.index');
    Route::post('/kelas', [ClassManagementController::class,'store'])->middleware('throttle:20,1')->name('admin.classes.store');
    Route::get('/kelas/{class}/edit', [ClassManagementController::class,'edit'])->name('admin.classes.edit');
    // S7.4: read-only class monitoring, independent from CAT and score writes.
    Route::get('/kelas/{class}/monitoring', [ClassMonitoringController::class,'index'])->name('admin.classes.monitoring.index');
    Route::get('/kelas/{class}/monitoring/peserta/{user}', [ClassMonitoringController::class,'show'])->name('admin.classes.monitoring.show');
    Route::put('/kelas/{class}', [ClassManagementController::class,'update'])->middleware('throttle:20,1')->name('admin.classes.update');
    // S7.5 class chapters and private media; mutation only while material remains draft.
    Route::post('/kelas/{class}/bab', [ClassMediaManagementController::class,'chapter'])->middleware('throttle:20,1')->name('admin.classes.chapters.store');
    Route::post('/kelas/{class}/materi/{material}/media/pdf', [ClassMediaManagementController::class,'uploadPdf'])->middleware('throttle:10,1')->name('admin.classes.media.pdf');
    Route::post('/kelas/{class}/materi/{material}/media/video', [ClassMediaManagementController::class,'video'])->middleware('throttle:20,1')->name('admin.classes.media.video');
    Route::post('/kelas/{class}/materi/{material}/media/{media}/hapus', [ClassMediaManagementController::class,'remove'])->middleware('throttle:20,1')->name('admin.classes.media.remove');
    Route::get('/kelas/{class}/materi/{material}/media/{media}/unduh', [ClassMediaManagementController::class,'download'])->middleware('throttle:60,1')->name('admin.classes.media.download');
    Route::post('/kelas/{class}/materi', [ClassManagementController::class,'addMaterial'])->middleware('throttle:20,1')->name('admin.classes.material.store');
    Route::put('/kelas/{class}/materi/{material}', [ClassManagementController::class,'updateDraftMaterial'])->middleware('throttle:20,1')->name('admin.classes.material.update');
    Route::post('/kelas/{class}/materi/{material}/terbit', [ClassManagementController::class,'publishMaterial'])->middleware('throttle:15,1')->name('admin.classes.material.publish');
    // CLS-MEDIA-02: create/edit draft revision, then explicitly publish a versioned snapshot.
    Route::post('/kelas/{class}/materi/{material}/revisi', [LearningMaterialRevisionController::class,'start'])->middleware('throttle:15,1')->name('admin.classes.material.revision.start');
    Route::put('/kelas/{class}/materi/{material}/revisi', [LearningMaterialRevisionController::class,'update'])->middleware('throttle:20,1')->name('admin.classes.material.revision.update');
    Route::post('/kelas/{class}/materi/{material}/revisi/terbit', [LearningMaterialRevisionController::class,'publish'])->middleware('throttle:10,1')->name('admin.classes.material.revision.publish');
    Route::post('/kelas/{class}/peserta', [ClassManagementController::class,'enroll'])->middleware('throttle:10,1')->name('admin.classes.enroll');
    Route::post('/kelas/{class}/peserta/{user}/cabut', [ClassManagementController::class,'revoke'])->middleware('throttle:10,1')->name('admin.classes.revoke');
    Route::prefix('kelas/{class}/latihan')->group(function () {
        Route::get('/', [ClassPracticeManagementController::class,'index'])->name('admin.class-practices.index');
        Route::get('/cari-soal', [ClassPracticeManagementController::class,'searchQuestions'])->middleware('throttle:90,1')->name('admin.class-practices.search');
        Route::get('/buat', [ClassPracticeManagementController::class,'create'])->name('admin.class-practices.create');
        Route::post('/', [ClassPracticeManagementController::class,'store'])->middleware('throttle:20,1')->name('admin.class-practices.store');
        Route::get('/{practice}/edit', [ClassPracticeManagementController::class,'edit'])->name('admin.class-practices.edit');
        Route::put('/{practice}', [ClassPracticeManagementController::class,'update'])->middleware('throttle:20,1')->name('admin.class-practices.update');
        Route::post('/{practice}/terbit', [ClassPracticeManagementController::class,'publish'])->middleware('throttle:15,1')->name('admin.class-practices.publish');
        Route::post('/{practice}/arsip', [ClassPracticeManagementController::class,'archive'])->middleware('throttle:15,1')->name('admin.class-practices.archive');
    });
    // QQ-CORE-01: editorial only. No participant route or quota consumption until PKG-OPS-ENT passes QA.
    Route::get('/quiz-kilat', [QuickQuizManagementController::class,'index'])->middleware('throttle:120,1')->name('admin.quick-quizzes.index');
    Route::get('/quiz-kilat/cari-soal', [QuickQuizManagementController::class,'searchQuestions'])->middleware('throttle:90,1')->name('admin.quick-quizzes.search');
    Route::get('/quiz-kilat/buat', [QuickQuizManagementController::class,'create'])->name('admin.quick-quizzes.create');
    Route::post('/quiz-kilat', [QuickQuizManagementController::class,'store'])->middleware('throttle:20,1')->name('admin.quick-quizzes.store');
    Route::get('/quiz-kilat/{quiz}/edit', [QuickQuizManagementController::class,'edit'])->name('admin.quick-quizzes.edit');
    Route::put('/quiz-kilat/{quiz}', [QuickQuizManagementController::class,'update'])->middleware('throttle:20,1')->name('admin.quick-quizzes.update');
    Route::post('/quiz-kilat/{quiz}/revisi', [QuickQuizManagementController::class,'startRevision'])->middleware('throttle:10,1')->name('admin.quick-quizzes.revision.start');
    Route::post('/quiz-kilat/{quiz}/terbit', [QuickQuizManagementController::class,'publish'])->middleware('throttle:10,1')->name('admin.quick-quizzes.publish');
    Route::post('/quiz-kilat/{quiz}/arsip', [QuickQuizManagementController::class,'archive'])->middleware('throttle:10,1')->name('admin.quick-quizzes.archive');
    // QQ quota is a benefit of THREE business member plans, never a per-quiz grant.
    // Participant attempt routes remain unavailable until QQ-CORE-02.
    Route::get('/quiz-kilat-kuota', [QuickQuizQuotaController::class,'index'])->name('admin.qq-quota.index');
    Route::put('/quiz-kilat-kuota/paket/{plan}', [QuickQuizQuotaController::class,'update'])
        ->middleware('throttle:10,1')->name('admin.qq-quota.update');
    Route::get('/keamanan', [AdminSecurityController::class, 'edit'])->name('admin.security.edit');
    Route::post('/keamanan/password', [AdminSecurityController::class, 'changePassword'])->middleware('throttle:5,1')->name('admin.security.password');
    // S6.1: Participant account directory. Read access for admins; status operations require Superadmin.
    Route::get('/peserta', [ParticipantManagementController::class, 'index'])->middleware('throttle:120,1')->name('admin.participants.index');
    Route::get('/peserta/{user}', [ParticipantManagementController::class, 'show'])->name('admin.participants.show');
    Route::post('/peserta/{user}/paket', [ParticipantManagementController::class, 'changePlan'])
        ->middleware('throttle:10,1')->name('admin.participants.plan');
    Route::post('/peserta/{user}/status', [ParticipantManagementController::class, 'changeStatus'])->middleware('throttle:10,1')->name('admin.participants.status');
    Route::get('/registrasi', [RegistrationController::class, 'index'])->name('registration.index');
    Route::get('/registrasi/{user}', [RegistrationController::class, 'show'])->name('registration.show');
    Route::post('/registrasi/{user}/keputusan', [RegistrationController::class, 'decide'])->middleware('throttle:30,1')->name('registration.decide');
    Route::get('/soal', [QuestionManagementController::class, 'index'])->middleware('throttle:120,1')->name('questions.admin.index');
    Route::get('/soal/buat', [QuestionManagementController::class, 'create'])->name('questions.admin.create');
    Route::post('/soal', [QuestionManagementController::class, 'store'])->middleware('throttle:30,1')->name('questions.admin.store');
    Route::get('/soal/{question}/edit', [QuestionManagementController::class, 'edit'])->name('questions.admin.edit');
    Route::put('/soal/{question}', [QuestionManagementController::class, 'update'])->middleware('throttle:30,1')->name('questions.admin.update');
    Route::post('/soal/{question}/publish', [QuestionManagementController::class, 'publish'])->middleware('throttle:15,1')->name('questions.admin.publish');
    Route::post('/soal/{question}/discard-draft', [QuestionManagementController::class, 'discardDraft'])->middleware('throttle:15,1')->name('questions.admin.discard-draft');
    Route::post('/soal/{question}/archive', [QuestionManagementController::class, 'archive'])->middleware('throttle:15,1')->name('questions.admin.archive');
    // S4.2: Subject and topic catalog.
    Route::get('/materi', [SubjectCatalogController::class, 'index'])->name('subjects.admin.index');
    Route::post('/materi', [SubjectCatalogController::class, 'store'])->middleware('throttle:30,1')->name('subjects.admin.store');
    Route::get('/materi/{subject}', [SubjectCatalogController::class, 'show'])->name('subjects.admin.show');
    Route::put('/materi/{subject}', [SubjectCatalogController::class, 'update'])->middleware('throttle:30,1')->name('subjects.admin.update');
    Route::delete('/materi/{subject}', [SubjectCatalogController::class, 'destroy'])->middleware('throttle:15,1')->name('subjects.admin.destroy');
    Route::post('/materi/{subject}/topik', [SubjectCatalogController::class, 'storeTopic'])->middleware('throttle:30,1')->name('subjects.admin.topics.store');
    Route::put('/topik/{topic}', [SubjectCatalogController::class, 'updateTopic'])->middleware('throttle:30,1')->name('subjects.admin.topics.update');
    Route::delete('/topik/{topic}', [SubjectCatalogController::class, 'destroyTopic'])->middleware('throttle:15,1')->name('subjects.admin.topics.destroy');
    // S4.3: Immutable published version references; participant CAT is S5.
    Route::get('/tryout', [TryoutManagementController::class, 'index'])->name('tryouts.admin.index');
    Route::get('/tryout/cari-soal', [TryoutManagementController::class, 'searchQuestions'])->middleware('throttle:90,1')->name('tryouts.admin.search');
    Route::get('/tryout/buat', [TryoutManagementController::class, 'create'])->name('tryouts.admin.create');
    Route::post('/tryout', [TryoutManagementController::class, 'store'])->middleware('throttle:30,1')->name('tryouts.admin.store');
    Route::get('/tryout/{package}/edit', [TryoutManagementController::class, 'edit'])->name('tryouts.admin.edit');
    Route::put('/tryout/{package}', [TryoutManagementController::class, 'update'])->middleware('throttle:30,1')->name('tryouts.admin.update');
    Route::post('/tryout/{package}/publish', [TryoutManagementController::class, 'publish'])->middleware('throttle:15,1')->name('tryouts.admin.publish');
    Route::post('/tryout/{package}/archive', [TryoutManagementController::class, 'archive'])->middleware('throttle:15,1')->name('tryouts.admin.archive');
});

});
