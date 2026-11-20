<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Invitation;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckInController extends Controller
{
    public function verify(string $identifier): JsonResponse
    {
        $invitation = Invitation::with('members')
            ->where('code', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        if (! $invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket/undangan tidak ditemukan.',
            ], 404);
        }

        if ($invitation->cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Undangan telah dibatalkan.',
                'data' => $invitation,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $invitation,
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|string',
            'code' => 'nullable|string',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'string',
            'count' => 'nullable|integer|min:1',
            'attendees' => 'nullable', // array of names or comma/newline separated string
        ]);

        $identifier = $validated['id'] ?? $validated['code'] ?? $request->route('code') ?? null;

        if (! $identifier) {
            return response()->json([
                'success' => false,
                'message' => 'ID atau kode undangan harus disertakan.',
            ], 422);
        }

        $invitation = Invitation::with('members')
            ->where('id', $identifier)
            ->orWhere('code', $identifier)
            ->first();

        if (! $invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Undangan tidak ditemukan.',
            ], 404);
        }

        if ($invitation->cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Undangan telah dibatalkan.',
            ], 422);
        }

        // Process attendee names if provided
        $attendeeNames = [];
        if (! empty($validated['attendees'])) {
            if (is_array($validated['attendees'])) {
                $attendeeNames = array_filter(array_map('trim', $validated['attendees']));
            } elseif (is_string($validated['attendees'])) {
                $attendeeNames = array_filter(array_map('trim', preg_split('/[,\n\r]+/', $validated['attendees'])));
            }
        }

        // Save attendees as Member records
        if (! empty($attendeeNames)) {
            foreach ($attendeeNames as $attName) {
                $existing = $invitation->members->firstWhere('name', $attName);
                if ($existing) {
                    $existing->update(['present' => true]);
                } else {
                    Member::create([
                        'id' => (string) Str::uuid(),
                        'invitation_id' => $invitation->id,
                        'name' => $attName,
                        'role' => 'Tamu',
                        'present' => true,
                    ]);
                }
            }
        }

        // Handle specific member_ids if provided
        $memberIds = $validated['member_ids'] ?? [];
        if (! empty($memberIds)) {
            Member::whereIn('id', $memberIds)->update(['present' => true]);
        }

        // Determine updated party count
        $partyCount = $validated['count'] ?? (! empty($attendeeNames) ? count($attendeeNames) : ($invitation->checked > 0 ? $invitation->checked : 1));

        // Update database: adjust quota to match party size if needed, update checked count
        $invitation->quota = max($invitation->quota, $partyCount);
        $invitation->checked = $partyCount;
        $invitation->rsvp = 'Hadir';
        $invitation->save();

        Activity::create([
            'text' => "{$invitation->name} check-in ({$partyCount} tamu hadir)",
            'time' => now()->format('H:i'),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Kehadiran {$partyCount} tamu berhasil dicatat di database.",
            'added' => $partyCount,
            'data' => $invitation->fresh('members'),
        ]);
    }
}
