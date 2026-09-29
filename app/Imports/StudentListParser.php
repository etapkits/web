<?php

namespace App\Imports;

use App\Models\Student;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads e-Okul "Sınıf Listesi" exports: one sheet holds many class blocks, each starting with a
 * "9. Sınıf / A Şubesi" title, followed by a header row and student rows, closed by a
 * "Toplam Öğrenci Sayısı" footer. Plain tables with an explicit "Sınıf" column are accepted too.
 */
class StudentListParser
{
    public const NAME_PATTERN = '/^[\p{L}][\p{L} .\'-]*$/u';

    public const NO_PATTERN = '/^[0-9A-Za-z-]+$/';

    private const TITLE_PATTERN = '/(\d{1,2})\s*\.\s*sınıf\s*\/\s*([\p{L}\d]{1,3})\s*şubesi/iu';

    private const HEADERS = [
        'student_no' => ['öğrenci no', 'öğrenci numarası', 'okul no', 'okul numarası', 'numara', 'no'],
        'first_name' => ['adı', 'ad'],
        'last_name' => ['soyadı', 'soyad'],
        'class_name' => ['sınıf', 'sınıfı', 'sınıf/şube', 'sınıf şube', 'şube'],
    ];

    /**
     * @return array{rows: list<array{line: int, student_no: string, first_name: string, last_name: string, class_name: string}>, warnings: list<string>}
     */
    public function parse(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (SpreadsheetException) {
            throw new StudentImportException('Dosya okunamadı. e-Okul sınıf listesini (.xls) yükleyin.');
        }

        $rows = [];
        $warnings = [];

        foreach ($book->getAllSheets() as $sheet) {
            $this->parseSheet($sheet->toArray(null, false, false, false), $rows, $warnings);
        }

        $book->disconnectWorksheets();

        if ($rows === []) {
            throw new StudentImportException('Dosyada öğrenci bulunamadı. Başlık satırında "Öğrenci No", "Adı" ve "Soyadı" olmalı.');
        }

        return ['rows' => array_values($rows), 'warnings' => $warnings];
    }

    /**
     * @param  array<int, array<int, mixed>>  $data
     * @param  array<string, array{line: int, student_no: string, first_name: string, last_name: string, class_name: string}>  $rows
     * @param  list<string>  $warnings
     */
    private function parseSheet(array $data, array &$rows, array &$warnings): void
    {
        $class = null;
        $columns = null;
        $blockCount = 0;

        foreach ($data as $index => $raw) {
            $line = $index + 1;
            $cells = array_map(fn ($value) => $this->text($value), $raw);
            $joined = implode(' ', array_filter($cells, fn (string $v) => $v !== ''));

            if ($joined === '') {
                continue;
            }

            if (preg_match(self::TITLE_PATTERN, $joined, $m)) {
                $class = Student::normalizeClassName($m[1].'-'.$m[2]);
                $columns = null;
                $blockCount = 0;

                continue;
            }

            if ($header = $this->header($cells)) {
                $columns = $header;
                $blockCount = 0;

                continue;
            }

            if ($columns === null) {
                continue;
            }

            if (str_contains($this->lower($joined), 'toplam öğrenci sayısı')) {
                $expected = $this->lastNumber($cells);

                if ($expected !== null && $expected !== $blockCount) {
                    $warnings[] = sprintf('%s: listede %d öğrenci yazıyor, %d öğrenci okundu.', $class ?? 'Satır '.$line, $expected, $blockCount);
                }

                $columns = null;

                continue;
            }

            $studentNo = $this->cell($cells, $columns, 'student_no');

            if ($studentNo === '') {
                continue;
            }

            $row = [
                'line' => $line,
                'student_no' => $studentNo,
                'first_name' => $this->cell($cells, $columns, 'first_name'),
                'last_name' => $this->cell($cells, $columns, 'last_name'),
                'class_name' => isset($columns['class_name'])
                    ? Student::normalizeClassName($this->cell($cells, $columns, 'class_name'))
                    : (string) $class,
            ];

            if ($error = $this->invalid($row)) {
                $warnings[] = sprintf('Satır %d (%s): %s Atlandı.', $line, $studentNo, $error);

                continue;
            }

            if (isset($rows[$studentNo])) {
                $warnings[] = sprintf('Satır %d: %s numarası satır %d ile aynı. Son satır kullanıldı.', $line, $studentNo, $rows[$studentNo]['line']);
            }

            $rows[$studentNo] = $row;
            $blockCount++;
        }
    }

    /**
     * @param  array{student_no: string, first_name: string, last_name: string, class_name: string}  $row
     */
    private function invalid(array $row): ?string
    {
        return match (true) {
            strlen($row['student_no']) > 20 || ! preg_match(self::NO_PATTERN, $row['student_no']) => 'Öğrenci no geçersiz.',
            $row['first_name'] === '' || mb_strlen($row['first_name']) > 100 || ! preg_match(self::NAME_PATTERN, $row['first_name']) => 'Ad geçersiz.',
            $row['last_name'] === '' || mb_strlen($row['last_name']) > 100 || ! preg_match(self::NAME_PATTERN, $row['last_name']) => 'Soyad geçersiz.',
            $row['class_name'] === '' => 'Sınıf bulunamadı.',
            mb_strlen($row['class_name']) > 20 => 'Sınıf geçersiz.',
            default => null,
        };
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, int>|null
     */
    private function header(array $cells): ?array
    {
        $found = [];

        foreach ($cells as $col => $value) {
            $label = rtrim($this->lower($value), ' :');

            foreach (self::HEADERS as $key => $labels) {
                if (! isset($found[$key]) && in_array($label, $labels, true)) {
                    $found[$key] = $col;
                }
            }
        }

        if (! isset($found['student_no'], $found['first_name'], $found['last_name'])) {
            return null;
        }

        asort($found);

        return $found;
    }

    /**
     * Merged header cells can push the value a few columns right, so read until the next header column.
     *
     * @param  list<string>  $cells
     * @param  array<string, int>  $columns
     */
    private function cell(array $cells, array $columns, string $key): string
    {
        $start = $columns[$key];
        $end = count($cells);

        foreach ($columns as $col) {
            if ($col > $start && $col < $end) {
                $end = $col;
            }
        }

        for ($col = $start; $col < $end; $col++) {
            if (($cells[$col] ?? '') !== '') {
                return $cells[$col];
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $cells
     */
    private function lastNumber(array $cells): ?int
    {
        foreach (array_reverse($cells) as $value) {
            if ($value !== '' && ctype_digit($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function text(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        $text = str_replace("\u{00A0}", ' ', (string) $value);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function lower(string $value): string
    {
        return mb_strtolower(strtr($value, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');
    }
}
