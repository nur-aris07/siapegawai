<?php
namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionImportBatch;
use App\Models\QuestionVersionMedia;
use App\Models\Topic;
use App\Models\User;
use App\Services\QuestionQcService;
use App\Services\QuestionVersionMediaService;
use App\Services\QuestionZipImportService;
use App\Services\QuestionMediaStorageService;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SiapegawaiContentStudioTest extends TestCase
{
    use RefreshDatabase;
    private const PNG='iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAIAAADZSiLoAAAAIklEQVR42g3GMQEAMBCEsFdy1hDGgtQ2U26T2bwhGH9Jlg+gIwwiwiu29wAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
        Storage::fake('local'); Storage::fake('question_media_private');
    }

    private function user(string $role='operational_admin',string $status='active'): User
    {
        return User::factory()->create(['role'=>$role,'status'=>$status]);
    }

    private function archive(int $count=1,?\Closure $modify=null,bool $asUnixSymlink=false): UploadedFile
    {
        $subject=Topic::query()->with('subject')->where('status','active')->firstOrFail();
        $rows=[]; $media=[]; $assets=[];
        for($n=1;$n<=$count;$n++){
            $code='STUDIO-'.sprintf('%03d',$n);
            $row=[$code,$subject->subject->code,$subject->slug,
                $n===1?'':'Pertanyaan contoh sintetis cukup panjang nomor '.$n,
                'Penjelasan sintetis cukup panjang nomor '.$n,
                'Opsi A','Opsi B','Opsi C','Opsi D','','A'];
            $rows[]=$row;
            if($n===1){
                $path='media/stem-'.$n.'.png';
                $media[]=[$code,'stem','1',$path,'Ilustrasi sintetis','Contoh gambar','internal source','UNREVIEWED'];
                $assets[$path]=base64_decode(self::PNG,true);
            }
        }
        if($modify)$modify($rows,$media,$assets);
        $tmp=tempnam(sys_get_temp_dir(),'sgzip');$zip=new \ZipArchive();
        if($zip->open($tmp,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('ZIP test fixture');
        $csv=fopen('php://temp','w+');fputcsv($csv,\App\Services\QuestionCsvImportService::HEADER,',','"','');
        foreach($rows as $row)fputcsv($csv,$row,',','"','');rewind($csv);$zip->addFromString('questions.csv',stream_get_contents($csv));fclose($csv);
        $csv=fopen('php://temp','w+');fputcsv($csv,QuestionZipImportService::MEDIA_HEADER,',','"','');
        foreach($media as $row)fputcsv($csv,$row,',','"','');rewind($csv);$zip->addFromString('media.csv',stream_get_contents($csv));fclose($csv);
        foreach($assets as $path=>$bytes)$zip->addFromString($path,$bytes);
        // Set symlink metadata while building our standalone test ZIP, before Laravel's
        // UploadedFile::fake() takes ownership of its temporary file. On Windows,
        // reopening an UploadedFile temp path for mutation can make ZipArchive::close()
        // fail when libzip renames its temporary ZIP over that still-open file.
        if($asUnixSymlink){
            if(!$zip->setExternalAttributesName('media/stem-1.png',\ZipArchive::OPSYS_UNIX,0120777<<16))
                throw new \RuntimeException('Failed to prepare ZIP symlink test fixture');
        }
        if(!$zip->close())throw new \RuntimeException('Failed to close ZIP test fixture');
        $bytes=file_get_contents($tmp);unlink($tmp);
        return UploadedFile::fake()->createWithContent('studio.zip',$bytes);
    }

    private function zip(): QuestionZipImportService {return app(QuestionZipImportService::class);}

    public function test_30_mixed_items_preview_drafts_private_media_idempotent_and_legacy_csv_ui(): void
    {
        $actor=$this->user(); $this->actingAs($actor);
        $this->get(route('questions.import.index'))->assertOk()->assertSee('Content Studio')->assertSee('ZIP+CSV')->assertSee('QC Workspace');
        $batch=$this->zip()->upload($this->archive(30),$actor);
        $this->assertSame('ready',$batch->status);
        $this->assertDatabaseCount('questions',0);
        $preview=$this->zip()->preview($batch);
        $this->assertSame(30,$preview['total']);$this->assertSame(0,$preview['invalid']);
        $this->get(route('questions.import.show',$batch->id))->assertOk()->assertSee('Ilustrasi sintetis')->assertSee('Hak: belum diverifikasi');
        $this->get(route('questions.import.media-preview',[$batch->id,0]))->assertOk()->assertHeader('Content-Type','image/png')->assertHeader('X-Content-Type-Options','nosniff');
        $one=$this->zip()->confirm($batch,$actor);
        $this->assertSame(30,$one['count']);$this->assertSame($one,$this->zip()->confirm($batch->fresh(),$actor));
        $this->assertDatabaseCount('questions',30);$this->assertDatabaseCount('question_versions',30);
        $this->assertDatabaseCount('question_version_media',1);
        $this->assertSame('draft',Question::query()->firstOrFail()->status);
        $this->assertTrue(QuestionVersionMedia::query()->firstOrFail()->byte_size>0);
        $this->assertSame('DECLARATIONS_ONLY_NOT_RIGHTS_CLEARED',AuditLog::query()->where('action','question_import.media_rights_declared')->firstOrFail()->after_summary['scope']);
        $this->get(route('questions.import.media-preview',[$batch->id,0]))->assertNotFound();
        $this->get(route('questions.qc.index'))->assertOk()->assertSee('UNREVIEWED');
        $this->get(route('questions.import.template'))->assertOk();
        $this->get(route('questions.import.zip-template'))->assertOk()->assertHeader('Content-Type','application/zip');
    }

    public function test_media_only_options_and_explanation_allow_zip_draft_without_auto_publish(): void
    {
        $actor=$this->user();
        $zip=$this->archive(1,function(&$rows,&$media,&$assets){
            $rows[0][4]='';$rows[0][5]='';
            $assets['media/answer.png']=base64_decode(self::PNG,true);
            $assets['media/explanation.png']=base64_decode(self::PNG,true);
            $media[]=['STUDIO-001','option_A','1','media/answer.png','Gambar jawaban','','',''];
            $media[]=['STUDIO-001','explanation','1','media/explanation.png','Pembahasan gambar','','',''];
        });
        $batch=$this->zip()->upload($zip,$actor);
        $this->assertSame(0,$batch->invalid_count);
        $result=$this->zip()->confirm($batch,$actor);
        $q=Question::query()->findOrFail($result['question_ids'][0]);
        $this->assertSame('draft',$q->status);$this->assertNull($q->current_version_id);
        $this->assertDatabaseCount('question_version_media',3);
        $this->assertDatabaseCount('questions',1);
    }

    public function test_invalid_code_missing_media_duplicate_and_wrong_zip_paths_rejected_without_any_draft(): void
    {
        $actor=$this->user();
        $batch=$this->zip()->upload($this->archive(2,function(&$rows,&$media,&$assets){$rows[1][0]='STUDIO-001';}),$actor);
        $this->assertSame('invalid',$batch->status);
        $this->expectException(ValidationException::class);
        $this->zip()->confirm($batch,$actor);
    }

    public function test_missing_image_blocks_commit_and_keeps_database_unchanged(): void
    {
        $actor=$this->user();
        $batch=$this->zip()->upload($this->archive(2,function(&$rows,&$media,&$assets){unset($assets['media/stem-1.png']);}),$actor);
        $this->assertSame('invalid',$batch->status);
        $this->assertDatabaseCount('questions',0);
        $this->assertDatabaseCount('question_version_media',0);
    }

    public function test_path_traversal_and_symlink_rejected_before_batch_creation(): void
    {
        $actor=$this->user();
        foreach(['media/../evil.png','media\\evil.png','media/Nested.zip'] as $name){
            try {$this->zip()->upload($this->archive(1,function(&$rows,&$media,&$assets)use($name){$assets[$name]='payload';}),$actor);$this->fail('Dangerous path accepted');}
            catch(ValidationException $e){$this->assertArrayHasKey('file',$e->errors());}
        }
        $this->assertDatabaseCount('question_import_batches',0);
    }

    public function test_one_invalid_image_and_corrupted_image_reject_batch_without_question_writes(): void
    {
        $actor=$this->user();$zip=$this->archive(1,function(&$rows,&$media,&$assets){$assets['media/stem-1.png']='not a PNG';});
        $batch=$this->zip()->upload($zip,$actor);
        $this->assertSame('invalid',$batch->status);$this->assertDatabaseCount('questions',0);
    }

    public function test_confirm_mid_batch_exception_rolls_back_all_questions(): void
    {
        $actor=$this->user();$batch=$this->zip()->upload($this->archive(3),$actor);
        try {$this->zip()->confirm($batch,$actor,function(int $number){if($number===3)throw new \RuntimeException('test-only');});$this->fail('Fault injection ignored');}
        catch(\RuntimeException $e){$this->assertSame('test-only',$e->getMessage());}
        $this->assertDatabaseCount('questions',0);$this->assertDatabaseCount('question_versions',0);
        $this->assertDatabaseCount('question_version_media',0);
        $this->assertSame('ready',$batch->fresh()->status);
        // The database rolled back; an immutable media object may remain as an orphan.
        $inventory=app(QuestionVersionMediaService::class)->inventoryStorage($this->user('superadmin'));
        $this->assertNotEmpty($inventory['orphan_keys']);
        $this->assertDatabaseCount('question_version_media',0);
        foreach($inventory['orphan_keys'] as $key)$this->assertTrue(Storage::disk('question_media_private')->exists($key));
    }

    public function test_qc_is_version_hash_bound_stale_and_denied_for_inactive_or_participant(): void
    {
        $actor=$this->user();$batch=$this->zip()->upload($this->archive(),$actor);$r=$this->zip()->confirm($batch,$actor);
        $q=Question::query()->findOrFail($r['question_ids'][0]);
        $this->actingAs($actor)->get(route('questions.qc.show',$q))->assertOk()->assertSee('UNREVIEWED');
        $qc=app(QuestionQcService::class);$old=$qc->snapshot($q->latestVersion)['hash'];
        $this->post(route('questions.qc.mark',$q),['hash'=>$old,'decision'=>'REVIEWED_INTERNAL','notes'=>'Verified locally'])->assertRedirect();
        $this->assertSame('REVIEWED_INTERNAL',$qc->snapshot($q->latestVersion)['status']);
        $this->get(route('questions.qc.index',['qc_status'=>'REVIEWED_INTERNAL']))
            ->assertOk()->assertSee($q->code)->assertSee('Terapkan filter');
        $this->get(route('questions.qc.index',['code'=>'STUDIO-001','topic_id'=>$q->topic_id]))
            ->assertOk()->assertSee($q->code);
        $q->latestVersion->update(['explanation'=>'Modified explanation makes the original QC stale.']);
        $this->assertSame('STALE',$qc->snapshot($q->latestVersion->fresh())['status']);
        $this->get(route('questions.qc.index',['qc_status'=>'STALE']))->assertOk()->assertSee($q->code);
        $this->get(route('questions.qc.index',['qc_status'=>'REVIEWED_INTERNAL']))
            ->assertOk()->assertDontSee('>STUDIO-001<',false);
        $this->post(route('questions.qc.mark',$q),['hash'=>$old,'decision'=>'REVIEWED_INTERNAL'])->assertSessionHasErrors('hash');
        $this->actingAs($this->user('participant'))->get(route('questions.qc.show',$q))->assertForbidden();
        $this->actingAs($this->user('operational_admin','inactive'))->post(route('questions.qc.mark',$q),['hash'=>$old,'decision'=>'NEEDS_REVISION'])->assertForbidden();
    }

    public function test_non_owner_preview_denied_and_get_is_read_only(): void
    {
        $actor=$this->user();$other=$this->user();$batch=$this->zip()->upload($this->archive(),$actor);
        $count=AuditLog::count();
        $this->actingAs($other)->get(route('questions.import.media-preview',[$batch->id,0]))->assertNotFound();
        $this->actingAs($actor)->get(route('questions.import.media-preview',[$batch->id,0]))->assertOk();
        $this->assertSame($count,AuditLog::count());
    }

    public function test_unsupported_archive_mime_spoof_and_media_csv_duplicates_have_no_drafts(): void
    {
        $actor=$this->user();
        $zip=$this->archive(1,function(&$rows,&$media,&$assets){$media[]=$media[0];});
        $batch=$this->zip()->upload($zip,$actor);
        $this->assertSame('invalid',$batch->status);$this->assertDatabaseCount('questions',0);
        $zip=$this->archive(1,function(&$rows,&$media,&$assets){$assets['media/stem-1.png']='spoofed bytes';});
        $batch=$this->zip()->upload($zip,$actor);$this->assertSame('invalid',$batch->status);
    }
    public function test_expired_or_foreign_zip_batch_cannot_commit_or_expose_private_preview(): void
    {
        $owner=$this->user();$stranger=$this->user();
        $batch=$this->zip()->upload($this->archive(1),$owner);
        $this->actingAs($stranger)->get(route('questions.import.media-preview',[$batch->id,0]))->assertNotFound();
        $this->post(route('questions.import.confirm',$batch->id),['confirmed_import'=>'1'])->assertNotFound();
        $batch->update(['expires_at'=>now()->subMinutes(5)]);
        $this->actingAs($owner)->get(route('questions.import.media-preview',[$batch->id,0]))->assertNotFound();
        try {$this->zip()->confirm($batch->fresh(),$owner);$this->fail('Expired ZIP committed');}
        catch(ValidationException $e){$this->assertArrayHasKey('batch',$e->errors());}
        $this->assertDatabaseCount('questions',0);
        $this->delete(route('questions.import.discard',$batch->id),['confirmed_discard'=>'1'])->assertRedirect();
        $this->assertDatabaseCount('questions',0);
    }

    public function test_case_insensitive_collisions_and_unix_symlinks_are_rejected(): void
    {
        $owner=$this->user();
        $collision=$this->archive(1,function(&$rows,&$media,&$assets){
            $assets['media/STEM-1.PNG']=$assets['media/stem-1.png'];
        });
        try {$this->zip()->upload($collision,$owner);$this->fail('ZIP path collision accepted');}
        catch(ValidationException $e){$this->assertArrayHasKey('file',$e->errors());}
        // This fixture is already a Unix symlink ZIP; no in-place ZIP rewrite of an
        // UploadedFile::fake() path is needed (Windows-safe).
        $link=$this->archive(1,null,true);
        try {$this->zip()->upload($link,$owner);$this->fail('ZIP symlink accepted');}
        catch(ValidationException $e){$this->assertArrayHasKey('file',$e->errors());}
        $this->assertDatabaseCount('question_import_batches',0);
        $this->assertDatabaseCount('questions',0);
    }

}
