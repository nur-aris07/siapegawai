<?php
namespace App\Console\Commands;

use App\Models\ClassEnrollment;
use App\Models\ClassPractice;
use App\Models\ClassPracticeItem;
use App\Models\LearningClass;
use App\Models\MemberPlan;
use App\Models\QuickQuizQuotaPolicy;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\QuestionVersionMedia;
use App\Models\QuickQuiz;
use App\Models\QuickQuizVersion;
use App\Models\QuickQuizVersionItem;
use App\Models\Topic;
use App\Models\TryoutPackage;
use App\Models\TryoutPackageItem;
use App\Models\User;
use App\Services\QuestionEditorialMediaCoordinator;
use App\Services\QuestionEditorialService;
use App\Services\QuestionMediaStorageService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Intentionally opt-in and TEST-DB-ONLY. Not invoked by the source installer. */
final class FigP3DemoFixtures extends Command
{
    protected $signature = 'siapegawai:fig-p3-demo {--dry-run : Preview test-DB fixture without mutations} {--apply : Insert isolated QA fixtures} {--verify : Read-only fixture verification} {--admin= : Existing active superadmin id} {--participant= : Existing active participant id} {--bootstrap-users : Create two named QA-only login accounts if missing, only in testing DB} {--qa-quota : Configure limited QQ quota for PEJUANG in testing DB, never grant membership}';
    protected $description = 'FIG-P3: QA-only synthetic diagrams and 30-question QQ / 3-question CAT & Class fixtures (testing DB only)';

    private const ADMIN_EMAIL = 'figp3-qa-admin@example.test';
    private const PARTICIPANT_EMAIL = 'figp3-qa-participant@example.test';
    private const QQ = 'FIGP3-QA-QQ-01';
    private const CAT = 'FIGP3-QA-CAT-01';
    private const CLASS_SLUG = 'figp3-qa-kelas-01';
    private const PRACTICE = 'FIG-P3 QA — Latihan Kelas (3 soal)';

