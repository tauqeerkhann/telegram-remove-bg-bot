<?php
// Bot configuration
define('BOT_TOKEN', 'Place_Your_Token_Here');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('USERS_FILE', __DIR__ . '/users.json');
define('ERROR_LOG', __DIR__ . '/error.log');
define('MIN_WITHDRAWAL', 1000); // Minimum points required for withdrawal
define('EARN_COOLDOWN', 3600); // 1 hour cooldown between earning (in seconds)
define('EARN_AMOUNT', 10); // Points earned per click

// Initialize bot (clear webhook)
function initializeBot() {
    try {
        file_get_contents(API_URL . 'setWebhook?url=');
        return true;
    } catch (Exception $e) {
        logError("Initialization failed: " . $e->getMessage());
        return false;
    }
}

// Error logging
function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(ERROR_LOG, "[$timestamp] $message\n", FILE_APPEND);
}

// Data management
function loadUsers() {
    try {
        if (!file_exists(USERS_FILE)) {
            file_put_contents(USERS_FILE, json_encode([]));
        }
        return json_decode(file_get_contents(USERS_FILE), true) ?: [];
    } catch (Exception $e) {
        logError("Load users failed: " . $e->getMessage());
        return [];
    }
}

function saveUsers($users) {
    try {
        file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
        return true;
    } catch (Exception $e) {
        logError("Save users failed: " . $e->getMessage());
        return false;
    }
}

// Message sending with inline keyboard
function sendMessage($chat_id, $text, $keyboard = null) {
    try {
        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($keyboard) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $keyboard
            ]);
        }
        
        $url = API_URL . 'sendMessage?' . http_build_query($params);
        file_get_contents($url);
        return true;
    } catch (Exception $e) {
        logError("Send message failed: " . $e->getMessage());
        return false;
    }
}

// Edit existing message
function editMessage($chat_id, $message_id, $text, $keyboard = null) {
    try {
        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($keyboard) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $keyboard
            ]);
        }
        
        $url = API_URL . 'editMessageText?' . http_build_query($params);
        file_get_contents($url);
        return true;
    } catch (Exception $e) {
        logError("Edit message failed: " . $e->getMessage());
        return false;
    }
}

// Answer callback query
function answerCallback($callback_id, $text = '', $show_alert = false) {
    try {
        $params = [
            'callback_query_id' => $callback_id,
            'text' => $text,
            'show_alert' => $show_alert
        ];
        
        $url = API_URL . 'answerCallbackQuery?' . http_build_query($params);
        file_get_contents($url);
        return true;
    } catch (Exception $e) {
        logError("Answer callback failed: " . $e->getMessage());
        return false;
    }
}

// Main keyboard
function getMainKeyboard() {
    return [
        [['text' => '💰 Earn', 'callback_data' => 'earn'], ['text' => '💳 Balance', 'callback_data' => 'balance']],
        [['text' => '🏆 Leaderboard', 'callback_data' => 'leaderboard'], ['text' => '👥 Referrals', 'callback_data' => 'referrals']],
        [['text' => '🏧 Withdraw', 'callback_data' => 'withdraw'], ['text' => '❓ Help', 'callback_data' => 'help']]
    ];
}

// Withdraw keyboard
function getWithdrawKeyboard($chat_id) {
    return [
        [['text' => '💰 PayPal', 'callback_data' => 'withdraw_paypal_' . $chat_id]],
        [['text' => '🔙 Back', 'callback_data' => 'main_menu']]
    ];
}

