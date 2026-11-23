<?php
use App\Http\Controllers\Questions\QuestionMediaManagementController;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FreePracticeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\PublicSeoSitemapController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\Questions\QuestionManagementController;
use App\Http\Controllers\Questions\QuestionImportController;
use App\Http\Controllers\Admin\SubjectCatalogController;
use App\Http\Controllers\Admin\TryoutManagementController;
use App\Http\Controllers\Admin\ParticipantManagementController;
use App\Http\Controllers\Admin\ParticipantInvitationController;
use App\Http\Controllers\Admin\AdminSecurityController;
use App\Http\Controllers\Admin\AccountEmailController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\PackageCatalogController;
use App\Http\Controllers\Member\PackageUpgradeController;
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
use App\Http\Controllers\Admin\BulkQuestionAssignmentController;
use App\Http\Controllers\Member\MemberClassMediaController;
use App\Http\Controllers\Member\MemberClassPracticeController;
use App\Http\Controllers\Member\MemberQuickQuizController;
use App\Http\Controllers\Member\MemberQuickQuizAnalyticsController;
use App\Http\Controllers\Member\MemberDomainAnalyticsController;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAdminSessionVersion;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

Route::middleware(AddSecurityHeaders::class)->group(function () {
Route::get('/sitemap.xml', PublicSeoSitemapController::class)->name('seo.sitemap');
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

// Authenticated provider callback must remain outside guest/auth middleware to support both login and manual linking.
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:15,1')->name('google.callback');
Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware('throttle:10,1')->name('google.redirect');
    Route::get('/auth/google/lengkapi', [GoogleAuthController::class, 'onboarding'])->name('google.onboarding');
    Route::post('/auth/google/lengkapi', [GoogleAuthController::class, 'store'])->middleware('throttle:5,1')->name('google.onboarding.store');
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
    // ADM-USERS-01: owner-only consent after invitation password reset. Never a public registration shortcut.
    Route::post('/aktivasi/undangan', [ParticipantInvitationController::class, 'accept'])
        ->middleware('throttle:5,1')->name('participant.invitation.accept');
    Route::middleware([EnsureActiveAccount::class, EnsureRole::class.':participant'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'participant'])->name('dashboard');
        Route::post('/paket/{plan}/ajukan', [PackageUpgradeController::class, 'store'])->middleware('throttle:5,1')->name('member.packages.request.store');
        Route::get('/paket/pengajuan/{upgrade}', [PackageUpgradeController::class, 'show'])->name('member.packages.request');
        // ANL-DOMAIN-01: read-only hub; routes for individual domain histories remain intact.
        Route::get('/analisis-kemampuan', [MemberDomainAnalyticsController::class, 'index'])->name('member.analytics.index');
        // S7: private participant profile and manually enrolled classes.
        Route::get('/profil', [MemberProfileController::class,'edit'])->name('member.profile.edit');
        Route::post('/profil/hubungkan-google', [GoogleAuthController::class, 'link'])->middleware('throttle:3,1')->name('google.link');
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
        // QQ-CORE-02: private participant Quiz Kilat. Independent from CAT and Class Practice.
        Route::prefix('quiz-kilat')->group(function () {
            Route::get('/', [MemberQuickQuizController::class,'index'])->name('member.quick-quizzes.index');
            Route::get('/riwayat', [MemberQuickQuizController::class,'history'])->name('member.quick-quizzes.history');
            // QQ-ANALYTICS-01: completed-only historical snapshots; before the /{quiz} catch-all.
            Route::get('/analisis', [MemberQuickQuizAnalyticsController::class,'index'])->name('member.quick-quizzes.analytics');
            Route::get('/sesi/{attempt}', [MemberQuickQuizController::class,'take'])->name('member.quick-quizzes.take');
            Route::post('/sesi/{attempt}/jawab', [MemberQuickQuizController::class,'answer'])->middleware('throttle:120,1')->name('member.quick-quizzes.answer');
            Route::post('/sesi/{attempt}/lanjut', [MemberQuickQuizController::class,'next'])->middleware('throttle:120,1')->name('member.quick-quizzes.next');
            Route::get('/sesi/{attempt}/hasil', [MemberQuickQuizController::class,'result'])->name('member.quick-quizzes.result');
            Route::get('/{quiz}', [MemberQuickQuizController::class,'show'])->name('member.quick-quizzes.show');
            Route::post('/{quiz}/mulai', [MemberQuickQuizController::class,'start'])->middleware('throttle:10,1')->name('member.quick-quizzes.start');
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
    // QQ quota policy belongs to each dynamic member plan, never a per-quiz grant.
    // Participant attempt routes remain unavailable until QQ-CORE-02.
    Route::get('/quiz-kilat-kuota', [QuickQuizQuotaController::class,'index'])->name('admin.qq-quota.index');
    Route::put('/quiz-kilat-kuota/paket/{plan}', [QuickQuizQuotaController::class,'update'])
        ->middleware('throttle:10,1')->name('admin.qq-quota.update');
    Route::get('/keamanan', [AdminSecurityController::class, 'edit'])->name('admin.security.edit');
    // SITE-SETTINGS-01: non-secret public presentation only; superadmin guarded in controller.
    // PKG-CATALOG-01: dynamic identities, versioned public content, manual requests.
    Route::get('/paket', [PackageCatalogController::class, 'index'])->name('admin.packages.index');
    Route::post('/paket', [PackageCatalogController::class, 'store'])->middleware('throttle:10,1')->name('admin.packages.store');
    // Dedicated catalog ordering; never accept paginated DataTables rows as a complete order.
    Route::get('/paket/urutan', [PackageCatalogController::class, 'ordering'])->name('admin.packages.ordering');
    Route::post('/paket/urutan', [PackageCatalogController::class, 'saveOrdering'])
        ->middleware('throttle:10,1')->name('admin.packages.ordering.save');
    Route::get('/paket/pengajuan', [PackageCatalogController::class, 'requests'])->name('admin.packages.requests');
    Route::post('/paket/pengajuan/{upgrade}/keputusan', [PackageCatalogController::class, 'decide'])->middleware('throttle:10,1')->name('admin.packages.requests.decide');
    Route::get('/paket/{plan}', [PackageCatalogController::class, 'show'])->name('admin.packages.show');
    Route::post('/paket/{plan}/pratinjau', [PackageCatalogController::class, 'preview'])->middleware('throttle:30,1')->name('admin.packages.preview');
    Route::put('/paket/{plan}/draft', [PackageCatalogController::class, 'updateDraft'])->middleware('throttle:20,1')->name('admin.packages.draft.update');
    Route::post('/paket/{plan}/revisi', [PackageCatalogController::class, 'revision'])->middleware('throttle:10,1')->name('admin.packages.revision');
    Route::post('/paket/{plan}/terbit', [PackageCatalogController::class, 'publish'])->middleware('throttle:10,1')->name('admin.packages.publish');
    Route::post('/paket/{plan}/batalkan-draft', [PackageCatalogController::class, 'discard'])->middleware('throttle:10,1')->name('admin.packages.discard');
    Route::post('/paket/{plan}/ketersediaan', [PackageCatalogController::class, 'availability'])->middleware('throttle:10,1')->name('admin.packages.availability');
    Route::get('/website', [SiteSettingsController::class, 'index'])->name('admin.site-settings.index');
    Route::post('/website/pratinjau', [SiteSettingsController::class, 'preview'])->middleware('throttle:20,1')->name('admin.site-settings.preview');
    Route::put('/website', [SiteSettingsController::class, 'update'])->middleware('throttle:10,1')->name('admin.site-settings.update');
    Route::get('/email', [AccountEmailController::class, 'index'])->name('admin.account-emails.index');
    Route::get('/email/pratinjau/{kind}', [AccountEmailController::class, 'preview'])->name('admin.account-emails.preview');
    Route::post('/email/uji', [AccountEmailController::class, 'test'])->middleware('throttle:3,1')->name('admin.account-emails.test');
    Route::post('/email/{outbox}/antrekan', [AccountEmailController::class, 'retry'])->middleware('throttle:5,1')->name('admin.account-emails.retry');
    Route::post('/email/{outbox}/kirim-ulang', [AccountEmailController::class, 'resend'])->middleware('throttle:3,1')->name('admin.account-emails.resend');
    Route::post('/keamanan/password', [AdminSecurityController::class, 'changePassword'])->middleware('throttle:5,1')->name('admin.security.password');
    // S6.1: Participant account directory. Read access for admins; status operations require Superadmin.
    Route::get('/peserta', [ParticipantManagementController::class, 'index'])->middleware('throttle:120,1')->name('admin.participants.index');
    Route::get('/peserta/tambah', [ParticipantInvitationController::class, 'create'])->name('admin.participants.create');
    Route::post('/peserta', [ParticipantInvitationController::class, 'store'])->middleware('throttle:10,1')->name('admin.participants.store');
    Route::post('/peserta/{user}/undangan/kirim-ulang', [ParticipantInvitationController::class, 'resend'])
        ->middleware('throttle:3,1')->name('admin.participants.invitation.resend');
    Route::get('/peserta/{user}', [ParticipantManagementController::class, 'show'])->name('admin.participants.show');
    Route::post('/peserta/{user}/paket', [ParticipantManagementController::class, 'changePlan'])
        ->middleware('throttle:10,1')->name('admin.participants.plan');
    Route::post('/peserta/{user}/status', [ParticipantManagementController::class, 'changeStatus'])->middleware('throttle:10,1')->name('admin.participants.status');
    Route::get('/registrasi', [RegistrationController::class, 'index'])->name('registration.index');
    Route::get('/registrasi/{user}', [RegistrationController::class, 'show'])->name('registration.show');
    Route::post('/registrasi/{user}/keputusan', [RegistrationController::class, 'decide'])->middleware('throttle:30,1')->name('registration.decide');
    // Q-BULK-ASSIGN-01: guarded draft-only append; editor services remain authoritative.
    Route::get('/penugasan-massal/{domain}/{target}/cari', [BulkQuestionAssignmentController::class,'search'])
        ->where('domain','cat|qq|kelas')->middleware('throttle:90,1')->name('admin.bulk-assignment.search');
    Route::post('/penugasan-massal/{domain}/{target}/pratinjau', [BulkQuestionAssignmentController::class,'preview'])
        ->where('domain','cat|qq|kelas')->middleware('throttle:30,1')->name('admin.bulk-assignment.preview');
    Route::post('/penugasan-massal/{domain}/{target}/konfirmasi', [BulkQuestionAssignmentController::class,'confirm'])
        ->where('domain','cat|qq|kelas')->middleware('throttle:10,1')->name('admin.bulk-assignment.confirm');
    // Q-BULK-IMPORT-01: specific routes must precede /soal/{question}.
    Route::get('/soal/impor', [QuestionImportController::class, 'index'])->name('questions.import.index');
    Route::post('/soal/impor', [QuestionImportController::class, 'upload'])->middleware('throttle:10,1')->name('questions.import.upload');
    Route::get('/soal/impor/template', [QuestionImportController::class, 'template'])->name('questions.import.template');
    Route::get('/soal/impor/referensi', [QuestionImportController::class, 'references'])->name('questions.import.references');
    Route::get('/soal/impor/{batch}', [QuestionImportController::class, 'show'])->name('questions.import.show');
    Route::get('/soal/impor/{batch}/kesalahan', [QuestionImportController::class, 'errors'])->name('questions.import.errors');
    Route::post('/soal/impor/{batch}/konfirmasi', [QuestionImportController::class, 'confirm'])->middleware('throttle:5,1')->name('questions.import.confirm');
    Route::delete('/soal/impor/{batch}', [QuestionImportController::class, 'discard'])->middleware('throttle:10,1')->name('questions.import.discard');
    Route::get('/soal', [QuestionManagementController::class, 'index'])->middleware('throttle:120,1')->name('questions.admin.index');
    Route::get('/soal/buat', [QuestionManagementController::class, 'create'])->name('questions.admin.create');
    Route::post('/soal', [QuestionManagementController::class, 'store'])->middleware('throttle:30,1')->name('questions.admin.store');
    Route::get('/soal/{question}/edit', [QuestionManagementController::class, 'edit'])->name('questions.admin.edit');
    Route::put('/soal/{question}', [QuestionManagementController::class, 'update'])->middleware('throttle:30,1')->name('questions.admin.update');
    Route::post('/soal/{question}/publish', [QuestionManagementController::class, 'publish'])->middleware('throttle:15,1')->name('questions.admin.publish');
    Route::post('/soal/{question}/discard-draft', [QuestionManagementController::class, 'discardDraft'])->middleware('throttle:15,1')->name('questions.admin.discard-draft');
    Route::post('/soal/{question}/archive', [QuestionManagementController::class, 'archive'])->middleware('throttle:15,1')->name('questions.admin.archive');
    // FIG-P2: admin-only private question media; never use these routes from participant contexts.
    Route::post('/soal/{question}/mulai-revisi', [QuestionManagementController::class, 'startRevision'])->middleware('throttle:15,1')->name('questions.admin.start-revision');
    Route::post('/soal/{question}/media', [QuestionMediaManagementController::class, 'upload'])->middleware('throttle:20,1')->name('questions.admin.media.upload');
    Route::post('/soal/{question}/media/urut', [QuestionMediaManagementController::class, 'reorder'])->middleware('throttle:20,1')->name('questions.admin.media.reorder');
    Route::post('/soal/{question}/media/{media}/lepas', [QuestionMediaManagementController::class, 'detach'])->middleware('throttle:20,1')->name('questions.admin.media.detach');
    Route::post('/soal/{question}/media/{media}/metadata', [QuestionMediaManagementController::class, 'metadata'])->middleware('throttle:20,1')->name('questions.admin.media.metadata');
    Route::get('/soal/{question}/media/{media}/preview', [QuestionMediaManagementController::class, 'preview'])->middleware('throttle:90,1')->name('questions.admin.media.preview');
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