    public function handle(QuestionEditorialService $editorial, QuestionEditorialMediaCoordinator $coord, QuestionMediaStorageService $storage): int
    {
        if (!app()->environment('testing') || DB::connection()->getDatabaseName() !== 'siapegawai_testing'
            || DB::selectOne('select database() as db')->db !== 'siapegawai_testing') {
            $this->error('FAIL-CLOSED: command hanya boleh di APP_ENV=testing dan database siapegawai_testing.');
            return self::FAILURE;
        }
        if ((int)(bool)$this->option('dry-run') + (int)(bool)$this->option('apply') + (int)(bool)$this->option('verify') !== 1) {
            $this->error('Pilih tepat satu --dry-run, --apply, atau --verify. Tidak ada operasi default.');
            return self::FAILURE;
        }
        if ($this->option('verify')) return $this->verify();
        $bootstrap=(bool)$this->option('bootstrap-users');
        $quota=(bool)$this->option('qa-quota');
        if ($bootstrap && ($this->option('admin') !== null || $this->option('participant') !== null)) {
            $this->error('Gunakan --bootstrap-users TANPA --admin/--participant, atau berikan kedua ID existing tanpa --bootstrap-users.');
            return self::FAILURE;
        }
        $admin=$bootstrap ? User::query()->where('email', self::ADMIN_EMAIL)->first()
            : User::query()->find((int)$this->option('admin'));
        $participant=$bootstrap ? User::query()->where('email', self::PARTICIPANT_EMAIL)->first()
            : User::query()->find((int)$this->option('participant'));
        foreach ([[$admin,'superadmin'],[$participant,'participant']] as [$user,$role]) {
            if ($user && ($user->role !== $role || $user->status !== 'active')) {
                $this->error('Akun yang ditemukan tidak sesuai role/status aktif. Menolak perubahan akun existing.');
                return self::FAILURE;
            }
        }
        if (!$bootstrap && (!$admin || !$participant)) {
            $this->error('Belum ada admin/peserta aktif di DB testing. Gunakan --bootstrap-users untuk membuat akun QA atau --admin=ID --participant=ID yang benar.');
            return self::FAILURE;
        }
        if (Question::query()->where('code','like','FIGP3-QA-Q%')->exists()
            || QuickQuiz::query()->where('code',self::QQ)->exists()
            || TryoutPackage::query()->where('code',self::CAT)->exists()
            || LearningClass::query()->where('slug',self::CLASS_SLUG)->exists()) {
            $this->line('Fixture prefix FIGP3 sudah ada; hanya --verify yang bisa menentukan apakah lengkap. Tidak overwrite.');
            return $this->verify();
        }
        $topic=Topic::query()->whereHas('subject',fn($q)=>$q->where('code','TIU'))
            ->where('status','active')->with('subject')->first();
        if (!$topic && !$bootstrap) {
            $this->error('Topic TIU aktif tidak ditemukan. Gunakan --bootstrap-users pada DB testing kosong, atau jalankan seeder kategori existing dengan izin terpisah.');
            return self::FAILURE;
        }
        foreach (['pattern-stem.png','option-a.png','option-b.png','option-c.png','option-d.png',
            'chart-stem.png','explanation-pattern.png'] as $image) {
            $path=base_path('database/fixtures/fig-p3/'.$image);
            if (!is_file($path) || @getimagesize($path) === false) {
                $this->error('Diagram fixture hilang/rusak: '.$image); return self::FAILURE;
            }
        }
        if ($quota && !MemberPlan::query()->where('code','PEJUANG')->exists()) {
            $this->error('MemberPlan PEJUANG tidak tersedia; cek migration testing. Tidak membuat paket/entitlement.');
            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->info('DRY RUN PASS | DB siapegawai_testing | tidak ada write.');
            $this->line('Rencana: 30 soal/8 media, QQ 30, CAT 3, kelas 3 + enrollment.');
            $this->line('Akun QA '.($bootstrap ? 'dibuat bila belum ada; credentials acak dicetak sekali saat apply' : 'menggunakan ID existing').'.');
            $this->line('Topic TIU '.($topic ? 'sudah tersedia' : 'dibuat oleh seeder existing saat apply').'.');
            $this->line('QQ quota '.($quota ? 'testing-only, 3 attempt/hari untuk PEJUANG bila belum ada kebijakan' : 'tetap sesuai konfigurasi existing').'.');
            return self::SUCCESS;
        }
        $generatedSecrets=[];
        $previous=config('question_media.figural_publish_qa');
        config()->set('question_media.figural_publish_qa',true);
        try {
            DB::transaction(function () use (&$admin,&$participant,&$topic,&$generatedSecrets,$bootstrap,$quota,$editorial,$coord,$storage): void {
                if ($bootstrap) {
                    foreach ([
                        ['admin',self::ADMIN_EMAIL,'FIG-P3 QA Superadmin','superadmin'],
                        ['participant',self::PARTICIPANT_EMAIL,'FIG-P3 QA Peserta','participant'],
                    ] as [$kind,$email,$name,$role]) {
                        $existing=User::query()->where('email',$email)->first();
                        if ($existing) {
                            if ($existing->role !== $role || $existing->status !== 'active') {
                                throw new \RuntimeException('Akun QA existing tidak valid: '.$email);
                            }
                            $user=$existing;
                        } else {
                            $password=Str::random(24).'7!';
                            $user=User::query()->forceCreate([
                                'name'=>$name,'email'=>$email,'password'=>$password,
                                'role'=>$role,'status'=>'active', 'email_verified_at'=>now(),
                                'activated_at'=>now(),
                            ]);
                            $generatedSecrets[$kind]=['email'=>$email,'password'=>$password];
                        }
                        if ($kind === 'admin') $admin=$user; else $participant=$user;
                    }
                }
                if (!$topic) {
                    // Only inside explicit testing-only --apply --bootstrap-users.
                    app(SiapegawaiSubjectsSeeder::class)->run();
                    $topic=Topic::query()->whereHas('subject',fn($q)=>$q->where('code','TIU'))
                        ->where('status','active')->with('subject')->firstOrFail();
                }
                if ($quota) {
                    $plan=MemberPlan::query()->where('code','PEJUANG')->firstOrFail();
                    $existing=QuickQuizQuotaPolicy::query()->where('member_plan_id',$plan->id)->first();
                    if (!$existing) {
                        QuickQuizQuotaPolicy::query()->create([
                            'code'=>'FIGP3-QA-PEJUANG','name'=>'FIG-P3 QA / hanya testing DB',
                            'scope'=>'plan','daily_limit'=>3,'unlimited'=>false,
                            'status'=>'active','member_plan_id'=>$plan->id,'approved_by'=>$admin->id,
                        ]);
                    } elseif ($existing->scope !== 'plan' || $existing->status !== 'active'
                        || (!$existing->unlimited && ($existing->daily_limit ?? 0) < 1)) {
                        throw new \RuntimeException('Kebijakan kuota PEJUANG existing tidak siap; tidak overwrite.');
                    }
                }

                $versions=[];
                for ($i=1;$i<=30;$i++) {
                    $q=Question::query()->create([
                        'topic_id'=>$topic->id, 'code'=>sprintf('FIGP3-QA-Q%02d',$i),
                        'status'=>'draft','created_by'=>$admin->id,
                    ]);
                    $imageOnly = $i===1;
                    $text = $i===2 ? 'Dari diagram batang, jumlah buku pada Rabu adalah ....'
                        : ($i===1 ? '' : ($i===3 ? 'Perhatikan pola berulang berikut.' : "Soal QA fungsional {$i}: hitung {$i} + 2."));
                    $explanation = $i===1 ? '' : ($i===2 ? 'Batang Rabu menunjukkan nilai 7.'
                        : ($i===3 ? 'Pola pada gambar berulang secara teratur.' : "Hasil {$i} ditambah 2 adalah ".($i+2).'.'));
                    $v=QuestionVersion::query()->create([
                        'question_id'=>$q->id,'version_number'=>1,'body'=>$text,
                        'explanation'=>$explanation,'review_status'=>'draft',
                    ]);
                    foreach (['A','B','C','D'] as $order=>$label) {
                        $answer = $i===1 ? '' : ($i===2 ? (string)(4+$order) : ($i===3 ? $label : (string)($i+$order)));
                        QuestionOption::query()->create([
                            'question_version_id'=>$v->id,'label'=>$label,'body'=>$answer,
                            'is_correct'=>($i===1 ? $label==='A' : ($i===2 ? $label==='D' : ($i===3 ? $label==='A' : $label==='C'))),
                            'sort_order'=>$order+1,
                        ]);
                    }
                    if ($i===1) {
                        $this->media($v,$storage,'pattern-stem.png','stem',1,'Lingkaran, persegi, segitiga membentuk pola berulang');
                        foreach (['A','B','C','D'] as $label) {
                            $this->media($v,$storage,'option-'.strtolower($label).'.png','option_'.$label,1,'Pilihan bentuk '.$label);
                        }
                        $this->media($v,$storage,'explanation-pattern.png','explanation',1,'Pola berulang dari lingkaran ke persegi dan segitiga');
                    } elseif ($i===2) {
                        $this->media($v,$storage,'chart-stem.png','stem',1,'Diagram batang Senin 3 Selasa 5 Rabu 7');
                    } elseif ($i===3) {
                        $this->media($v,$storage,'pattern-stem.png','stem',1,'Urutan tiga bentuk geometri');
                    }
                    // Real P2 publish validator (QA exception is strictly testing + testing DB).
                    $editorial->publish($q,$admin,$coord->editToken($q));
                    $versions[]=$v->id;
                }
                $qq=QuickQuiz::query()->create(['code'=>self::QQ,'status'=>'draft','created_by'=>$admin->id]);
                $qv=QuickQuizVersion::query()->create(['quick_quiz_id'=>$qq->id,'version_number'=>1,
                    'status'=>'draft','title'=>'QA FIG-P3: Quiz Kilat Figural (30 soal)',
                    'description'=>'UJI INTERNAL / BUKAN KONTEN PILOT','seconds_per_question'=>60]);
                foreach ($versions as $index=>$versionId) QuickQuizVersionItem::query()->create([
                    'quick_quiz_version_id'=>$qv->id,'question_version_id'=>$versionId,'sort_order'=>$index+1,
                    'subject_code_snapshot'=>'TIU','subject_name_snapshot'=>'Tes Intelegensia Umum',
                    'topic_name_snapshot'=>$topic->name,
                ]);
                $qv->update(['status'=>'published','published_at'=>now(),'published_by'=>$admin->id]);
                $qq->update(['status'=>'published','current_version_id'=>$qv->id]);
                $cat=TryoutPackage::query()->create([
                    'code'=>self::CAT,'name'=>'QA FIG-P3: CAT Figural (3 soal)',
                    'description'=>'UJI INTERNAL / BUKAN TRYOUT KOMERSIAL','duration_minutes'=>20,
                    'max_attempts'=>3,'status'=>'published','published_at'=>now(),'created_by'=>$admin->id,
                ]);
                foreach (array_slice($versions,0,3) as $index=>$versionId) TryoutPackageItem::query()->create([
                    'tryout_package_id'=>$cat->id,'question_version_id'=>$versionId,'sort_order'=>$index+1,
                    'subject_id_snapshot'=>$topic->subject_id,'subject_code_snapshot'=>'TIU',
                    'subject_name_snapshot'=>'Tes Intelegensia Umum','topic_id_snapshot'=>$topic->id,
                    'topic_name_snapshot'=>$topic->name,
                ]);
                $class=LearningClass::query()->create([
                    'slug'=>self::CLASS_SLUG,'title'=>'QA FIG-P3 — Kelas Figural',
                    'description'=>'Kelas terbatas khusus testing GET media dan latihan figural.',
                    'syllabus'=>'Demo tiga soal; bukan materi akademik tervalidasi.',
                    'status'=>'open','capacity'=>5,'created_by'=>$admin->id,
                ]);
                $practice=ClassPractice::query()->create([
                    'class_id'=>$class->id,'title'=>self::PRACTICE,
                    'description'=>'3 soal figural dari question versions yang sama.','duration_minutes'=>20,
                    'max_attempts'=>3,'sort_order'=>1,'status'=>'published','published_at'=>now(),
                    'created_by'=>$admin->id,
                ]);
                foreach (array_slice($versions,0,3) as $index=>$versionId) ClassPracticeItem::query()->create([
                    'class_practice_id'=>$practice->id,'question_version_id'=>$versionId,
                    'sort_order'=>$index+1,'subject_id_snapshot'=>$topic->subject_id,
                    'subject_code_snapshot'=>'TIU','subject_name_snapshot'=>'Tes Intelegensia Umum',
                    'topic_id_snapshot'=>$topic->id,'topic_name_snapshot'=>$topic->name,
                ]);
                ClassEnrollment::query()->create(['class_id'=>$class->id,'user_id'=>$participant->id,
                    'status'=>'active','approved_by'=>$admin->id,'approved_at'=>now()]);
            },1);
        } catch (Throwable $error) {
            $this->error('Fixture DB rollback; kemungkinan ada orphan media immutable yang harus diaudit. '.$error->getMessage());
            return self::FAILURE;
        } finally { config()->set('question_media.figural_publish_qa',$previous); }
        $this->info('PASS: 30 question versions, 1 QQ (minimum 30), 1 CAT (3), 1 Kelas + 1 Latihan (3), enrollment testing saja.');
        foreach ($generatedSecrets as $identity) {
            $this->warn('TEST-ONLY LOGIN | '.$identity['email'].' | password='.$identity['password']);
        }
        if ($generatedSecrets) $this->warn('Simpan credentials QA ini secara privat; hanya dicetak satu kali, tidak di-commit atau masuk log aplikasi.');
        $this->line('QA admin id='.$admin->id.' | peserta id='.$participant->id);
        $this->warn('Tidak ada attempt/pembayaran/grant buatan. QQ quota hanya ada bila --qa-quota dipilih atau sudah tersedia sebelumnya.');
        return $this->verify();
    }

