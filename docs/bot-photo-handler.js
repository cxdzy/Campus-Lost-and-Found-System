/**
 * Campus Lost & Found — Telegram Bot
 *
 * Flow enforced by state machine:
 *   idle → /found → waiting_for_photo → waiting_for_location → done (→ idle)
 *
 * Requires:  npm install node-telegram-bot-api axios express
 * Env vars:
 *   TELEGRAM_BOT_TOKEN   — from BotFather
 *   LARAVEL_APP_URL      — e.g. http://77.42.84.234:8000
 *   BOT_WEBHOOK_PORT     — port for the /notify-match inbound endpoint (default 3001)
 */

const TelegramBot = require('node-telegram-bot-api');
const axios       = require('axios');
const express     = require('express');

const BOT_TOKEN    = process.env.TELEGRAM_BOT_TOKEN;
const LARAVEL_URL  = process.env.LARAVEL_APP_URL;
const WEBHOOK_PORT = parseInt(process.env.BOT_WEBHOOK_PORT ?? '3001', 10);

if (!BOT_TOKEN || !LARAVEL_URL) {
    console.error('Missing required env vars: TELEGRAM_BOT_TOKEN, LARAVEL_APP_URL');
    process.exit(1);
}

const bot = new TelegramBot(BOT_TOKEN, { polling: true });

// ── State machine ─────────────────────────────────────────────────────────────
// Map<chatId, { state, fileId, caption }>
const sessions = new Map();

const STATE = {
    IDLE:                 'idle',
    WAITING_FOR_PHOTO:    'waiting_for_photo',
    WAITING_FOR_LOCATION: 'waiting_for_location',
};

function resetSession(chatId) {
    sessions.delete(chatId);
}

function getSession(chatId) {
    return sessions.get(chatId) ?? { state: STATE.IDLE };
}

// ── Commands ──────────────────────────────────────────────────────────────────

bot.onText(/\/start/, async (msg) => {
    const chatId = msg.chat.id;
    resetSession(chatId);
    await bot.sendMessage(chatId,
        '👋 Welcome to *Campus Lost & Found Bot*!\n\n' +
        'Use /found to report an item you found on campus.\n' +
        'Use /cancel at any time to abort.',
        { parse_mode: 'Markdown' }
    );
});

bot.onText(/\/found/, async (msg) => {
    const chatId = msg.chat.id;
    sessions.set(chatId, { state: STATE.WAITING_FOR_PHOTO });

    await bot.sendMessage(chatId,
        '📸 *Step 1 of 2 — Photo*\n\nPlease send a clear photo of the item you found.',
        { parse_mode: 'Markdown' }
    );
});

bot.onText(/\/cancel/, async (msg) => {
    const chatId = msg.chat.id;
    const had = sessions.has(chatId);
    resetSession(chatId);
    await bot.sendMessage(chatId, had ? '❌ Report cancelled.' : 'Nothing to cancel.');
});

// ── Photo handler ─────────────────────────────────────────────────────────────

bot.on('photo', async (msg) => {
    const chatId  = msg.chat.id;
    const session = getSession(chatId);

    if (session.state !== STATE.WAITING_FOR_PHOTO) {
        await bot.sendMessage(chatId,
            '⚠️ Please start a report with /found before sending a photo.'
        );
        return;
    }

    try {
        // Highest-resolution file is always the last element
        const fileId    = msg.photo[msg.photo.length - 1].file_id;
        const fileRes   = await axios.get(
            `https://api.telegram.org/bot${BOT_TOKEN}/getFile`,
            { params: { file_id: fileId } }
        );
        const filePath  = fileRes.data.result?.file_path;

        if (!filePath) {
            await bot.sendMessage(chatId, '❌ Could not retrieve your photo. Please try again.');
            resetSession(chatId);
            return;
        }

        const imageUrl = `https://api.telegram.org/file/bot${BOT_TOKEN}/${filePath}`;
        const caption  = msg.caption || '';

        // Advance state
        sessions.set(chatId, {
            state:    STATE.WAITING_FOR_LOCATION,
            imageUrl,
            caption,
        });

        await bot.sendMessage(chatId,
            '📍 *Step 2 of 2 — Location*\n\n' +
            'Tap the 📎 attachment icon → *Location* → *Send My Current Location*.\n\n' +
            'This pins exactly where you found the item on the map.',
            {
                parse_mode:   'Markdown',
                reply_markup: {
                    keyboard: [[
                        { text: '📍 Share My Location', request_location: true },
                    ]],
                    resize_keyboard:   true,
                    one_time_keyboard: true,
                },
            }
        );
    } catch (err) {
        console.error('Photo handler error:', err?.response?.data ?? err.message);
        await bot.sendMessage(chatId, '❌ Something went wrong. Please try again.');
        resetSession(chatId);
    }
});

