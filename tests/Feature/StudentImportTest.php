<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    public function test_eokul_class_list_is_imported_per_class_block(): void
    {
        $org = $this->organization();
        $other = Organization::factory()->create(['official_code' => '87654321']);
        Student::factory()->forOrganization($org)->create([
            'student_no' => '52', 'first_name' => 'ESKİ', 'last_name' => 'AD', 'class_name' => '10-A', 'parent_phone' => '905321112233',
        ]);
        Student::factory()->forOrganization($org)->create(['student_no' => '999', 'class_name' => '12-A']);
        Student::factory()->forOrganization($other)->create(['student_no' => '81']);

        $file = $this->eokulFile([
            '9. Sınıf / A' => [
                [52, 'ABBAS', 'ALHUSSEİN'],
                [81, 'ABDELRAHMAN MANSOUR ABDELMOAEZ MANSOUR MOAMED', 'KASAB'],
            ],
            '10. Sınıf / B' => [
                [101, 'AHMET EMİN', 'KÜNTÜŞ'],
                [102, 'AYŞE', '?'],
            ],
        ], totals: [2, 3]);

        $this->actingAs(User::factory()->create())
            ->post('/panel/ogrenciler/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('result', ['created' => 2, 'updated' => 1, 'unchanged' => 0, 'deleted' => 0])
            ->assertJsonPath('warning_count', 2)
            ->assertJsonPath('class_names', ['9-A', '10-B', '12-A']);

        $abbas = Student::query()->where('organization_id', $org->id)->where('student_no', '52')->sole();
        $this->assertSame('ABBAS', $abbas->first_name);
        $this->assertSame('9-A', $abbas->class_name);
        $this->assertSame('905321112233', $abbas->parent_phone);

        $this->assertSame('10-B', Student::query()->where('organization_id', $org->id)->where('student_no', '101')->value('class_name'));
        $this->assertTrue(Student::query()->where('organization_id', $org->id)->where('student_no', '999')->exists());
        $this->assertSame(1, Student::query()->where('organization_id', $other->id)->count());
    }

    public function test_reimport_is_idempotent_and_can_remove_missing_students(): void
    {
        $org = $this->organization();
        Student::factory()->forOrganization($org)->create(['student_no' => '999']);
        $this->actingAs(User::factory()->create());

        $rows = ['11. Sınıf / E' => [[7, 'ELİF', 'KAYA'], [8, 'ZEYNEP', 'DEMİR']]];

        $this->post('/panel/ogrenciler/import', ['file' => $this->eokulFile($rows)], ['Accept' => 'application/json'])
            ->assertJsonPath('result.created', 2);

        $this->post('/panel/ogrenciler/import', [
            'file' => $this->eokulFile($rows),
            'remove_missing' => '1',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('result', ['created' => 0, 'updated' => 0, 'unchanged' => 2, 'deleted' => 1]);

        $this->assertSame(['7', '8'], Student::query()->orderBy('student_no')->pluck('student_no')->all());
    }

    public function test_unrecognised_file_is_rejected(): void
    {
        $this->organization();
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Bir', 'İki'], ['x', 'y']]);
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xls';
        (new Xls($book))->save($path);

        $this->actingAs(User::factory()->create())
            ->post('/panel/ogrenciler/import', ['file' => new UploadedFile($path, 'liste.xls', null, null, true)], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('students', 0);
    }

    /**
     * @param  array<string, list<array{int, string, string}>>  $blocks
     * @param  list<int>  $totals
     */
    private function eokulFile(array $blocks, array $totals = []): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $row = 1;

        foreach (array_values($blocks) as $i => $students) {
            $title = array_keys($blocks)[$i];
            $sheet->setCellValue([1, $row], "T.C.\nİSTANBUL VALİLİĞİ\nÖrnek Lisesi Müdürlüğü\nAİHL - {$title} Şubesi (FEN VE SOSYAL BİLİMLER PROGRAMI) Sınıf Listesi \n");
            $row += 6;
            $sheet->setCellValue([1, $row], 'Sınıf Öğretmeni: ');
            $row += 4;
            foreach ([1 => 'S.No', 2 => 'Öğrenci No', 5 => 'Adı', 9 => 'Soyadı', 12 => 'Cinsiyeti'] as $col => $label) {
                $sheet->setCellValue([$col, $row], $label);
            }
            $row += 2;

            foreach ($students as $n => [$no, $first, $last]) {
                $sheet->setCellValue([1, $row], $n + 1);
                $sheet->setCellValue([2, $row], $no);
                $sheet->setCellValue([5, $row], $first);
                $sheet->setCellValue([9, $row], $last);
                $sheet->setCellValue([13, $row], 'Erkek');
                $row += 2;
            }

            $sheet->setCellValue([2, $row], 'Kız Öğrenci Sayısı        :');
            $sheet->setCellValue([12, $row], 'Toplam Öğrenci Sayısı    :');
            $sheet->setCellValue([16, $row], $totals[$i] ?? count($students));
            $row++;
        }

        $path = tempnam(sys_get_temp_dir(), 'imp').'.xls';
        (new Xls($book))->save($path);

        return new UploadedFile($path, 'ham.XLS', null, null, true);
    }
}