    private function media(QuestionVersion $version, QuestionMediaStorageService $storage, string $filename,
        string $slot, int $position, string $alt): void
    {
        $path=base_path('database/fixtures/fig-p3/'.$filename);
        if (!is_file($path)) throw new \RuntimeException('Diagram test tidak ada: '.$filename);
        $inspect=$storage->inspect(new UploadedFile($path,$filename,'image/png',null,true));
        $key=$storage->storeImmutable($inspect);
        $model=new QuestionVersionMedia();
        $model->question_version_id=$version->id;$model->slot=$slot;$model->position=$position;
        $model->object_key=$key;$model->sha256=$inspect['sha256'];$model->mime_type='image/png';
        $model->byte_size=$inspect['byte_size'];$model->width_px=$inspect['width_px'];
        $model->height_px=$inspect['height_px'];$model->alt_text=$alt;$model->caption=null;
        $model->save();
    }

    private function verify(): int
    {
        $questions=Question::query()->where('code','like','FIGP3-QA-Q%')->count();
        $qq=QuickQuiz::query()->where('code',self::QQ)->first();
        $cat=TryoutPackage::query()->where('code',self::CAT)->first();
        $class=LearningClass::query()->where('slug',self::CLASS_SLUG)->first();
        $versionIds=QuestionVersion::query()->whereHas('question',fn($q)=>$q->where('code','like','FIGP3-QA-Q%'))->pluck('id');
        $media=QuestionVersionMedia::query()->whereIn('question_version_id',$versionIds)->get();
        $storage=app(QuestionMediaStorageService::class);
        $valid=$questions===30 && $qq?->status==='published' && $qq->currentVersion?->items()->count()===30
            && $cat?->status==='published' && $cat->items()->count()===3
            && $class?->status==='open' && $class->practices()->where('status','published')->first()?->items()->count()===3
            && ClassEnrollment::query()->where('class_id', $class?->id ?? 0)->where('status','active')->exists()
            && $media->count()===8 && $media->every(fn($m)=>$storage->verify($m->object_key,$m->sha256));
        $this->line(($valid?'PASS':'FAIL').' | questions='.$questions.' | media='.$media->count()
            .' | QQ items='.($qq?->currentVersion?->items()->count() ?? 0)
            .' | CAT items='.($cat?->items()->count() ?? 0));
        return $valid ? self::SUCCESS : self::FAILURE;
    }
}
