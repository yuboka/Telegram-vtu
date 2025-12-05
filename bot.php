<?php
// bot.php
// Telegram VTU bot using Sabuss VTU API (single-file)
// Requirements: PHP 7.2+ with cURL and PDO_SQLITE enabled.

// ---------- CONFIGURATION (use env vars in production!) ----------
$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: 'YOUR_TELEGRAM_BOT_TOKEN';
$SABUSS_API_KEY      = getenv('SABUSS_API_KEY') ?: 'YOUR_SABUSS_API_KEY';
$SABUSS_PIN          = getenv('SABUSS_PIN') ?: '0000';
$WEBHOOK_SECRET      = getenv('WEBHOOK_SECRET') ?: 'change_this_secret'; // part of webhook URL to prevent random posts
$DB_FILE             = __DIR__ . '/vtu_bot.sqlite';

// Sabuss base URLs (as in your examples)
define('SABUSS_BASE', 'https://sabuss.com/vtu/api/');

// ---------- UTILITIES ----------
function sendTelegramMessage($chat_id, $text, $parse_mode = null) {
    global $TELEGRAM_BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$TELEGRAM_BOT_TOKEN}/sendMessage";
    $post = ['chat_id' => $chat_id, 'text' => $text];
    if ($parse_mode) $post['parse_mode'] = $parse_mode;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    $res = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("Telegram send error: " . curl_error($ch));
    }
    curl_close($ch);
    return $res;
}

function sabussRequest($endpoint, $postfields = []) {
    global $SABUSS_API_KEY;
    $url = rtrim(SABUSS_BASE, '/') . '/' . trim($endpoint, '/');
    $url = str_replace('{API_KEY}', $SABUSS_API_KEY, $url);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    $raw = curl_exec($ch);
    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => $err];
    }
    curl_close($ch);
    $decoded = json_decode($raw, true);
    if ($decoded === null) {
        return ['success' => false, 'error' => 'invalid_json', 'raw' => $raw];
    }
    return ['success' => true, 'data' => $decoded];
}

