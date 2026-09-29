<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class StudentExportTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    public function test_each_class_is_exported_to_its_own_sheet(): void
    {
        $org = $this->organization();
        $other = Organization::factory()->create(['official_code' => '87654321']);
        Student::factory()->forOrganization($org)->create([
            'student_no' => '052', 'first_name' => 'ZEYNEP', 'last_name' => 'AK', 'class_name' => '10-A', 'parent_phone' => '905321112233',
        ]);
        Student::factory()->forOrganization($org)->create([
            'student_no' => '7', 'first_name' => 'ALİ', 'last_name' => 'BAL', 'class_name' => '9-A', 'parent_phone' => null,
        ]);
        Student::factory()->forOrganization($org)->create([
            'student_no' => '8', 'first_name' => 'CAN', 'last_name' => 'AY', 'class_name' => '9-A',
        ]);
        Student::factory()->forOrganization($other)->create(['class_name' => '11-B']);

        $this->actingAs(User::factory()->create());

        $book = $this->download('/panel/ogrenciler/export');
        $this->assertSame(['9-A', '10-A'], $book->getSheetNames());

        $nineA = $book->getSheetByName('9-A')->toArray();
        $this->assertSame(['S.No', 'Öğrenci No', 'Adı', 'Soyadı', 'Sınıf', 'Veli Telefonu'], $nineA[0]);
        $this->assertSame(['CAN', 'AY'], [$nineA[1][2], $nineA[1][3]]);
        $this->assertCount(3, $nineA);

        $tenA = $book->getSheetByName('10-A')->toArray();
        $this->assertSame('052', $tenA[1][1]);
        $this->assertSame('0532 111 22 33', $tenA[1][5]);

        $filtered = $this->download('/panel/ogrenciler/export?class_name=10-A');
        $this->assertSame(['10-A'], $filtered->getSheetNames());
    }

    public function test_empty_organization_exports_a_header_only_sheet(): void
    {
        $this->organization();
        $this->actingAs(User::factory()->create());

        $book = $this->download('/panel/ogrenciler/export');

        $this->assertSame(['Öğrenciler'], $book->getSheetNames());
        $this->assertCount(1, $book->getActiveSheet()->toArray());
    }

    private function download(string $url): Spreadsheet
    {
        $response = $this->get($url)->assertOk()->assertDownload();
        $path = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        return IOFactory::load($path);
    }
}