// ── Location handler ──────────────────────────────────────────────────────────

bot.on('location', async (msg) => {
    const chatId  = msg.chat.id;
    const session = getSession(chatId);

    if (session.state !== STATE.WAITING_FOR_LOCATION) {
        await bot.sendMessage(chatId, '⚠️ No active report. Use /found to start.');
        return;
    }

    const { latitude, longitude } = msg.location;

    await bot.sendMessage(chatId, '⏳ Submitting your report…', {
        reply_markup: { remove_keyboard: true },
    });

    try {
        const response = await axios.post(
            `${LARAVEL_URL}/api/bot/submit`,
            {
                image_url:        session.imageUrl,
                caption:          session.caption,
                latitude:         latitude,
                longitude:        longitude,
                telegram_chat_id: String(chatId),
            },
            {
                headers: { 'Content-Type': 'application/json' },
                timeout: 15000,
            }
        );

        const itemId = response.data?.id;
        await bot.sendMessage(chatId,
            `✅ *Report submitted!*\n\n` +
            `📋 Report ID: #${itemId}\n` +
            `📍 Location recorded.\n\n` +
            `Our AI is now tagging and matching your find. ` +
            `If we find the owner, they will be notified automatically.\n\n` +
            `Use /found to submit another item.`,
            { parse_mode: 'Markdown' }
        );
    } catch (err) {
        console.error('Submission error:', err?.response?.data ?? err.message);
        await bot.sendMessage(chatId,
            '❌ Failed to submit. Please try again with /found.'
        );
    } finally {
        // Always clear session after dispatch attempt
        resetSession(chatId);
    }
});

// ── Guard: block direct photo uploads without /found ─────────────────────────
// (handled inside the photo handler above via state check)

// ── Inbound match-notification webhook ───────────────────────────────────────
// Laravel's MatchingService POSTs here when a match score exceeds the threshold.

const app = express();
app.use(express.json());

app.post('/notify-match', async (req, res) => {
    const { telegram_chat_id, match_score, lost_title, found_title, found_location } = req.body;

    if (!telegram_chat_id) {
        return res.status(400).json({ error: 'telegram_chat_id required' });
    }

    try {
        await bot.sendMessage(telegram_chat_id,
            `🔔 *Potential Match Found!*\n\n` +
            `Your lost item *"${lost_title}"* may match a found item:\n\n` +
            `📦 Found item: *${found_title}*\n` +
            `📍 Location: ${found_location}\n` +
            `🎯 Confidence: ${match_score}%\n\n` +
            `Please visit the Campus Lost & Found office to verify and claim your item.`,
            { parse_mode: 'Markdown' }
        );
        res.json({ ok: true });
    } catch (err) {
        console.error('Notify error:', err.message);
        res.status(500).json({ error: err.message });
    }
});

app.listen(WEBHOOK_PORT, () => {
    console.log(`Bot notification endpoint listening on port ${WEBHOOK_PORT}`);
});

console.log('Campus Lost & Found bot started (polling)');
