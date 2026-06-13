<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\LostItem;
use App\Models\MatchAlert;
use App\Models\ReownershipClaim;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentClaimController extends Controller
{
    public function requestOtp(Item $item): JsonResponse
    {
        $user = Auth::user();

        // Verify the lost item belongs to this student
        $lostRecord = LostItem::where('item_id', $item->id)
            ->where('loser_id', $user->loser?->user_id)
            ->first();

        if (!$lostRecord) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        if ($item->status !== 'Matched') {
            return response()->json(['message' => 'This item does not have an active match.'], 422);
        }

        if (!$user->telegram_chat_id) {
            return response()->json(['message' => 'Link your Telegram account first to receive the OTP.'], 422);
        }

        $alert = MatchAlert::where('lost_item_id', $item->id)
            ->orderByDesc('match_score')
            ->first();

        if (!$alert) {
            return response()->json(['message' => 'No match alert found for this item.'], 404);
        }

        $otp = str_pad((string) \random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::beginTransaction();
        try {
            ReownershipClaim::updateOrCreate(
                [
                    'found_item_id' => $alert->found_item_id,
                    'loser_id'      => $user->loser->user_id,
                    'claimed_at'    => null,
                ],
                [
                    'otp_code'   => $otp,
                    'expires_at' => now()->addMinutes(15),
                ]
            );
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }

        $foundTitle = $alert->foundItem?->title_description ?? 'your item';
        $score      = round($alert->match_score * 100, 1);
        $message    = "🔐 <b>Your Claim OTP — Campus Lost &amp; Found</b>\n\n"
                    . "A high-confidence match ({$score}%) was found for:\n"
                    . "<b>{$foundTitle}</b>\n\n"
                    . "Your one-time claim code is:\n"
                    . "<b>{$otp}</b>\n\n"
                    . "⏱ This code expires in <b>15 minutes</b>.\n"
                    . "Show it to the security desk to collect your item.";

        app(TelegramService::class)->sendMessage(
            $user->telegram_chat_id,
            $message,
            $alert->found_item_id,
            redactPayload: true,
        );

        return response()->json(['message' => 'OTP sent to your Telegram. Show it at the security desk.']);
    }
}