// ---------- PLAN LIST (map of plan_id => description) ----------
// Included exactly as provided by you (abbreviated comments removed)
$PLANS = [
    12 => 'GLO Airtime',
    1651 => '9mobile Airtime',
    10 => 'MTN Airtime',
    11 => 'AIRTEL Airtime',
    2308 => 'MTN SME2 2.5GB - 1 Day',
    2275 => 'MTN SME2 3GB - 30 Days',
    2263 => 'MTN SME 2GB - 30 Days',
    2264 => 'MTN SME 10GB - 30 Days',
    3 => 'MTN SME 1GB - 7 Days',
    2287 => 'MTN SME2 5GB - 30 Days',
    2274 => 'MTN SME2 2GB - 30 Days',
    2273 => 'MTN SME2 1GB - 7 Days',
    2303 => 'MTN SME2 500MB - 7 Days',
    2267 => 'MTN SME 500MB - 7 Days',
    2307 => 'MTN SME2 1GB - 1 Day',
    2302 => 'MTN SME2 1GB - 30 Days',
    8 => 'MTN SME 7GB - 30 Days',
    6 => 'MTN SME 3.2GB - 2 Days',
    5 => 'MTN SME 2GB - 2 Days',
    2258 => 'MTN Smart 2.5GB - 2 Days',
    2251 => 'MTN Smart 3.5GB - 30 Days',
    1654 => 'MTN Smart 1.5GB - 2 Days',
    2226 => 'MTN Smart 11GB - 7 Days',
    1652 => 'MTN Smart 1GB - 1 Day',
    2294 => 'MTN Smart 1GB - 7 Days',
    1854 => 'MTN Smart 1.5GB - 7 Days',
    2298 => 'MTN Smart 500MB - 7 Days',
    2242 => 'MTN Data 14.5GB [XtraSpecial] - 30 Days',
    2236 => 'MTN Data 36GB - 30 Days',
    2244 => 'MTN Data 110MB - 1 Day',
    2245 => 'MTN Data 230MB - 1 Day',
    2265 => 'MTN Data 6GB - 7 Days',
    2266 => 'MTN Data 1.8GB + 35mins calltime - 30 Days',
    2241 => 'MTN Data 6.75GB [XtraSpecial] - 30 Days',
    2237 => 'MTN Data 75GB - 30 Days',
    2243 => 'MTN Data 75MB - 1 Day',
    2268 => 'MTN Data 750MB (Pulse Tariff) - 3 Days',
    2238 => 'MTN Data 90GB - 60 Days',
    2306 => 'MTN Data 65GB - 30 Days',
    2305 => 'MTN Data 11GB - 7 Days',
    2297 => 'MTN Data 500MB - 7 Days',
    2295 => 'MTN Data 1GB - 7 Days',
    1668 => 'MTN Data 12.5GB - 30 Days',
    1669 => 'MTN Data 16.5GB - 30 Days',
    1695 => 'MTN Data 2.7GB - 30 Days',
    2280 => 'Airtel CG 3GB - 30 Days',
    2299 => 'Airtel CG 500MB - 2 Days',
    2262 => 'Airtel CG 10GB - 30 Days',
    2284 => 'Airtel CG 1GB - Weekly',
    733 => 'Airtel CG 2GB - 30 Days',
    2256 => 'Airtel Smart 300MB - 2 Days',
    2289 => 'Airtel Smart 1.5GB - 1 Day',
    2304 => 'Airtel Smart 5GB - 7 Days',
    2301 => 'Airtel Smart 3GB - 2 Days',
    2292 => 'Airtel Data 500MB - 7 Days',
    1720 => 'Airtel Data 6GB - 30 Days',
    1735 => 'Airtel Data 3GB Binge - 2 Days',
    1736 => 'Airtel Data 8.5GB - 7 Days',
    1721 => 'Airtel Data 10GB - 30 Days',
    1722 => 'Airtel Data 13GB - 30 Days',
    1723 => 'Airtel Data 5GB - 2 Days',
    1724 => 'Airtel Data 35GB - 30 Days',
    1725 => 'Airtel Data 60GB - 30 Days',
    1730 => 'Airtel Data 4GB - 30 Days',
    1731 => 'Airtel Data 1GB - 7 Days',
    1737 => 'Airtel Data 18GB - 7 Days',
    1719 => 'Airtel Data 3GB - 30 Days',
    1744 => 'Airtel Data 2GB Binge - 2 Days',
    1746 => 'Airtel Data 6GB - 7 Days',
    1740 => 'Airtel Data 18GB - 30 Days',
    1662 => 'Airtel Smart 2GB - 2 Days',
    1714 => 'Airtel Data 200MB - 1 Day',
    1715 => 'Airtel Data 100MB - 1 Day',
    1717 => 'Airtel Data 1.5GB - 7 Days',
    1734 => 'Airtel Data 2GB - 30 Days',
    2224 => 'GLO CG 5GB - 30 Days',
    963 => 'GLO CG 500MB - 14 Days',
    2225 => 'GLO CG 10GB - 30 Days',
    964 => 'GLO CG 1GB - 30 Days',
    966 => 'GLO CG 3GB - 30 Days',
    962 => 'GLO CG 200MB - 14 Days',
    965 => 'GLO CG 2GB - 30 Days',
    2193 => 'Glo Data 1.1GB + 1.5GB Night - 30 Days',
    2194 => 'Glo Data 2GB + 3GB Night - 30 Days',
    2195 => 'Glo Data 3.25GB + 3GB Night - 30 Days',
    2196 => 'Glo Data 4GB + 2GB Night - 7 Days',
    2197 => 'Glo Data 4.5GB + 3GB Night - 30 Days',
    1647 => 'Glo Awoof 750MB - 1 Day',
    2227 => 'Glo Awoof 2.5GB - 2 Days',
    2228 => 'Glo Awoof 10GB - 7 Days',
    2232 => 'Glo Data 8GB + 3GB Night - 30 Days',
    2233 => 'Glo Data 11GB + 3GB Night - 30 Days',
    2234 => 'Glo Data 14GB + 4GB Night - 30 Days',
    2235 => 'Glo Data 25GB + 4GB Night - 30 Days',
    1648 => 'Glo Awoof 1.5GB - 1 Day',
    2192 => 'Glo Data 500MB + 1GB Night - 14 Days',
    1640 => '9MOBILE 1400MB - 30 Days',
    1708 => '9MOBILE 39GB - 2 Months',
    1707 => '9MOBILE 3.17GB - 30 Days',
    1641 => '9MOBILE 450MB - 1 Day',
    1639 => '9MOBILE 3.91GB - 30 Days',
    1638 => '9MOBILE 6.5GB - 30 Days',
    1637 => '9MOBILE 2.44GB - 30 Days',
    1709 => '9MOBILE 26.5GB - 30 Days',
    1636 => '9MOBILE 650MB - 1 Day',
    1635 => '9MOBILE 100MB - 1 Day',
    1876 => '160GB Jumbo for 90days',
    1875 => '90GB Jumbo for 60days',
    1869 => '30GB Bigga for 30days',
    1882 => '35GB 365 for 365days',
    1870 => '40GB Bigga for 30days',
    1873 => '100GB Bigga for 30days',
    1872 => '75GB Bigga for 30days',
    1871 => '60GB Bigga for 30days',
    1868 => '6.5GB Bigga for 30days',
    1867 => '5GB Bigga for 30days',
    1874 => '200GB 365 for 365days',
    1895 => 'SmileVoice ONLY 65 for 30days',
    1866 => '6GB FlexiWeekly  for 7days',
    1865 => '3GB Bigga for 30days',
    1864 => '2GB Bigga for 30days',
    1863 => '2GB FlexiWeekly  for 7days',
    1877 => '200GB Jumbo for 120days',
    1878 => '125GB 365 for 365days',
    1879 => '500GB 365 for 365days',
    1890 => 'UnlimitedEssential for 30days',
    1891 => 'Freedom 3Mbps for 30days',
    1892 => 'Freedom 6Mbps for 30days',
    1893 => 'Freedom BestEffort for 30days',
    1861 => '1GB FlexiWeekly for 7days',
    1894 => '400GB Jumbo for 180days',
    1896 => 'SmileVoice ONLY 135 for 30days',
    1897 => 'SmileVoice ONLY 150 for 60days',
    1898 => 'SmileVoice ONLY 175 for 90days',
    1900 => 'SmileVoice ONLY 450 for 60days',
    1901 => 'SmileVoice ONLY 500 for 90days',
    1889 => 'UnlimitedLite for 30days',
    1888 => '25GB Bigga for 30days',
    1880 => '1TB 365 for 365days',
    1881 => '15GB 365 for 365days',
    1862 => '1.5GB Bigga for 30days',
    1883 => '130GB Bigga for 30days',
    1884 => '70GB 365 for 365days',
    1885 => '10GB Bigga for 30days',
    1886 => '15GB Bigga for 30days',
    1899 => 'SmileVoice ONLY 430 for 30days',
    1887 => '20GB Bigga for 30days',
    1859 => '1GB FlexiDaily for 1days',
    1860 => '2.5GB FlexiDaily for 2days',
    1902 => 'Freedom Mobile Plan for 30days',
];

