<?php
namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionImportBatch;
use App\Models\User;
use App\Services\QuestionCsvImportService;
use App\Services\QuestionEditorialService;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SiapegawaiBulkQuestionImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(SiapegawaiSubjectsSeeder::class);
    }

    private function user(string $role='operational_admin'): User
    {
        return User::factory()->create(['role'=>$role,'status'=>'active']);
    }

    private function csv(int $n=1, ?\Closure $change=null, bool $bom=false): string
    {
        $f=fopen('php://temp','w+');
        fputcsv($f,QuestionCsvImportService::HEADER,',','"','');
        for ($i=1;$i<=$n;$i++) {
            $record=['UTQ-'.sprintf('%04d',$i),'TWK', 'placeholder',
                'Pertanyaan pengujian yang panjang valid untuk nomor '.$i,
                'Pembahasan jawaban yang cukup panjang untuk nomor '.$i,
                'Pilihan A jawaban benar','Pilihan B jawaban','Pilihan C jawaban','Pilihan D jawaban','','A'];
            if ($change) $record=$change($record,$i);
            fputcsv($f,$record,',','"','');
        }
        rewind($f); $out=stream_get_contents($f); fclose($f);
        return ($bom?"\xEF\xBB\xBF":'').$out;
    }

    private function reference(string $csv): string
    {
        $topic=\App\Models\Topic::query()->whereHas('subject',fn($q)=>$q->where('code','TWK'))
            ->where('status','active')->firstOrFail();
        return str_replace('placeholder',$topic->slug,$csv);
    }

    private function upload(string $data): QuestionImportBatch
    {
        $user=$this->user();
        return app(QuestionCsvImportService::class)->upload(
            UploadedFile::fake()->createWithContent('soal.csv',$data),$user);
    }

    public function test_strict_parser_handles_bom_quote_comma_and_multiline(): void
    {
        $service=app(QuestionCsvImportService::class);
        $input=$this->csv(1, function($row){$row[3]="Pertanyaan \"berkutip\", koma dan\nbaris baru valid?";return $row;},true);
        $rows=$service->parse($input);
        $this->assertCount(1,$rows);
        $this->assertStringContainsString("\nbaris",$rows[0]['values']['body']);
        $this->assertSame(2,$rows[0]['line']);
        foreach (["\xFF", "\0"] as $bad) {
            try {$service->parse($input.$bad);$this->fail('Invalid encoding accepted');}
            catch (ValidationException $e) {$this->assertNotEmpty($e->errors());}
        }
        $this->expectException(ValidationException::class);
        $service->parse(implode(',',QuestionCsvImportService::HEADER)."\n\"unterminated");
    }

    public function test_byte_and_record_limits_independent(): void
    {
        $service=app(QuestionCsvImportService::class);
        $this->assertTrue($service->withinByteLimit(5242880));
        $this->assertFalse($service->withinByteLimit(5242881));
        $this->assertCount(200,$service->parse($this->csv(200)));
        try {$service->parse($this->csv(201));$this->fail('201 accepted');}
        catch (ValidationException $e) {$this->assertArrayHasKey('file',$e->errors());}
        $this->expectException(ValidationException::class);
        $service->upload(UploadedFile::fake()->createWithContent('large.csv',str_repeat('x',5242881)), $this->user());
    }

    public function test_preview_does_not_write_questions_even_when_199_valid_and_one_invalid(): void
    {
        $service=app(QuestionCsvImportService::class);
        $data=$this->reference($this->csv(200, function($row,$i){if($i===200) $row[10]='E';return $row;}));
        $actor=$this->user();
        $batch=$service->upload(UploadedFile::fake()->createWithContent('soal.csv',$data),$actor);
        $this->assertSame('invalid',$batch->status);
        $this->assertSame(200,$batch->row_count);
        $this->assertSame(199,$batch->valid_count);
        $this->assertSame(1,$batch->invalid_count);
        $this->assertDatabaseCount('questions',0);
        $this->assertDatabaseCount('question_versions',0);
        $this->assertDatabaseCount('question_options',0);
        $this->assertSame(0,AuditLog::where('action','question.create')->count());
        $this->expectException(ValidationException::class);
        $service->confirm($batch,$actor,app(QuestionEditorialService::class));
    }

    public function test_valid_batch_commits_drafts_and_replay_does_not_duplicate(): void
    {
        $actor=$this->user();$service=app(QuestionCsvImportService::class);
        $batch=$service->upload(UploadedFile::fake()->createWithContent('soal.csv',$this->reference($this->csv(5))),$actor);
        $this->assertSame('ready',$batch->status);
        $this->assertDatabaseCount('questions',0);
        $one=$service->confirm($batch,$actor,app(QuestionEditorialService::class));
        $two=$service->confirm($batch,$actor,app(QuestionEditorialService::class));
        $this->assertSame($one,$two);
        $this->assertSame(5,$one['count']);
        $this->assertDatabaseCount('questions',5);
        $this->assertDatabaseCount('question_versions',5);
        $this->assertDatabaseCount('question_options',20);
        $this->assertSame(5,AuditLog::query()->where('action','question.create')->count());
        $this->assertSame(0,Question::query()->whereNotNull('current_version_id')->count());
        $this->assertSame('committed',$batch->fresh()->status);
    }

    public function test_200_valid_records_created_with_same_draft_contract(): void
    {
        $actor=$this->user();$service=app(QuestionCsvImportService::class);
        $batch=$service->upload(UploadedFile::fake()->createWithContent('soal.csv',$this->reference($this->csv(200))),$actor);
        $this->assertSame(200,$batch->valid_count);
        $this->assertSame(200,$service->confirm($batch,$actor,app(QuestionEditorialService::class))['count']);
        $this->assertDatabaseCount('questions',200);
    }

    public function test_failure_at_third_record_rolls_back_all_questions_options_and_audit(): void
    {
        $actor=$this->user();$service=app(QuestionCsvImportService::class);
        $batch=$service->upload(UploadedFile::fake()->createWithContent('soal.csv',$this->reference($this->csv(5))),$actor);
        try {$service->confirm($batch,$actor,app(QuestionEditorialService::class),function($i){if($i===3) throw new \RuntimeException('UAT-fault');});$this->fail('Expected fault');}
        catch(\RuntimeException $e){$this->assertSame('UAT-fault',$e->getMessage());}
        $this->assertDatabaseCount('questions',0);
        $this->assertDatabaseCount('question_versions',0);
        $this->assertDatabaseCount('question_options',0);
        $this->assertSame(0,AuditLog::query()->where('action','question.create')->count());
        $this->assertSame('failed',$batch->fresh()->status);
    }

    public function test_two_batches_same_codes_second_fails_as_whole_batch(): void
    {
        $actor=$this->user();$service=app(QuestionCsvImportService::class);
        $data=$this->reference($this->csv(3));
        $one=$service->upload(UploadedFile::fake()->createWithContent('one.csv',$data),$actor);
        $two=$service->upload(UploadedFile::fake()->createWithContent('two.csv',$data),$actor);
        $service->confirm($one,$actor,app(QuestionEditorialService::class));
        try {$service->confirm($two,$actor,app(QuestionEditorialService::class));$this->fail('Expected collision');}
        catch(ValidationException $e){$this->assertArrayHasKey('batch',$e->errors());}
        $this->assertDatabaseCount('questions',3);
        $this->assertSame('failed',$two->fresh()->status);
    }

    public function test_expired_modified_checksum_and_reference_change_fail_closed(): void
    {
        $actor=$this->user();$service=app(QuestionCsvImportService::class);
        $data=$this->reference($this->csv());
        $expired=$service->upload(UploadedFile::fake()->createWithContent('one.csv',$data),$actor);
        $expired->update(['expires_at'=>now()->subMinute()]);
        try {$service->confirm($expired,$actor,app(QuestionEditorialService::class));$this->fail('Expected expired');}
        catch(ValidationException $e){$this->assertArrayHasKey('batch',$e->errors());}
        $tampered=$service->upload(UploadedFile::fake()->createWithContent('two.csv',$data),$actor);
        Storage::disk('local')->put($tampered->private_path,$data.' ');
        try {$service->confirm($tampered,$actor,app(QuestionEditorialService::class));$this->fail('Expected checksum failure');}
        catch(ValidationException $e){$this->assertArrayHasKey('batch',$e->errors());}
        $changed=$service->upload(UploadedFile::fake()->createWithContent('three.csv',$data),$actor);
        $used=\App\Models\Topic::query()->where('slug',explode(',',explode("\n",$data)[1])[2])->firstOrFail();
        $used->update(['status'=>'inactive']);
        try {$service->confirm($changed,$actor,app(QuestionEditorialService::class));$this->fail('Expected ref change');}
        catch(ValidationException $e){$this->assertArrayHasKey('batch',$e->errors());}
        $this->assertDatabaseCount('questions',0);
    }

    public function test_non_owner_cannot_access_batch_or_report_and_participant_cannot_open_ui(): void
    {
        $actor=$this->user(); $other=$this->user();
        $batch=app(QuestionCsvImportService::class)->upload(UploadedFile::fake()->createWithContent('one.csv',$this->reference($this->csv())),$actor);
        $this->actingAs($other)->get(route('questions.import.show',$batch->id))->assertNotFound();
        $this->get(route('questions.import.errors',$batch->id))->assertNotFound();
        $this->post(route('questions.import.confirm',$batch->id),['confirmed_import'=>'1'])->assertNotFound();
        $this->actingAs($this->user('participant'))->get(route('questions.import.index'))->assertForbidden();
    }

    public function test_formula_export_cell_neutralization_and_html_preview(): void
    {
        foreach (['=1+1','  +SUM(1,2)',"\t@x",'-3'] as $bad) {
            $this->assertStringStartsWith("'",QuestionCsvImportService::csvCell($bad));
        }
        $this->assertSame('normal',QuestionCsvImportService::csvCell('normal'));
        $actor=$this->user();
        $data=$this->reference($this->csv(1,function($r){$r[3]='Penjelasan <script>alert(1)</script> harus di-escape aman?';return $r;}));
        $batch=app(QuestionCsvImportService::class)->upload(UploadedFile::fake()->createWithContent('one.csv',$data),$actor);
        $this->actingAs($actor)->get(route('questions.import.show',$batch->id))->assertOk()
            ->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
    }

    public function test_owner_discards_invalid_batch_without_removing_questions_or_audit(): void
    {
        $actor = $this->user();
        $service = app(QuestionCsvImportService::class);
        $invalid = $this->reference($this->csv(2, function($row, $i) {
            if ($i === 2) $row[10] = 'E';
            return $row;
        }));
        $batch = $service->upload(UploadedFile::fake()->createWithContent('invalid.csv',$invalid),$actor);
        $path = $batch->private_path;
        $this->assertSame('invalid', $batch->status);
        Storage::disk('local')->assertExists($path);
        $this->actingAs($actor)->delete(route('questions.import.discard',$batch->id), ['confirmed_discard'=>'1'])
            ->assertRedirect(route('questions.import.index'));
        $this->assertSame('discarded', $batch->fresh()->status);
        $this->assertSame('', $batch->fresh()->private_path);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseCount('questions',0);
        $this->assertSame(1,AuditLog::query()->where('action','question_import.discard')->count());
        $this->get(route('questions.import.show',$batch->id))->assertNotFound();
        $this->get(route('questions.import.errors',$batch->id))->assertNotFound();
        $this->get(route('questions.import.index'))->assertDontSee($batch->id);
        // Idempotent repeated discard; no second audit event.
        $this->delete(route('questions.import.discard',$batch->id), ['confirmed_discard'=>'1'])
            ->assertRedirect(route('questions.import.index'));
        $this->assertSame(1,AuditLog::query()->where('action','question_import.discard')->count());
    }

    public function test_committed_batch_cannot_be_discarded_or_delete_draft_questions(): void
    {
        $actor = $this->user();
        $service = app(QuestionCsvImportService::class);
        $batch = $service->upload(UploadedFile::fake()->createWithContent('valid.csv',$this->reference($this->csv(2))),$actor);
        $service->confirm($batch,$actor,app(QuestionEditorialService::class));
        $this->actingAs($actor)->delete(route('questions.import.discard',$batch->id), ['confirmed_discard'=>'1'])
            ->assertSessionHasErrors('batch');
        $this->assertSame('committed',$batch->fresh()->status);
        $this->assertDatabaseCount('questions',2);
        $this->assertSame(0,AuditLog::query()->where('action','question_import.discard')->count());
    }

    public function test_discard_requires_owner_and_confirmation_and_template_is_downloadable(): void
    {
        $actor = $this->user();
        $other = $this->user();
        $service = app(QuestionCsvImportService::class);
        $batch = $service->upload(UploadedFile::fake()->createWithContent('valid.csv',$this->reference($this->csv())), $actor);
        $this->actingAs($other)->delete(route('questions.import.discard',$batch->id), ['confirmed_discard'=>'1'])->assertNotFound();
        $this->actingAs($actor)->delete(route('questions.import.discard',$batch->id), [])->assertSessionHasErrors('confirmed_discard');
        $this->assertSame('ready',$batch->fresh()->status);
        $this->get(route('questions.import.template'))->assertOk();
        $this->get(route('questions.import.references'))->assertOk();
    }

    public function test_import_ui_exposes_template_drag_drop_and_copy_error_actions(): void
    {
        $actor=$this->user();
        $this->actingAs($actor)->get(route('questions.import.index'))->assertOk()
            ->assertSee('data-qi-dropzone',false)
            ->assertSee('class="button button-outline qi-choose-label"',false)
            ->assertSee('id="import-file" name="file" class="qi-file-input"',false)
            ->assertDontSee('Berkas yang akan diperiksa')
            ->assertDontSee('data-qi-choose',false)
            ->assertSee('Unduh template CSV')
            ->assertSee('Panduan langkah demi langkah');
        $invalid=$this->reference($this->csv(1,function($row){$row[10]='E';return $row;}));
        $batch=app(QuestionCsvImportService::class)->upload(
            UploadedFile::fake()->createWithContent('invalid.csv',$invalid),$actor);
        $this->get(route('questions.import.show',$batch->id))->assertOk()
            ->assertSee('data-qi-copy-errors',false)
            ->assertSee('qi-callout-danger',false)
            ->assertSee('Buang batch ini');
    }
}
