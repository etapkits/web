<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\LockSetting;
use App\Models\OtpMessage;
use App\Models\Student;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceNotifier
{
    /**
     * @param  Collection<int, Student>  $students  keyed by id
     * @return list<array{phone: string, message: string}>
     */
    public function messages(AttendanceSession $session, Collection $students): array
    {
        $setting = LockSetting::forOrganization((int) $session->organization_id);

        if (! $setting?->sendsAttendanceSms()) {
            return [];
        }

        $session->loadMissing('records', 'organization');
        $messages = [];

        foreach ($session->records as $record) {
            $phone = PhoneNumber::normalize($students->get($record->student_id)?->parent_phone);

            if ($phone !== null) {
                $messages[] = [
                    'phone' => $phone,
                    'message' => $this->render((string) $setting->attendance_sms_template, $session, $record),
                ];
            }
        }

        return $messages;
    }

    /**
     * @param  list<array{phone: string, message: string}>  $messages
     */
    public function queue(array $messages): void
    {
        DB::transaction(function () use ($messages) {
            foreach ($messages as $message) {
                OtpMessage::queue($message['phone'], $message['message'], OtpMessage::TYPE_ATTENDANCE);
            }
        });
    }

    public function render(string $template, AttendanceSession $session, AttendanceRecord $record): string
    {
        return strtr($template, [
            '{ogrenci}' => $record->student_name,
            '{sinif}' => $session->class_name,
            '{ders}' => (string) $session->lesson,
            '{tarih}' => $session->date->format('d.m.Y'),
            '{durum}' => $record->status->label(),
            '{kurum}' => (string) $session->organization?->name,
        ]);
    }
}
