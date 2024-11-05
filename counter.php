<?php

$servername = "localhost";
$dbusername = "heyfqtse_balebotuser";
$dbpassword = ".A8g.s^gQ6s~";
$dbname = "heyfqtse_balebot";
$botName=$_GET['botname'];
$instructions = getAssistantConversationHistory();

if ($instructions) {
    $unique_chatids = $instructions['unique_chatids'];
    $chatids = $instructions['chatids'];
    $total_tokencount = $instructions['total_tokencount'];

    echo 'تعداد استفاده کننده: ' . $unique_chatids . ' | تعداد سوال پرسیده شده: ' . $chatids . ' | تعداد توکن مصرف شده: ' . $total_tokencount;
} elseif ($instructions === null) {
    // اگر بات یافت نشد
    error_log('بات مورد نظر در دیتابیس پیدا نشد');
} 

function getPDOConnection() {
    global $servername, $dbname, $dbusername, $dbpassword;
    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $dbusername, $dbpassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        return null;
    }
}

function getAssistantConversationHistory() {
    $pdo = getPDOConnection();
    global $botName;
    if (!$pdo) {
        return null;
    }
    $sql = "SELECT 
                COUNT(DISTINCT chat_id) AS unique_chatids,
                COUNT(chat_id) AS chatids,
                SUM(tokencount) AS total_tokencount
            FROM 
                conversations
            WHERE 
                botname = :botname";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':botname' => $botName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage());
        return null;
    }
}


?>

