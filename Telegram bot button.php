<?php
$token = "YOUR_TELEGRAM_BOT_TOKEN";
$api = "https://api.telegram.org/bot$token/";
$chat_id = $_GET['chat_id'];

$pay_url = "https://yourdomain.com/paystack/create_payment.php?chat_id=$chat_id";

$data = [
    'chat_id' => $chat_id,
    'text' => "Click below to make payment",
    'reply_markup' => json_encode([
        'inline_keyboard' => [
            [
                ['text' => '💳 PAY NOW', 'url' => $pay_url]
            ]
        ]
    ])
];

file_get_contents($api . "sendMessage?" . http_build_query($data));
?>
