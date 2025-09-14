<?php

// ====================================================================
// === BOT CONFIGURATION (Please fill in your details here) ===
// ====================================================================
define("BOT_TOKEN", "8304200760:AAHPJ9sWj-86AZ5NcFs8_gsEJKalJ9UrPkQ");
define("PRIMARY_ADMIN_ID", 6232671662);
define("ADMIN_USERNAME", "@kowserahmed12");
define("CHANNEL_ID", "@aIIin_one");
define("MIN_WITHDRAW", 50);
define("REFERRAL_BONUS", 3);
define("DAILY_BONUS", 2.0);

// ZINIPAY API CONFIGURATION
define("ZINIPAY_API_KEY", "5112d45fd4f14c2eeb0f79e00ed5f6d7abcdff3703868806");
define("ZINIPAY_API_URL_CREATE", "https://api.zinipay.com/v1/payment/create");
define("WEBHOOK_URL", "https://5112d45fd4f14c2eeb0f79e00ed5f6d7abcdff3703868806/webhook.php"); // এখানে আপনার ডোমেইন বসান

// ====================================================================

// --- Include database and helper functions ---
require_once 'db.php';

// --- Keyboard Layouts ---
function main_reply_keyboard($user_id) {
    $db = loadDb();
    $keyboard = [
        ["🛍️ Products", "💰 Wallet"],
        ["🎁 Daily Bonus", "👥 Refer & Earn"],
        ["📢 Notice", "ℹ️ Support"]
    ];
    if (in_array($user_id, $db['admin_ids'])) {
        array_unshift($keyboard, ["⚙️ Admin Panel"]);
    }
    return ['keyboard' => $keyboard, 'resize_keyboard' => true];
}

function wallet_keyboard() {
    return [
        'inline_keyboard' => [
            [['text' => "📥 Add Money (Auto)", 'callback_data' => 'add_money_auto']],
            [['text' => "📥 Add Money (Manual)", 'callback_data' => 'add_money_manual']],
            [['text' => "📤 Withdraw", 'callback_data' => 'withdraw']],
            [['text' => "📜 History", 'callback_data' => 'transaction_history']],
            [['text' => "⬅️ Back", 'callback_data' => 'back_to_main_menu_inline']]
        ]
    ];
}

function admin_panel_keyboard() {
    return [
        'inline_keyboard' => [
            [['text' => "📊 Dashboard", 'callback_data' => 'admin_dashboard']],
            [['text' => "📦 Manage Products", 'callback_data' => 'admin_manage_products'], ['text' => "👤 User Management", 'callback_data' => 'admin_user_management']],
            [['text' => "🔔 Bot Settings", 'callback_data' => 'admin_bot_settings'], ['text' => "📣 Broadcast", 'callback_data' => 'admin_broadcast_start']],
            [['text' => "📥 Pending Deposits", 'callback_data' => 'admin_pending_deposits'], ['text' => "📤 Pending Withdraws", 'callback_data' => 'admin_pending_withdraws']],
            [['text' => "🗑️ Delete Product by ID", 'callback_data' => 'admin_delete_prod_by_id']],
            [['text' => "👮 Admin Management", 'callback_data' => 'admin_manage_admins']],
            [['text' => "⬅️ Back", 'callback_data' => 'back_to_main_menu_inline']]
        ]
    ];
}

function get_products_keyboard($db) {
    $keyboard_rows = [];
    if (!empty($db['products'])) {
        foreach ($db['products'] as $pid => $pinfo) {
            if ($pinfo['stock'] > 0 || $pinfo['requires_info']) {
                $keyboard_rows[] = [['text' => "Buy {$pinfo['name']}", 'callback_data' => "purchase_{$pid}"]];
            }
        }
    }
    $keyboard_rows[] = [['text' => "⬅️ Back", 'callback_data' => 'back_to_main_menu_inline']];
    return ['inline_keyboard' => $keyboard_rows];
}

function get_admin_product_list($db) {
    $product_list_text = "📦 **বর্তমান পণ্য তালিকা:**\n";
    $keyboard_rows = [];
    if (empty($db['products'])) {
        $product_list_text .= "\nকোনো পণ্য যোগ করা হয়নি।";
    } else {
        foreach ($db['products'] as $pid => $pinfo) {
            $keyboard_rows[] = [['text' => "✏️ {$pinfo['name']} (Stock: {$pinfo['stock']})", 'callback_data' => "edit_prod_menu_{$pid}"]];
        }
    }
    $keyboard_rows[] = [['text' => "➕ Add New Product", 'callback_data' => 'admin_add_product_start']];
    $keyboard_rows[] = [['text' => "⬅️ Back to Admin Panel", 'callback_data' => 'admin_panel']];
    return ['text' => $product_list_text, 'keyboard' => ['inline_keyboard' => $keyboard_rows]];
}

// --- Conversation States (Manual Deposit) ---
$stateFile = 'user_states.json';
function saveState($user_id, $state, $data = null) {
    $states = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
    $states[$user_id] = ['state' => $state, 'data' => $data];
    file_put_contents($stateFile, json_encode($states));
}

