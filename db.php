<?php

// --- Database Functions ---
function saveDb($db) {
    file_put_contents('nexgenshop_data_final.json', json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadDb() {
    if (file_exists('nexgenshop_data_final.json')) {
        return json_decode(file_get_contents('nexgenshop_data_final.json'), true);
    }
    return [];
}

function add_transaction($user_id, $details, &$db) {
    if (!isset($db['users'][$user_id]['transactions'])) {
        $db['users'][$user_id]['transactions'] = [];
    }
    $now = date("d/m/y, h:i A");
    array_unshift($db['users'][$user_id]['transactions'], "[$now] " . $details);
    $db['users'][$user_id]['transactions'] = array_slice($db['users'][$user_id]['transactions'], 0, 15);
}

function send_message($chat_id, $text, $keyboard = null, $parse_mode = null) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
    ];

    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    if ($parse_mode) {
        $data['parse_mode'] = $parse_mode;
    }

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}

function edit_message($chat_id, $message_id, $text, $keyboard = null, $parse_mode = null) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/editMessageText';
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
    ];

    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    if ($parse_mode) {
        $data['parse_mode'] = $parse_mode;
    }

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}

?>
