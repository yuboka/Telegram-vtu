<?php
$secret = "sk_test_xxxxxxx"; // Paystack SECRET key
$chat_id = $_GET['chat_id'];

$curl = curl_init();

$data = [
  "email" => "user_$chat_id@example.com",
  "amount" => 2000 * 100,   // 2000 NGN
  "metadata" => [
      "chat_id" => $chat_id
  ],
  "callback_url" => "https://yourdomain.com/paystack/verify.php"
];

curl_setopt_array($curl, [
  CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer $secret",
    "Content-Type: application/json"
  ],
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode($data)
]);

$response = curl_exec($curl);
curl_close($curl);

$res = json_decode($response, true);

if ($res['status']) {
    header("Location: " . $res['data']['authorization_url']);
    exit;
} else {
    echo "Error creating payment.";
}
?>
