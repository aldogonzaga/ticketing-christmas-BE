<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\Message;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    public function getInvitation(string $code): JsonResponse
    {
        $invitation = Invitation::with('members')
            ->where('code', $code)
            ->first();

        if (! $invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Undangan dengan kode tersebut tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $invitation,
        ]);
    }

    public function submitRsvp(Request $request, string $code): JsonResponse
    {
        $invitation = Invitation::where('code', $code)->first();

        if (! $invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Undangan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'rsvp' => 'required|in:Pending,Hadir,Tidak Hadir',
            'phone' => 'nullable|string',
            'pic' => 'nullable|string',
        ]);

        $invitation->rsvp = $validated['rsvp'];
        if (! empty($validated['phone'])) {
            $invitation->phone = $validated['phone'];
        }
        if (! empty($validated['pic'])) {
            $invitation->pic = $validated['pic'];
        }
        $invitation->save();

        $actionText = match ($validated['rsvp']) {
            'Hadir' => 'mengonfirmasi kehadiran',
            'Tidak Hadir' => 'mengonfirmasi tidak dapat hadir',
            default => 'memperbarui status RSVP',
        };

        Activity::create([
            'text' => "{$invitation->name} {$actionText}",
            'time' => now()->format('H:i'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Konfirmasi RSVP berhasil disimpan.',
            'data' => $invitation->fresh('members'),
        ]);
    }

    public function updateSchoolMembers(Request $request, string $code): JsonResponse
    {
        $invitation = Invitation::with('members')->where('code', $code)->first();

        if (! $invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Undangan sekolah tidak ditemukan.',
            ], 404);
        }

        if ($invitation->category !== 'SMP') {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini khusus untuk perwakilan sekolah SMP.',
            ], 403);
        }

        $validated = $request->validate([
            'members' => 'required|array',
            'members.*.name' => 'required|string',
            'members.*.role' => 'nullable|string',
            'pic' => 'nullable|string',
            'phone' => 'nullable|string',
            'principal' => 'nullable|string',
        ]);

        if (count($validated['members']) > $invitation->quota) {
            return response()->json([
                'success' => false,
                'message' => "Jumlah anggota melebihi kuota undangan ({$invitation->quota} peserta).",
            ], 422);
        }

        if (! empty($validated['pic'])) $invitation->pic = $validated['pic'];
        if (! empty($validated['phone'])) $invitation->phone = $validated['phone'];
        if (! empty($validated['principal'])) $invitation->principal = $validated['principal'];
        $invitation->save();

        $existingIds = [];
        foreach ($validated['members'] as $m) {
            $memberId = $m['id'] ?? (string) Str::uuid();
            $existingIds[] = $memberId;

            Member::updateOrCreate(
                ['id' => $memberId],
                [
                    'invitation_id' => $invitation->id,
                    'name' => $m['name'],
                    'role' => $m['role'] ?? 'Siswa',
                    'present' => $m['present'] ?? false,
                ]
            );
        }

        // Delete removed members
        Member::where('invitation_id', $invitation->id)
            ->whereNotIn('id', $existingIds)
            ->delete();

        Activity::create([
            'text' => "{$invitation->name} memperbarui daftar peserta",
            'time' => now()->format('H:i'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Daftar peserta sekolah berhasil diperbarui.',
            'data' => $invitation->fresh('members'),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'affiliation' => 'required|string|max:255',
            'category' => 'nullable|string',
            'message' => 'required|string|max:1000',
            'phone' => 'nullable|string|max:50',
        ]);

        $category = $validated['category'] ?? 'Orang Tua/Wali';
        $validCategories = ['SMP', 'Guru', 'VIP', 'Orang Tua/Wali'];
        if (! in_array($category, $validCategories)) {
            $affLower = strtolower($validated['affiliation']);
            if (str_contains($affLower, 'smp') || str_contains($affLower, 'sekolah')) {
                $category = 'SMP';
            } elseif (str_contains($affLower, 'guru') || str_contains($affLower, 'pengajar')) {
                $category = 'Guru';
            } elseif (str_contains($affLower, 'vip') || str_contains($affLower, 'dinas') || str_contains($affLower, 'pejabat')) {
                $category = 'VIP';
            } else {
                $category = 'Orang Tua/Wali';
            }
        }

        // Generate unique 5-character alphanumeric code (excluding ambiguous 0, O, 1, I)
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 5; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (Invitation::where('code', $code)->exists());

        $invitation = Invitation::create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => $validated['name'],
            'category' => $category,
            'institution' => $validated['affiliation'],
            'phone' => $validated['phone'] ?? null,
            'quota' => 1,
            'checked' => 0,
            'rsvp' => 'Hadir',
            'note' => $validated['message'],
        ]);

        // Save Christmas message to messages table so it displays in celebration marquee
        Message::create([
            'name' => $validated['name'],
            'text' => $validated['message'],
            'category' => $validated['affiliation'],
        ]);

        // Log activity
        Activity::create([
            'text' => "{$invitation->name} ({$validated['affiliation']}) mendaftar tiket ({$code})",
            'time' => now()->format('H:i'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil, kode unik tiket telah diterbitkan.',
            'code' => $code,
            'data' => $invitation->load('members'),
        ], 201);
    }

    public function getPublicData(): JsonResponse
    {
        $settings = Setting::first();
        $messages = Message::orderBy('created_at', 'desc')->take(30)->get();
        $activities = Activity::orderBy('created_at', 'desc')->take(20)->get();

        return response()->json([
            'success' => true,
            'settings' => $settings,
            'messages' => $messages,
            'activities' => $activities,
        ]);
    }
}
