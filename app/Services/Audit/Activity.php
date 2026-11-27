<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\DB;

final class Activity
{
    public static function audit(string $event, ?int $actor, ?int $swap = null, ?int $cancellation = null, ?string $reason = null, array $details = []): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => $actor,
            'swap_id' => $swap,
            'cancellation_id' => $cancellation,
            'event' => $event,
            'reason' => $reason,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public static function notify(iterable $recipients, string $message, string $target): void
    {
        $rows = [];
        foreach ($recipients as $recipient) {
            if ($recipient) {
                $rows[$recipient] = ['user_id' => $recipient, 'message' => $message, 'target' => $target, 'created_at' => now()];
            }
        }
        if ($rows) {
            DB::table('notifications')->insert(array_values($rows));
        }
    }
}
