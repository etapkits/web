<?php

namespace App\Http\Controllers\Etaotp;

use App\Http\Controllers\Controller;
use App\Models\OtpMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class QueueController extends Controller
{
    public function claim(): Response
    {
        $row = DB::transaction(function () {
            OtpMessage::query()
                ->where('status', 'sending')
                ->where(fn ($q) => $q->where('claimed_at', '<=', now()->subMinutes(3))
                    ->orWhere(fn ($q) => $q->whereNull('claimed_at')->where('created_at', '<=', now()->subMinutes(3))))
                ->update(['status' => 'pending']);

            $next = OtpMessage::query()
                ->where('status', 'pending')
                ->orderByRaw('case when type = ? then 0 else 1 end', [OtpMessage::TYPE_LOGIN])
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                return null;
            }

            $next->forceFill(['status' => 'sending', 'claimed_at' => now()])->save();

            return $next;
        });

        if (! $row) {
            return response()->noContent();
        }

        return response()->json([
            'id' => $row->id,
            'type' => $row->type,
            'sendto' => $row->sendto,
            'message' => $row->message,
        ]);
    }

    public function sent(int $id): JsonResponse
    {
        $updated = OtpMessage::query()
            ->whereKey($id)
            ->where('status', 'sending')
            ->update([
                'status' => 'sent',
                'sended_at' => now(),
                'message' => '',
            ]);

        return response()->json(['ok' => $updated === 1]);
    }

    public function failed(int $id): JsonResponse
    {
        $updated = OtpMessage::query()
            ->whereKey($id)
            ->where('status', 'sending')
            ->update(['status' => 'failed']);

        return response()->json(['ok' => $updated === 1]);
    }

    public function release(int $id): JsonResponse
    {
        $updated = OtpMessage::query()
            ->whereKey($id)
            ->where('status', 'sending')
            ->update(['status' => 'pending']);

        return response()->json(['ok' => $updated === 1]);
    }
}
