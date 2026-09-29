<?php

namespace App\Imports;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentImporter
{
    /**
     * Upserts by (organization_id, student_no). Parent phones are never touched because the
     * e-Okul list has no phone column.
     *
     * @param  list<array{student_no: string, first_name: string, last_name: string, class_name: string}>  $rows
     * @return array{created: int, updated: int, unchanged: int, deleted: int}
     */
    public function import(int $orgId, array $rows, bool $removeMissing = false): array
    {
        return DB::transaction(function () use ($orgId, $rows, $removeMissing): array {
            $existing = Student::query()
                ->where('organization_id', $orgId)
                ->get(['student_no', 'first_name', 'last_name', 'class_name'])
                ->keyBy('student_no');

            $now = now();
            $values = [];
            $created = 0;
            $updated = 0;
            $unchanged = 0;

            foreach ($rows as $row) {
                $current = $existing->get($row['student_no']);

                if ($current === null) {
                    $created++;
                } elseif ($current->first_name === $row['first_name']
                    && $current->last_name === $row['last_name']
                    && $current->class_name === $row['class_name']) {
                    $unchanged++;

                    continue;
                } else {
                    $updated++;
                }

                $values[] = [
                    'organization_id' => $orgId,
                    'student_no' => $row['student_no'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'class_name' => $row['class_name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($values, 500) as $chunk) {
                Student::query()->upsert(
                    $chunk,
                    ['organization_id', 'student_no'],
                    ['first_name', 'last_name', 'class_name', 'updated_at'],
                );
            }

            $deleted = 0;

            if ($removeMissing && $rows !== []) {
                $deleted = Student::query()
                    ->where('organization_id', $orgId)
                    ->whereNotIn('student_no', array_column($rows, 'student_no'))
                    ->delete();
            }

            return compact('created', 'updated', 'unchanged', 'deleted');
        });
    }
}