// ---------- DB (SQLite) ----------
function initDb($db_file) {
    $needCreate = !file_exists($db_file);
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($needCreate) {
        $pdo->exec("CREATE TABLE transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id TEXT,
            user TEXT,
            plan_id TEXT,
            phone TEXT,
            amount REAL,
            reference TEXT,
            sabuss_response TEXT,
            status TEXT,
            created_at TEXT
        )");
    }
    return $pdo;
}
$pdo = initDb($DB_FILE);

// ---------- Handlers ----------
function handleBalance($chat_id) {
    global $SABUSS_PIN;
    $res = sabussRequest('balance/{API_KEY}', ['pin' => $SABUSS_PIN]);
    if (!$res['success']) {
        sendTelegramMessage($chat_id, "Balance request failed: {$res['error']}");
        return;
    }
    sendTelegramMessage($chat_id, "Balance response:\n" . json_encode($res['data']));
}

function handlePlans($chat_id) {
    global $PLANS;
    $chunks = array_chunk($PLANS, 30, true); // split for long lists
    $i = 1;
    foreach ($chunks as $chunk) {
        $text = "Available plans (part {$i}):\n";
        foreach ($chunk as $id => $desc) {
            $text .= "{$id} => {$desc}\n";
        }
        sendTelegramMessage($chat_id, $text);
        $i++;
    }
}

