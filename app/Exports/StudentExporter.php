<?php

namespace App\Exports;

use App\Models\Student;
use App\Support\PhoneNumber;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentExporter
{
    private const HEADERS = ['S.No', 'Öğrenci No', 'Adı', 'Soyadı', 'Sınıf', 'Veli Telefonu'];

    public function build(int $orgId, ?string $className = null): Spreadsheet
    {
        $students = Student::query()
            ->where('organization_id', $orgId)
            ->when($className !== null && $className !== '', fn ($q) => $q->where('class_name', $className))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['student_no', 'first_name', 'last_name', 'class_name', 'parent_phone'])
            ->groupBy('class_name')
            ->sortKeysUsing('strnatcasecmp');

        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $used = [];

        foreach ($students as $class => $rows) {
            $this->fillSheet($book->createSheet(), $this->sheetTitle((string) $class, $used), $rows->all());
        }

        if ($book->getSheetCount() === 0) {
            $this->fillSheet($book->createSheet(), 'Öğrenciler', []);
        }

        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * @param  list<Student>  $rows
     */
    private function fillSheet(Worksheet $sheet, string $title, array $rows): void
    {
        $sheet->setTitle($title);
        $sheet->fromArray(self::HEADERS);

        foreach ($rows as $i => $student) {
            $line = $i + 2;
            $sheet->setCellValue([1, $line], $i + 1);
            $sheet->setCellValueExplicit([2, $line], $student->student_no, DataType::TYPE_STRING);
            $sheet->setCellValue([3, $line], $student->first_name);
            $sheet->setCellValue([4, $line], $student->last_name);
            $sheet->setCellValue([5, $line], $student->class_name);
            $sheet->setCellValueExplicit([6, $line], $student->parent_phone ? PhoneNumber::display($student->parent_phone) : '', DataType::TYPE_STRING);
        }

        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()->setFillType('solid')->getStartColor()->setRGB('C6DBDA');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F'.max(1, count($rows) + 1));

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * Excel sheet titles are limited to 31 chars, must be unique and cannot contain : \ / ? * [ ].
     *
     * @param  array<string, true>  $used
     */
    private function sheetTitle(string $class, array &$used): string
    {
        $base = mb_substr(trim(str_replace([':', '\\', '/', '?', '*', '[', ']'], '-', $class), "' ") ?: 'Sınıf', 0, 28);
        $title = $base;

        for ($n = 2; isset($used[mb_strtolower($title)]); $n++) {
            $title = $base.' ('.$n.')';
        }

        $used[mb_strtolower($title)] = true;

        return $title;
    }
}