function getState($user_id) {
    $states = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
    return $states[$user_id] ?? ['state' => null, 'data' => null];
}

function clearState($user_id) {
    $states = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
    unset($states[$user_id]);
    file_put_contents($stateFile, json_encode($states));
}

// --- Main Bot Logic ---
$update = json_decode(file_get_contents('php://input'), true);
$db = loadDb();

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $from_id = $message['from']['id'];
    $text = $message['text'] ?? '';
    $user_name = $message['from']['first_name'];
    $user_username = $message['from']['username'] ?? null;
    $user_state = getState($from_id);
    $is_admin = in_array($from_id, $db['admin_ids']);
    
    // Initialize user in DB if not exists
    if (!isset($db['users'][$from_id])) {
        $db['users'][$from_id] = [
            "name" => $user_name,
            "username" => $user_username,
            "balance" => 0.0,
            "referrals" => 0,
            "is_blocked" => false,
            "last_daily_bonus" => null,
            "transactions" => [],
            "claimed_free_products" => []
        ];
        // Handle referral
        $start_payload = explode(' ', $text);
        if ($start_payload[0] === '/start' && isset($start_payload[1])) {
            $referrer_id = (int)$start_payload[1];
            if ($referrer_id != $from_id && isset($db['users'][$referrer_id])) {
                $db['users'][$referrer_id]['balance'] += REFERRAL_BONUS;
                $db['users'][$referrer_id]['referrals'] += 1;
                add_transaction($referrer_id, "Referral Bonus +$REFERRAL_BONUS BDT from $user_name", $db);
                send_message($referrer_id, "🎉 আপনার রেফারেল লিংক থেকে `$user_name` জয়েন করেছে এবং আপনার অ্যাকাউন্টে $REFERRAL_BONUS টাকা যোগ হয়েছে।", null, 'Markdown');
            }
        }
        saveDb($db);
    }
    
    // Handle conversation states first
    if ($user_state['state'] === 'GET_MANUAL_DEPOSIT_TRXID') {
        $trx_id = $text;
        $amount = $user_state['data']['amount'];
        $deposit_id = $db['next_deposit_id']++;
        
        $db['pending_deposits'][(string)$deposit_id] = [
            'user_id' => $from_id,
            'user_name' => $user_name,
            'amount' => $amount,
            'trx_id' => $trx_id,
            'status' => 'pending'
        ];
        saveDb($db);
        clearState($from_id);
        
        send_message($chat_id, "আপনার ডিপোজিট রিকোয়েস্টটি পাঠানো হয়েছে। অ্যাডমিন দ্রুত এটি কনফার্ম করবে।", main_reply_keyboard($from_id));
        $admin_text = "🔔 নতুন ডিপোজিট!\n\nUser: $user_name (`$from_id`)\nAmount: $amount BDT\nTrxID: `$trx_id`";
        $admin_keyboard = ['inline_keyboard' => [[['text' => "✅ Approve", 'callback_data' => "approve_dep_$deposit_id"], ['text' => "❌ Reject", 'callback_data' => "reject_dep_$deposit_id"]]]];
        foreach ($db['admin_ids'] as $admin_id) {
            send_message($admin_id, $admin_text, $admin_keyboard, 'Markdown');
        }
        return;
    }

    if ($db['users'][$from_id]['is_blocked'] && !$is_admin) {
        send_message($chat_id, "দুঃখিত, আপনাকে এই বট ব্যবহার থেকে ব্লক করা হয়েছে।");
        return;
    }
    if ($db['bot_status']['status'] === 'off' && !$is_admin) {
        send_message($chat_id, "দুঃখিত, বটটি বর্তমানে বন্ধ আছে। কারণ: {$db['bot_status']['message']}");
        return;
    }

    // Main reply keyboard handlers
    if ($text === "/start") {
        send_message($chat_id, "🌐 **NexGenShop এ স্বাগতম!**\n\nআপনি এখানে আপনার প্রয়োজনীয় ডিজিটাল পণ্য খুঁজে পাবেন। নিচের বোতামগুলো ব্যবহার করে বটটি ব্যবহার করুন।", main_reply_keyboard($from_id), 'Markdown');
    } elseif ($text === "🛍️ Products") {
        $products_info = get_admin_product_list($db);
        send_message($chat_id, $products_info['text'], $products_info['keyboard'], 'Markdown');
    } elseif ($text === "💰 Wallet") {
        $balance = $db['users'][$from_id]['balance'];
        send_message($chat_id, "💰 **Your Wallet**\n\nBalance: " . number_format($balance, 2) . " BDT", wallet_keyboard(), 'Markdown');
    } elseif ($text === "🎁 Daily Bonus") {
        $last_bonus = $db['users'][$from_id]['last_daily_bonus'];
        $now = date('Y-m-d');
        if ($last_bonus && $last_bonus == $now) {
            send_message($chat_id, "আপনি ইতোমধ্যে আজকের বোনাস নিয়েছেন।");
        } else {
            $db['users'][$from_id]['balance'] += DAILY_BONUS;
            $db['users'][$from_id]['last_daily_bonus'] = $now;
            add_transaction($from_id, "Daily Bonus +".DAILY_BONUS." BDT", $db);
            saveDb($db);
            send_message($chat_id, "🎉 আপনার অ্যাকাউন্টে ".DAILY_BONUS." টাকা যোগ হয়েছে।");
        }
    } elseif ($text === "👥 Refer & Earn") {
        $referrals = $db['users'][$from_id]['referrals'];
        send_message($chat_id, "👥 **Refer & Earn**\n\nGet ".REFERRAL_BONUS." BDT for each user you refer. Share your unique link below:\n\n`https://t.me/".BOT_USERNAME."?start=".$from_id."`\n\nYour total referrals: ".$referrals, null, 'Markdown');
    } elseif ($text === "📢 Notice") {
        send_message($chat_id, "📢 **Notice**\n\n{$db['notice']}", null, 'Markdown');
    } elseif ($text === "ℹ️ Support") {
        send_message($chat_id, "For support, please contact: ".ADMIN_USERNAME, null, 'Markdown');
    } elseif ($text === "⚙️ Admin Panel" && $is_admin) {
        send_message($chat_id, "⚙️ **Admin Panel**", admin_panel_keyboard());
    } elseif ($text === "⬅️ Back") {
        send_message($chat_id, "Main Menu", main_reply_keyboard($from_id));
    } else {
        // Handle manual deposit amount if in state
        if ($user_state['state'] === 'GET_DEPOSIT_AMOUNT_MANUAL') {
            $amount = (float)$text;
            if ($amount > 0) {
                send_message($chat_id, "অনুগ্রহ করে আপনার Transaction ID (TRX ID) পাঠান।", null);
                saveState($from_id, 'GET_MANUAL_DEPOSIT_TRXID', ['amount' => $amount]);
            } else {
                send_message($chat_id, "ভুল ইনপুট। শুধু সংখ্যা ব্যবহার করুন।");
            }
        } else {
            // General text message handler
            send_message($chat_id, "দুঃখিত, আমি এই কমান্ডটি বুঝতে পারিনি।");
        }
    }
}

