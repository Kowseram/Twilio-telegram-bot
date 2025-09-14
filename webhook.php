<?php
// ====================================================================
// === ZINIPAY WEBHOOK RECEIVER ===
// ====================================================================

// Configuration (Must be same as index.php)
define("BOT_TOKEN", "8304200760:AAHPJ9sWj-86AZ5NcFs8_gsEJKalJ9UrPkQ");
define("5112d45fd4f14c2eeb0f79e00ed5f6d7abcdff3703868806", "আপনার_এপিআই_কী_এখানে");

// --- Database Functions ---
require_once 'db.php';

// --- Main Webhook Logic ---
$payload = json_decode(file_get_contents('php://input'), true);
$db = loadDb();

// Check if it's a valid ZiniPay webhook
if ($payload && isset($payload['status']) && $payload['status'] === 'success') {
    $invoice_id = $payload['invoiceId'];
    $amount = $payload['amount'];
    $customer_id = $payload['metadata']['customerId'];
    
    // Process the payment
    if (isset($db['users'][$customer_id])) {
        $db['users'][$customer_id]['balance'] += $amount;
        add_transaction($customer_id, "Auto Deposit +{$amount} BDT", $db);
        saveDb($db);
        
        // Notify the user via Telegram
        $telegram_url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
        $data = [
            'chat_id' => $customer_id,
            'text' => "🎉 আপনার অ্যাকাউন্টে {$amount} টাকা সফলভাবে যোগ হয়েছে।",
        ];
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
            ],
        ];
        $context = stream_context_create($options);
        file_get_contents($telegram_url, false, $context);
    }
    
    // Send a 200 OK response back to ZiniPay
    http_response_code(200);
    echo "Payment received and processed.";
} else {
    // Log invalid requests
    http_response_code(400);
    echo "Invalid request or payment failed.";
}
?>
