<?php
$secret = "sk_test_xxxxxxx";
$reference = $_GET['reference'];

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://api.paystack.co/transaction/verify/$reference",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer $secret"
  ]
]);

$response = curl_exec($curl);
curl_close($curl);

$res = json_decode($response, true);

if ($res['data']['status'] == "success") {
    $chat_id = $res['data']['metadata']['chat_id'];

    // SEND SUCCESS MESSAGE TO TELEGRAM
    $token = "YOUR_TELEGRAM_BOT_TOKEN";
    $msg = urlencode("✅ Payment successful!\n\nReference: $reference");

    file_get_contents("https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=$msg");

    echo "Payment Verified!";
} else {
    echo "Payment Failed.";
}
?>