// Handle callback queries
if (isset($update['callback_query'])) {
    $query = $update['callback_query'];
    $data = $query['data'];
    $chat_id = $query['message']['chat']['id'];
    $message_id = $query['message']['message_id'];
    $from_id = $query['from']['id'];
    $is_admin = in_array($from_id, $db['admin_ids']);
    
    // User actions
    if ($data === 'back_to_main_menu_inline') {
        edit_message($chat_id, $message_id, "Main Menu", main_reply_keyboard($from_id));
    } elseif ($data === 'add_money_auto') {
        // Generate ZiniPay payment link
        $amount = 10; // Example amount, you can make this dynamic
        $order_id = "deposit_" . $from_id . "_" . time();
        $api_data = [
            "amount" => $amount,
            "order_id" => $order_id,
            "api_key" => ZINIPAY_API_KEY,
            "customer_name" => $query['from']['first_name'],
            "customer_id" => $from_id,
            "redirect_url" => "https://YOUR_DOMAIN/index.php",
            "cancel_url" => "https://YOUR_DOMAIN/index.php",
            "webhook_url" => WEBHOOK_URL
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, ZINIPAY_API_URL_CREATE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        curl_close($ch);
        $response_json = json_decode($response, true);

        if ($response_json && $response_json['status'] === 'success') {
            $payment_url = $response_json['redirect_url'];
            $keyboard = ['inline_keyboard' => [[['text' => "পেমেন্ট করুন", 'url' => $payment_url]]]];
            edit_message($chat_id, $message_id, "আপনার পেমেন্ট লিংক তৈরি হয়েছে। নিচের বাটনে ক্লিক করে পেমেন্ট সম্পন্ন করুন।", $keyboard);
        } else {
            edit_message($chat_id, $message_id, "❌ পেমেন্ট লিংক তৈরি করতে সমস্যা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন।");
        }
    } elseif ($data === 'add_money_manual') {
        edit_message($chat_id, $message_id, "আপনি কত টাকা যোগ করতে চান? (ম্যানুয়াল)");
        saveState($from_id, 'GET_DEPOSIT_AMOUNT_MANUAL');
    }

    // Admin actions
    if ($is_admin) {
        // ... all admin callback handlers here
        if ($data === 'admin_panel') {
            edit_message($chat_id, $message_id, "⚙️ **Admin Panel**", admin_panel_keyboard());
        } elseif ($data === 'admin_manage_products') {
            $products_info = get_admin_product_list($db);
            edit_message($chat_id, $message_id, $products_info['text'], $products_info['keyboard'], 'Markdown');
        }
        // ... and so on for other admin buttons
    }
}
?>
