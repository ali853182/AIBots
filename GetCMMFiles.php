<?php
//https://api.telegram.org/bot7636322078:AAHU6ofILSwyMguAysdm0dG_gNTy7bzw9iw/setwebhook?url=https://mojalhosein.com/telegram/telegramGeneralbot.php?botName=aicm
$baseurl = "https://api.telegram.org/bot";
$servername = "localhost";
$dbusername = "heyfqtse_balebotuser";
$dbpassword = ".A8g.s^gQ6s~";
$dbname = "heyfqtse_balebot";
$ChatGPTapiKey = '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';  // Store this securely in environment variables or a config file $question
$language_code = 1;
$update = file_get_contents("php://input");
$updateArray = json_decode($update, TRUE); // Decode the JSON update

$user_name='';
$instructionsTXT='';


$allowed_channel_id = -1002432833759;  
$chat_id=1276187582;     // شناسه کانال (ID) شما
// یا
$allowed_channel_username = "cmmFileschannel"; // نام کاربری کانال بدون @

// توکن بات تلگرام
$bot_token = "7346020393:AAGyhZBETKmqsAXPdis-ytJzciPW8kpurJA"; // جایگزین با توکن بات شما

// کلید API OpenAI
$openai_api_key = "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"; // جایگزین با کلید API OpenAI شما

// بارگذاری Composer autoload
//require '../vendor/autoload.php'; // اطمینان حاصل کنید که مسیر صحیح است
require_once  '../PhpWord/autoloader.php';
use Smalot\PdfParser\Parser;

// تابع اتصال به پایگاه داده با استفاده از PDO
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

// تابع دانلود فایل با استفاده از cURL
function downloadFile($url, $path) {
    $ch = curl_init($url);
    $fp = fopen($path, 'w');

    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // تنظیم زمان تایم اوت به 5 دقیقه

    curl_exec($ch);
    if(curl_errno($ch)) {
        error_log("cURL error while downloading file: " . curl_error($ch));
    }

    curl_close($ch);
    fclose($fp);
}

// تابع استخراج متن از PDF
function extractTextFromPDF($filePath) {
    $parser = new Parser();
    error_log('extractTextFromPDF'.$filePath);
    try {
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();
        return $text;
    } catch (Exception $e) {
        error_log("Error parsing PDF: " . $e->getMessage());
        return null;
    }
}

