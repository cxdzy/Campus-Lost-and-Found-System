<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FoundItem;
use App\Models\LostItem;
use App\Models\MatchAlert;
use App\Models\ReownershipClaim;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MatchAlertsController extends Controller
{
    public function index()
    {
        $alerts = MatchAlert::with([
            'lostItem',
            'lostItem.lostItem',
            'lostItem.lostItem.loser',
            'lostItem.lostItem.loser.user',
            'lostItem.category',
            'foundItem',
            'foundItem.foundItem',
            'foundItem.foundItem.aiTags',
            'foundItem.category',
        ])->latest()->get()->map(fn (MatchAlert $alert) => $this->formatAlert($alert));

        return Inertia::render('Admin/MatchAlerts', [
            'alerts' => $alerts,
        ]);
    }

    public function verify(Request $request, MatchAlert $matchAlert): JsonResponse
    {
        $lostRecord  = LostItem::where('item_id', $matchAlert->lost_item_id)->with('loser.user')->first();
        $foundRecord = FoundItem::where('item_id', $matchAlert->found_item_id)->first();

        if (!$lostRecord || !$foundRecord) {
            return response()->json(['message' => 'Linked items not found.'], 404);
        }

        $loser = $lostRecord->loser;
        if (!$loser?->user?->telegram_chat_id) {
            return response()->json(['message' => 'Student has no linked Telegram account.'], 422);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Upsert: if a pending (unclaimed) claim exists for this found item, refresh it
        ReownershipClaim::updateOrCreate(
            [
                'found_item_id' => $foundRecord->item_id,
                'loser_id'      => $loser->user_id,
                'claimed_at'    => null,
            ],
            [
                'security_guard_id' => Auth::id(),
                'otp_code'          => $otp,
                'expires_at'        => now()->addMinutes(15),
            ]
        );

        $foundTitle = $matchAlert->foundItem->title_description;
        $score      = round($matchAlert->match_score * 100, 1);
        $message    = "🔐 <b>Claim OTP — Campus Lost &amp; Found</b>\n\n"
                    . "A security officer has verified your match ({$score}% confidence) for:\n"
                    . "<b>{$foundTitle}</b>\n\n"
                    . "Your one-time claim code is:\n"
                    . "<b>{$otp}</b>\n\n"
                    . "⏱ This code expires in <b>15 minutes</b>.\n"
                    . "Show it to the security desk to collect your item.";

        app(TelegramService::class)->sendMessage(
            $loser->user->telegram_chat_id,
            $message,
            $foundRecord->item_id,
            redactPayload: true,
        );

        return response()->json(['message' => 'OTP sent to student via Telegram.']);
    }

    private function formatAlert(MatchAlert $alert): array
    {
        $lostBase    = $alert->lostItem;
        $lostRecord  = $lostBase?->lostItem;
        $foundBase   = $alert->foundItem;
        $foundRecord = $foundBase?->foundItem;

        $imagePath = $foundRecord?->image_path;
        if ($imagePath && !Str::startsWith($imagePath, ['http://', 'https://'])) {
            $imagePath = Storage::disk('public')->exists($imagePath)
                ? '/storage/' . $imagePath
                : '/storage/' . $imagePath;
        }

        $hasPendingClaim = $foundRecord
            ? ReownershipClaim::where('found_item_id', $foundRecord->item_id)
                ->whereNull('claimed_at')
                ->where('expires_at', '>', now())
                ->exists()
            : false;

        return [
            'id'               => $alert->id,
            'match_score'      => round($alert->match_score * 100, 1),
            'is_notified'      => $alert->is_notified,
            'created_at'       => $alert->created_at?->toISOString(),
            'has_pending_claim' => $hasPendingClaim,
            'lost' => [
                'id'           => $lostBase?->id,
                'title'        => $lostBase?->title_description,
                'category'     => $lostBase?->category?->category_name,
                'location'     => $lostBase?->location_name,
                'latitude'     => $lostBase?->latitude,
                'longitude'    => $lostBase?->longitude,
                'status'       => $lostBase?->status,
                'student_name' => $lostRecord?->loser?->user?->name,
                'matric'       => $lostRecord?->loser?->matric_number,
            ],
            'found' => [
                'id'        => $foundBase?->id,
                'title'     => $foundBase?->title_description,
                'category'  => $foundBase?->category?->category_name,
                'location'  => $foundBase?->location_name,
                'image_url' => $imagePath,
                'tags'      => $foundRecord?->aiTags?->pluck('tag_name')?->all() ?? [],
            ],
        ];
    }
}