// Process commands and callbacks
function processUpdate($update) {
    $users = loadUsers();
    
    if (isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        $text = trim($update['message']['text'] ?? '');
        
        // Create new user if doesn't exist
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'balance' => 0,
                'last_earn' => 0,
                'referrals' => 0,
                'ref_code' => substr(md5($chat_id . time()), 0, 8),
                'referred_by' => null,
                'username' => $update['message']['chat']['username'] ?? null
            ];
        }
        
        if (strpos($text, '/start') === 0) {
            $ref = explode(' ', $text)[1] ?? null;
            if ($ref && !$users[$chat_id]['referred_by']) {
                foreach ($users as $id => $user) {
                    if ($user['ref_code'] === $ref && $id != $chat_id) {
                        $users[$chat_id]['referred_by'] = $id;
                        $users[$id]['referrals']++;
                        $users[$id]['balance'] += 50; // Referral bonus
                        sendMessage($id, "🎉 New referral! +50 points bonus!");
                        break;
                    }
                }
            }
            
            $msg = "👋 Welcome to <b>Earning Bot</b>!\n\n"
                 . "💰 <b>Earn points</b> by clicking the Earn button\n"
                 . "👥 <b>Invite friends</b> using your referral link and earn 50 points for each referral\n"
                 . "💵 <b>Withdraw</b> your earnings when you reach " . MIN_WITHDRAWAL . " points\n\n"
                 . "🔹 Your referral code: <code>{$users[$chat_id]['ref_code']}</code>\n"
                 . "🔹 Share your link: https://t.me/" . (explode('/', API_URL)[3] ?? 'YourBotName') . "?start={$users[$chat_id]['ref_code']}";
            
            sendMessage($chat_id, $msg, getMainKeyboard());
        } elseif ($text === '/balance') {
            sendMessage($chat_id, "💰 Your balance: <b>{$users[$chat_id]['balance']}</b> points", getMainKeyboard());
        } elseif ($text === '/referral') {
            $msg = "👥 <b>Referral Program</b>\n\n"
                 . "🔹 Your referral code: <code>{$users[$chat_id]['ref_code']}</code>\n"
                 . "🔹 Total referrals: <b>{$users[$chat_id]['referrals']}</b>\n"
                 . "🔹 Share your link: https://t.me/" . (explode('/', API_URL)[3] ?? 'YourBotName') . "?start={$users[$chat_id]['ref_code']}\n\n"
                 . "💸 You earn <b>50 points</b> for each active referral!";
            sendMessage($chat_id, $msg, getMainKeyboard());
        }
    } elseif (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $chat_id = $callback['message']['chat']['id'];
        $message_id = $callback['message']['message_id'];
        $data = $callback['data'];
        
        // Initialize user if doesn't exist (shouldn't happen but just in case)
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'balance' => 0,
                'last_earn' => 0,
                'referrals' => 0,
                'ref_code' => substr(md5($chat_id . time()), 0, 8),
                'referred_by' => null,
                'username' => $callback['message']['chat']['username'] ?? null
            ];
        }
        
        // Process callback data
        switch (true) {
            case $data === 'earn':
                $cooldown = time() - $users[$chat_id]['last_earn'];
                if ($cooldown >= EARN_COOLDOWN) {
                    $users[$chat_id]['balance'] += EARN_AMOUNT;
                    $users[$chat_id]['last_earn'] = time();
                    $msg = "✅ You earned <b>" . EARN_AMOUNT . " points</b>!\n"
                         . "💰 Your balance: <b>{$users[$chat_id]['balance']}</b> points\n\n"
                         . "⏳ Next earning available in " . gmdate("H:i:s", EARN_COOLDOWN);
                    answerCallback($callback['id'], "You earned " . EARN_AMOUNT . " points!");
                } else {
                    $remaining = EARN_COOLDOWN - $cooldown;
                    $msg = "⏳ Please wait <b>" . gmdate("H:i:s", $remaining) . "</b> before earning again\n\n"
                         . "💰 Your balance: <b>{$users[$chat_id]['balance']}</b> points";
                    answerCallback($callback['id'], "Please wait " . gmdate("H:i:s", $remaining) . " before earning again");
                }
                editMessage($chat_id, $message_id, $msg, getMainKeyboard());
                break;
                
            case $data === 'balance':
                $msg = "💰 <b>Balance Information</b>\n\n"
                     . "🔹 Current balance: <b>{$users[$chat_id]['balance']}</b> points\n"
                     . "🔹 Minimum withdrawal: <b>" . MIN_WITHDRAWAL . "</b> points\n"
                     . "🔹 Referral earnings: <b>{$users[$chat_id]['referrals'] * 50}</b> points";
                editMessage($chat_id, $message_id, $msg, getMainKeyboard());
                answerCallback($callback['id']);
                break;
                
            case $data === 'leaderboard':
                // Sort users by balance
                uasort($users, function($a, $b) {
                    return $b['balance'] - $a['balance'];
                });
                
                $leaderboard = "🏆 <b>Top 10 Leaderboard</b>\n\n";
                $position = 1;
                foreach (array_slice($users, 0, 10) as $id => $user) {
                    $username = $user['username'] ? "@" . $user['username'] : "User #$id";
                    $leaderboard .= "$position. $username - <b>{$user['balance']}</b> points\n";
                    $position++;
                }
                
                // Add current user position if not in top 10
                if (!array_key_exists($chat_id, array_slice($users, 0, 10, true))) {
                    $current_position = array_search($chat_id, array_keys($users)) + 1;
                    $leaderboard .= "\nYour position: <b>$current_position</b> of " . count($users);
                }
                
                editMessage($chat_id, $message_id, $leaderboard, getMainKeyboard());
                answerCallback($callback['id']);
                break;
                
            case $data === 'referrals':
                $msg = "👥 <b>Referral Program</b>\n\n"
                     . "🔹 Your referral code: <code>{$users[$chat_id]['ref_code']}</code>\n"
                     . "🔹 Total referrals: <b>{$users[$chat_id]['referrals']}</b>\n"
                     . "🔹 Earned from referrals: <b>{$users[$chat_id]['referrals'] * 50}</b> points\n\n"
                     . "Share your link: https://t.me/" . (explode('/', API_URL)[3] ?? 'YourBotName') . "?start={$users[$chat_id]['ref_code']}\n\n"
                     . "💸 You earn <b>50 points</b> for each active referral!";
                editMessage($chat_id, $message_id, $msg, getMainKeyboard());
                answerCallback($callback['id']);
                break;
                
            case $data === 'withdraw':
                if ($users[$chat_id]['balance'] >= MIN_WITHDRAWAL) {
                    $msg = "🏧 <b>Withdrawal Options</b>\n\n"
                         . "💰 Your balance: <b>{$users[$chat_id]['balance']}</b> points\n"
                         . "🔹 Minimum withdrawal: <b>" . MIN_WITHDRAWAL . "</b> points\n\n"
                         . "Select withdrawal method:";
                    editMessage($chat_id, $message_id, $msg, getWithdrawKeyboard($chat_id));
                } else {
                    $msg = "⚠️ <b>Withdrawal Not Available</b>\n\n"
                         . "You need at least <b>" . MIN_WITHDRAWAL . "</b> points to withdraw.\n"
                         . "💰 Your current balance: <b>{$users[$chat_id]['balance']}</b> points\n\n"
                         . "Keep earning to reach the minimum amount!";
                    editMessage($chat_id, $message_id, $msg, getMainKeyboard());
                    answerCallback($callback['id'], "You need " . (MIN_WITHDRAWAL - $users[$chat_id]['balance']) . " more points to withdraw", true);
                }
                break;
                
            case strpos($data, 'withdraw_paypal_') === 0:
                $msg = "📝 <b>PayPal Withdrawal</b>\n\n"
                     . "To withdraw via PayPal, please send your PayPal email to the bot admin.\n\n"
                     . "💰 Withdrawal amount: <b>{$users[$chat_id]['balance']}</b> points\n"
                     . "👤 Your account will be reset after withdrawal.";
                editMessage($chat_id, $message_id, $msg, getMainKeyboard());
                answerCallback($callback['id'], "Admin will contact you for PayPal withdrawal", true);
                break;
                
            case $data === 'help':
                $msg = "❓ <b>Help Center</b>\n\n"
                     . "💰 <b>Earning Points</b>\n"
                     . "- Click the Earn button to get " . EARN_AMOUNT . " points\n"
                     . "- Wait " . gmdate("H:i:s", EARN_COOLDOWN) . " between earnings\n\n"
                     . "👥 <b>Referral Program</b>\n"
                     . "- Share your referral link to earn 50 points per active referral\n"
                     . "- You can find your link in the Referrals section\n\n"
                     . "💵 <b>Withdrawals</b>\n"
                     . "- Minimum withdrawal: " . MIN_WITHDRAWAL . " points\n"
                     . "- Currently only PayPal withdrawals available\n\n"
                     . "📊 <b>Leaderboard</b>\n"
                     . "- Check top 10 users by points balance";
                editMessage($chat_id, $message_id, $msg, getMainKeyboard());
                answerCallback($callback['id']);
                break;
                
            case $data === 'main_menu':
                $msg = "👋 Welcome back to <b>Earning Bot</b>!\n\n"
                     . "💰 <b>Earn points</b> by clicking the Earn button\n"
                     . "👥 <b>Invite friends</b> using your referral link\n"
                     . "💵 <b>Withdraw</b> your earnings when you reach " . MIN_WITHDRAWAL . " points";
                editMessage($chat_id, $message_id, $msg, getMainKeyboard());
                answerCallback($callback['id']);
                break;
        }
    }
    
    saveUsers($users);
}

// Webhook handler
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if ($update) {
    processUpdate($update);
} else {
    // For testing via browser
    if (php_sapi_name() === 'cli') {
        echo "Telegram Bot is running in CLI mode.\n";
        initializeBot();
    } else {
        echo "<h1>Telegram Bot is running!</h1>";
        echo "<p>This is a webhook endpoint for your Telegram bot.</p>";
        echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
        
        // Display basic stats
        if (file_exists(USERS_FILE)) {
            $users = json_decode(file_get_contents(USERS_FILE), true);
            echo "<h2>Bot Statistics</h2>";
            echo "<p>Total users: " . count($users) . "</p>";
            
            $total_balance = 0;
            foreach ($users as $user) {
                $total_balance += $user['balance'];
            }
            echo "<p>Total points distributed: $total_balance</p>";
        }
    }
}