function handleBuy($chat_id, $fromUser, $args) {
    global $SABUSS_PIN, $pdo, $PLANS;
    // expected args: plan_id phone amount [reference]
    $parts = preg_split('/\s+/', trim($args));
    if (count($parts) < 3) {
        sendTelegramMessage($chat_id, "Usage: /buy <plan_id> <phone> <amount> [reference]\nExample: /buy 2308 08011223344 100 myref123");
        return;
    }
    list($plan_id, $phone, $amount) = $parts;
    $reference = $parts[3] ?? uniqid('r_');

    // sanity checks
    if (!is_numeric($plan_id)) {
        sendTelegramMessage($chat_id, "Plan ID must be numeric.");
        return;
    }
    if (!preg_match('/^\d{10,14}$/', $phone)) {
        sendTelegramMessage($chat_id, "Phone number looks invalid. Use digits only, e.g. 08011223344.");
        return;
    }

    // prepare post for Sabuss (based on your example)
    $post = [
        'pin' => $SABUSS_PIN,
        'plan_id' => $plan_id,
        'phone' => $phone,
        'amount' => $amount,
        'reference' => $reference
    ];

    $res = sabussRequest('buy/{API_KEY}', $post);
    $status = 'unknown';
    $resp_text = '';
    if (!$res['success']) {
        $resp_text = "Request error: {$res['error']}";
        $status = 'error';
    } else {
        $resp_text = json_encode($res['data']);
        // If Sabuss returns a code field, try to map success/pending/fail
        if (isset($res['data']['code'])) {
            $code = $res['data']['code'];
            if ($code == 200) $status = 'success';
            elseif ($code == 400) $status = 'pending';
            elseif ($code == 800) $status = 'failed';
            elseif ($code == 900) $status = 'reversed';
            else $status = 'unknown';
        } else {
            $status = 'unknown';
        }
    }

    // log to DB
    $stmt = $pdo->prepare("INSERT INTO transactions (chat_id,user,plan_id,phone,amount,reference,sabuss_response,status,created_at) VALUES (?,?,?,?,?,?,?,?,datetime('now'))");
    $stmt->execute([$chat_id, $fromUser, $plan_id, $phone, $amount, $reference, $resp_text, $status]);

    // reply
    sendTelegramMessage($chat_id, "Buy request sent.\nReference: {$reference}\nStatus: {$status}\nResponse: {$resp_text}");
}

function handleQuery($chat_id, $args) {
    global $SABUSS_PIN;
    $ref = trim($args);
    if ($ref === '') {
        sendTelegramMessage($chat_id, "Usage: /query <reference>\nExample: /query 1234567876543");
        return;
    }
    $post = [
        'pin' => $SABUSS_PIN,
        'reference' => $ref
    ];
    $res = sabussRequest('query/{API_KEY}', $post);
    if (!$res['success']) {
        sendTelegramMessage($chat_id, "Query failed: {$res['error']}");
        return;
    }
    sendTelegramMessage($chat_id, "Query result:\n" . json_encode($res['data']));
}

// ---------- Webhook / router ----------
$raw = file_get_contents('php://input');
// Basic check: require webhook secret in query string to avoid random posts
$qsSecret = $_GET['token'] ?? '';
if ($qsSecret !== $WEBHOOK_SECRET) {
    // Not allowed — quietly return 403 for non-secret requests
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$update = json_decode($raw, true);
if (!$update) {
    // nothing to do
    http_response_code(200);
    echo "ok";
    exit;
}

// handle message types
$chat_id = $update['message']['chat']['id'] ?? null;
$text = $update['message']['text'] ?? '';
$fromUser = $update['message']['from']['username'] ?? ($update['message']['from']['first_name'] ?? 'unknown');

if (!$chat_id || !$text) {
    echo "ok";
    exit;
}

// parse commands
if (strpos($text, '/help') === 0) {
    $help = "VTU Bot Commands:
/help - show this
/balance - check Sabuss balance
/plans - list available plans
/buy <plan_id> <phone> <amount> [reference] - buy a plan
/query <reference> - check status of a transaction

Example:
/buy 2308 08011223344 100 myref123";
    sendTelegramMessage($chat_id, $help);
    exit;
}

if (strpos($text, '/balance') === 0) {
    handleBalance($chat_id);
    exit;
}

if (strpos($text, '/plans') === 0) {
    handlePlans($chat_id);
    exit;
}

if (strpos($text, '/buy') === 0) {
    $args = trim(substr($text, strlen('/buy')));
    handleBuy($chat_id, $fromUser, $args);
    exit;
}

if (strpos($text, '/query') === 0) {
    $args = trim(substr($text, strlen('/query')));
    handleQuery($chat_id, $args);
    exit;
}

// default fallback: echo
sendTelegramMessage($chat_id, "Unknown command. Send /help for available commands.");
echo "ok";
