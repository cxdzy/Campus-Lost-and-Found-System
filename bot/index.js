/**
 * Campus Lost & Found — Telegram Bot (Finder workflow)
 *
 * State machine:
 *   idle → /found → waiting_for_photo → waiting_for_location → done (→ idle)
 *
 * Two-step Laravel API:
 *   1. POST /api/bot/submit          — uploads image, returns { id }
 *   2. POST /api/bot/update-location — supplies GPS, triggers vision + matching
 *
 * Required env vars:
 *   TELEGRAM_BOT_TOKEN   — from BotFather
 *   LARAVEL_APP_URL      — e.g. https://your-dokploy-domain.com
 */

const TelegramBot = require('node-telegram-bot-api');
const axios       = require('axios');

const BOT_TOKEN   = process.env.TELEGRAM_BOT_TOKEN;
const LARAVEL_URL = process.env.LARAVEL_APP_URL;

if (!BOT_TOKEN || !LARAVEL_URL) {
    console.error('Missing required env vars: TELEGRAM_BOT_TOKEN, LARAVEL_APP_URL');
    process.exit(1);
}

const bot = new TelegramBot(BOT_TOKEN, { polling: true });

// ── State machine ──────────────────────────────────────────────────────────────
// Map<chatId, { state, foundItemId? }>
const sessions = new Map();

const STATE = {
    IDLE:                 'idle',
    WAITING_FOR_PHOTO:    'waiting_for_photo',
    WAITING_FOR_LOCATION: 'waiting_for_location',
};

const getSession  = (chatId) => sessions.get(chatId) ?? { state: STATE.IDLE };
const resetSession = (chatId) => sessions.delete(chatId);

// ── Commands ───────────────────────────────────────────────────────────────────

bot.onText(/\/start/, async (msg) => {
    resetSession(msg.chat.id);
    await bot.sendMessage(msg.chat.id,
        '👋 Welcome to *Campus Lost & Found Bot*!\n\n' +
        'Use /found to report an item you found on campus.\n' +
        'Use /cancel at any time to abort.',
        { parse_mode: 'Markdown' }
    );
});

bot.onText(/\/found/, async (msg) => {
    sessions.set(msg.chat.id, { state: STATE.WAITING_FOR_PHOTO });
    await bot.sendMessage(msg.chat.id,
        '📸 *Step 1 of 2 — Photo*\n\nSend a clear photo of the item you found.',
        { parse_mode: 'Markdown' }
    );
});

bot.onText(/\/cancel/, async (msg) => {
    const had = sessions.has(msg.chat.id);
    resetSession(msg.chat.id);
    await bot.sendMessage(msg.chat.id, had ? '❌ Report cancelled.' : 'Nothing to cancel.');
});

// ── Photo handler ──────────────────────────────────────────────────────────────

bot.on('photo', async (msg) => {
    const chatId  = msg.chat.id;
    const session = getSession(chatId);

    if (session.state !== STATE.WAITING_FOR_PHOTO) {
        await bot.sendMessage(chatId, '⚠️ Please start a report with /found before sending a photo.');
        return;
    }

    try {
        const fileId   = msg.photo[msg.photo.length - 1].file_id;
        const fileRes  = await axios.get(
            `https://api.telegram.org/bot${BOT_TOKEN}/getFile`,
            { params: { file_id: fileId } }
        );
        const filePath = fileRes.data.result?.file_path;

        if (!filePath) {
            await bot.sendMessage(chatId, '❌ Could not retrieve your photo. Please try again with /found.');
            resetSession(chatId);
            return;
        }

        const imageUrl = `https://api.telegram.org/file/bot${BOT_TOKEN}/${filePath}`;
        const caption  = msg.caption ?? '';

        // Step 1: create the FoundItem in Laravel (GPS not yet known)
        const response = await axios.post(
            `${LARAVEL_URL}/api/bot/submit`,
            {
                image_url:        imageUrl,
                caption:          caption,
                telegram_chat_id: String(chatId),
            },
            { headers: { 'Content-Type': 'application/json' }, timeout: 20000 }
        );

        const foundItemId = response.data?.id;
        if (!foundItemId) {
            throw new Error('Laravel did not return an item ID.');
        }

        sessions.set(chatId, {
            state:       STATE.WAITING_FOR_LOCATION,
            foundItemId,
        });

        await bot.sendMessage(chatId,
            '📍 *Step 2 of 2 — Location*\n\n' +
            'Tap the 📎 attachment icon → *Location* → *Send My Current Location*.\n\n' +
            'This pins exactly where you found the item so our AI can match by proximity.',
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
        await bot.sendMessage(chatId, '❌ Something went wrong saving your photo. Please try again with /found.');
        resetSession(chatId);
    }
});

// ── Location handler ───────────────────────────────────────────────────────────

bot.on('location', async (msg) => {
    const chatId  = msg.chat.id;
    const session = getSession(chatId);

    if (session.state !== STATE.WAITING_FOR_LOCATION) {
        await bot.sendMessage(chatId, '⚠️ No active report. Use /found to start.');
        return;
    }

    const { latitude, longitude } = msg.location;

    await bot.sendMessage(chatId, '⏳ Finalising your report…', {
        reply_markup: { remove_keyboard: true },
    });

    try {
        // Step 2: supply GPS — triggers vision tagging + AI matching
        const response = await axios.post(
            `${LARAVEL_URL}/api/bot/update-location`,
            {
                found_item_id: session.foundItemId,
                latitude,
                longitude,
            },
            { headers: { 'Content-Type': 'application/json' }, timeout: 10000 }
        );

        await bot.sendMessage(chatId,
            `✅ *Report submitted!*\n\n` +
            `📋 Report ID: #${response.data?.id ?? session.foundItemId}\n` +
            `📍 Location recorded.\n\n` +
            `Our AI is now tagging and matching your find. ` +
            `If we locate the owner, they will be notified automatically via Telegram.\n\n` +
            `Use /found to submit another item.`,
            { parse_mode: 'Markdown' }
        );
    } catch (err) {
        console.error('Location handler error:', err?.response?.data ?? err.message);
        await bot.sendMessage(chatId,
            '❌ Failed to record your location. Please try again with /found.'
        );
    } finally {
        resetSession(chatId);
    }
});

console.log('Campus Lost & Found bot started (polling)');