// تابع ارسال درخواست به OpenAI API
function analyzeTextWithOpenAI($text, $api_key) {
    error_log('analyzeTextWithOpenAI:'.$text);
    $ch = curl_init();

    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [
            [
                "role" => "system",
                "content" => "شما یک دستیار تخصصی در زمینه پایش وضعیت و نگهداری و تعمیرات تجهیزات هستی"
            ],
            [
                "role" => "user",
                "content" => $text
            ]
        ],
        "max_tokens" => 1000, // تنظیم حداکثر تعداد توکن‌ها
        "temperature" => 0.5
    ];

    curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    if(curl_errno($ch)) {
        error_log("cURL error while communicating with OpenAI: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $responseData = json_decode($response, true);
    if(isset($responseData['choices'][0]['message']['content'])) {
        return $responseData['choices'][0]['message']['content'];
    } else {
        error_log("Unexpected response from OpenAI: " . $response);
        return null;
    }
}

// دریافت داده‌های ورودی از تلگرام
$update = file_get_contents("php://input");
$updateArray = json_decode($update, TRUE);

// بررسی وجود پیام در به‌روزرسانی
if(isset($updateArray["channel_post"])) { // برای پیام‌های کانال از کلید channel_post استفاده می‌شود
    $message = $updateArray["channel_post"];
    
    // بررسی اینکه پیام از کانال است
    if(isset($message["chat"]["type"]) && $message["chat"]["type"] === "channel") {
        // دریافت شناسه و نام کاربری کانال
        $channel_id = $message["chat"]["id"];
        $channel_username = isset($message["chat"]["username"]) ? $message["chat"]["username"] : "";
        error_log('$channel_id='.$channel_id.'| $channel_username='.$channel_username);
        // اطمینان از اینکه پیام از کانال مورد نظر است
        if($channel_id === $allowed_channel_id || $channel_username === $allowed_channel_username) {
            
            // بررسی نوع فایل - فقط اسناد (documents)
            if(isset($message["document"])) {
                $document = $message["document"];
                $file_id = $document["file_id"];
                $file_name = $document["file_name"];
                $mime_type =  pathinfo($file_name, PATHINFO_EXTENSION);
                error_log('mimetype:'.$mime_type);
                // بررسی اینکه نوع MIME فایل PDF است
                if ($mime_type === 'doc' || $mime_type === 'docx') {
                    // دریافت اطلاعات فایل از تلگرام
                    $file_info = file_get_contents("https://api.telegram.org/bot$bot_token/getFile?file_id=$file_id");
                    $file_info = json_decode($file_info, TRUE);
                    if($file_info["ok"]) {
                        $file_path = $file_info["result"]["file_path"];
                        $download_url = "https://api.telegram.org/file/bot$bot_token/$file_path";

                        // دانلود فایل با استفاده از cURL
                        $local_dir = "CMMFiles/"; // مسیر فولدر دانلود روی سرور
                        if(!file_exists($local_dir)){
                            mkdir($local_dir, 0777, true);
                        }
                        $local_file = $local_dir . basename($file_path);
                        
                        //downloadFile($download_url, $local_file);

                        // استخراج متن از فایل PDF
                        $extracted_text = download_and_process_word_file($chat_id,$file_id,$file_name);
                        if($extracted_text === null) {
                            error_log("Failed to extract text from PDF: " . $local_file);
                            exit;
                        }

                        // ارسال متن به OpenAI برای تحلیل
                        $analysis_result = analyzeTextWithOpenAI($extracted_text, $openai_api_key);
                        if($analysis_result === null) {
                            error_log("Failed to get analysis from OpenAI for file: " . $file_name);
                            exit;
                        }

                        // ایجاد آدرس فایل برای دسترسی از طریق وب
                        // اطمینان حاصل کنید که آدرس فایل به درستی ساخته شده است
                        $file_url = "https://mojalhosein.com/telegram/" . basename($local_file);

                        // اتصال به پایگاه داده
                        $pdo = getPDOConnection();
                        if($pdo) {
                            try {
                                // شروع تراکنش
                                $pdo->beginTransaction();

                                // آماده‌سازی دستور SQL برای فایل
                                $stmt_file = $pdo->prepare("INSERT INTO CMMfiles (file_name, file_url, file_type, aidesc, uploaded_at) VALUES (:file_name, :file_url, :file_type, :aidesc, NOW())");
                                
                                // مقادیر را به پارامترهای دستور SQL متصل می‌کنیم
                                $file_type = "pdf"; // نوع فایل ثابت PDF
                                $stmt_file->bindParam(':file_name', $file_name);
                                $stmt_file->bindParam(':file_url', $file_url);
                                $stmt_file->bindParam(':file_type', $file_type);
                                $stmt_file->bindParam(':aidesc', $analysis_result);

                                // اجرای دستور SQL برای فایل
                                $stmt_file->execute();

                                // پایان تراکنش
                                $pdo->commit();
                            } catch (PDOException $e) {
                                $pdo->rollBack();
                                error_log("Database insert failed: " . $e->getMessage());
                            }
                        } else {
                            error_log("Failed to establish a database connection.");
                        }
                    } else {
                        error_log("Failed to get file info: " . $file_info["description"]);
                    }
                } else {
                    // اگر فایل PDF نباشد، می‌توانید لاگ یا عملی دیگر انجام دهید
                    error_log("Received a non-PDF document: " . $file_name);
                }
            }
        } else {
            // پیام از کانال دیگری است، می‌توانید آن را نادیده بگیرید یا لاگ کنید
            error_log("Received a message from an unauthorized channel: ID = $channel_id, Username = $channel_username");
        }
    }
}

function download_and_process_word_file($chat_id, $file_id, $file_name) {
    global $baseurl, $bot_token;

    // دریافت لینک فایل از تلگرام
    $file_url = $baseurl . $bot_token . "/getFile?file_id=" . $file_id;
    $file_info = json_decode(file_get_contents($file_url), true);

    if (isset($file_info['result']['file_path'])) {
        $file_path = $file_info['result']['file_path'];
        $file_download_url = "https://api.telegram.org/file/bot" . $bot_token . "/" . $file_path;


        // دانلود فایل به سرور
        $local_file_path = 'files/' . $file_name;
        file_put_contents($local_file_path, file_get_contents($file_download_url));

        // استخراج متن از فایل Word
        $text = extract_text_from_word($local_file_path);
        error_log('$text'.$text);
        if ($text) {
            $pdo = getPDOConnection();
            error_log("محتوای فایل:".$chat_id);
            // ارسال محتوای فایل به ChatGPT برای بررسی
            $response = askChatGPTWithDB($text,$pdo,$file_name);
           // send_reply_message($chat_id, $response);
        } else {
            send_reply_message($chat_id, "خطا در خواندن فایل Word.");
        }
    } else {
        send_reply_message($chat_id, "خطا در دانلود فایل.");
    }
}

// تابع برای استخراج متن از فایل Word با استفاده از phpoffice
function extract_text_from_word($file_path) {
    try {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($file_path);
        
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            $elements = $section->getElements();
            foreach ($elements as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }
        return $text;
    } catch (Exception $e) {
        error_log("Error reading Word file: " . $e->getMessage());
        return false;
    }
}


function askChatGPTWithDB($newQuestion,$file_name) {
    global $ChatGPTapiKey;
    $apiKey = $ChatGPTapiKey;
    $instructionsTXT='خلاصه فایل ارسالی را برگردان';
        // بازیابی تاریخچه مکالمه از دیتابیس
        $UserconversationHistory = getUserConversationHistory($chat_id);
        $AssistantConversationHistory = getAssistantConversationHistory($chat_id);

        // ساخت آرایه پیام‌ها برای ارسال به OpenAI
    $messages = array_merge([[
        'role' => 'system',
        'content' => $instructionsTXT
    ]], [[
        'role' => 'user',
        'content' => $newQuestion
    ]]);
    //error_log(print_r($messages, true));    // ارسال درخواست به پیامOpenAI
    $data = [
        'model' => 'gpt-4o-mini',  // یا gpt-3.5-turbo بسته به دسترسی شما
        'messages' => $messages,
        'temperature' => 0.7,  // سطح خلاقیت
        //'max_tokens' => 200,  // حداکثر تعداد توکن‌ها
    ];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . 'Bearer ' . $apiKey,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "خطا در درخواست: $error_msg";
    }

    curl_close($ch);

    $responseData = json_decode($response, true);

    if (isset($responseData['error'])) {
        return "خطای API: " . $responseData['error']['message'];
    }

    // ذخیره پاسخ دستیار در دیتابیس
    saveEmbeddToDB($responseData['choices'][0]['message']['content'],$file_name);

    // بازگشت پاسخ دستیار
    return $responseData['choices'][0]['message']['content'] ?? 'پاسخی از API دریافت نشد';
}
function saveEmbeddToDB($response_content,$file_name) {
    global $embeddingtxt;
    $FileEmbedding = txtEmbedd($response_content, $embeddurl);
    $embeddingtxt=json_encode($FileEmbedding);

    $botName='aicm';
    $pdo = getPDOConnection();

    $insert_sql = "INSERT INTO AssistantContent (botname,chat_id, response_content,embedd_content) VALUES (:botname, :chat_id, :response_content, :embedd_content) ";
    $insert_stmt = $pdo->prepare($insert_sql);
    $insert_stmt->execute([
        ':botname' => $botName,
        ':embedd_content' => $embeddingtxt,
        ':response_content' => $response_content,
        ':response_content' =>$file_name
    ]);
}
$embeddurl = 'https://api.openai.com/v1/embeddings';

function txtEmbedd($text, $url) {
    global $ChatGPTapiKey;
    $data = [
        "input" => $text,
        "model" => "text-embedding-ada-002"
    ];

    $headers = [
        "Authorization: Bearer $ChatGPTapiKey",
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    if ($response === false) {
        echo "Error: " . curl_error($ch);
        curl_close($ch);
        exit;
    }
    curl_close($ch);

    $responseData = json_decode($response, true);
    return $responseData['data'][0]['embedding'];
}
?>


