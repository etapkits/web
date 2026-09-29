<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    public function test_admin_can_create_update_and_delete_a_student(): void
    {
        $org = $this->organization();
        $this->actingAs(User::factory()->create());

        $this->postJson('/panel/ogrenciler', [
            'student_no' => '123',
            'first_name' => 'Ayşe',
            'last_name' => 'Yılmaz',
            'class_name' => ' 9 - a ',
            'parent_phone' => '',
        ])->assertCreated()
            ->assertJsonPath('class_names', ['9-A']);

        $student = Student::query()->sole();
        $this->assertSame($org->id, $student->organization_id);
        $this->assertSame('9-A', $student->class_name);
        $this->assertNull($student->parent_phone);

        $this->putJson('/panel/ogrenciler/'.$student->id, [
            'student_no' => '123',
            'first_name' => 'Ayşe',
            'last_name' => 'Kaya',
            'class_name' => '10-B',
            'parent_phone' => '0532 111 22 33',
        ])->assertOk();

        $student->refresh();
        $this->assertSame('Kaya', $student->last_name);
        $this->assertSame('905321112233', $student->parent_phone);

        $this->deleteJson('/panel/ogrenciler/'.$student->id)
            ->assertOk()
            ->assertJsonPath('class_names', []);

        $this->assertDatabaseCount('students', 0);
    }

    public function test_student_number_is_unique_per_organization_and_phone_is_validated(): void
    {
        $org = $this->organization();
        $other = Organization::factory()->create(['official_code' => '87654321']);
        Student::factory()->forOrganization($org)->create(['student_no' => '55']);
        Student::factory()->forOrganization($other)->create(['student_no' => '77']);

        $this->actingAs(User::factory()->create());

        $this->postJson('/panel/ogrenciler', [
            'student_no' => '55',
            'first_name' => 'Ali',
            'last_name' => 'Demir',
            'class_name' => '9-A',
            'parent_phone' => '12345',
        ])->assertStatus(422)
            ->assertJsonPath('errors.student_no.0', 'Bu öğrenci no kayıtlı.');

        $this->postJson('/panel/ogrenciler', [
            'student_no' => '77',
            'first_name' => 'Ali',
            'last_name' => 'Demir',
            'class_name' => '9-A',
            'parent_phone' => '12345',
        ])->assertStatus(422)
            ->assertJsonPath('errors.parent_phone.0', 'Veli telefonu geçerli değil.');
    }

    public function test_students_are_scoped_filtered_and_searchable(): void
    {
        $org = $this->organization();
        $other = Organization::factory()->create(['official_code' => '87654321']);
        Student::factory()->forOrganization($org)->create([
            'first_name' => 'Ayşe', 'class_name' => '9-A', 'parent_phone' => '905321112233',
        ]);
        Student::factory()->forOrganization($org)->create([
            'first_name' => 'Mehmet', 'class_name' => '10-B', 'parent_phone' => null,
        ]);
        $foreign = Student::factory()->forOrganization($other)->create(['first_name' => 'Zeynep']);

        $this->actingAs(User::factory()->create());

        $this->get('/panel/ogrenciler')
            ->assertOk()
            ->assertSeeInOrder(['<option value="9-A">', '<option value="10-B">'], false)
            ->assertSee('vendor/datatables/dataTables.min.js');

        $this->getJson('/panel/ogrenciler/data?draw=1&start=0&length=25')
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('data.0.class_name', '9-A')
            ->assertJsonMissing(['first_name' => 'Zeynep']);

        $this->getJson('/panel/ogrenciler/data?draw=1&start=0&length=25&class_name=10-B')
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.first_name', 'Mehmet');

        $columns = collect(['student_no', 'first_name', 'last_name', 'class_name', 'parent_phone'])
            ->map(fn ($c, $i) => "columns[{$i}][data]={$c}&columns[{$i}][name]={$c}&columns[{$i}][searchable]=true")
            ->implode('&');

        $this->getJson('/panel/ogrenciler/data?draw=1&start=0&length=25&search[value]=0532%20111&'.$columns)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.first_name', 'Ayşe')
            ->assertJsonPath('data.0.parent_phone_display', '0532 111 22 33');

        $this->putJson('/panel/ogrenciler/'.$foreign->id, [
            'student_no' => '1', 'first_name' => 'X', 'last_name' => 'Y', 'class_name' => '9-A',
        ])->assertNotFound();

        $this->deleteJson('/panel/ogrenciler/'.$foreign->id)->assertNotFound();
    }
}
