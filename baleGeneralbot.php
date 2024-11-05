<?php

$update = file_get_contents("php://input");
$update_array = json_decode($update, true); // Decode the JSON update

$baseurl = "https://tapi.bale.ai/bot"; 
$tokentype='baletoken';


$language_code = 1;
$user_name='';
$botName=$_GET['botName'];
$instructionsTXT='';
$instructions = SelectInstructions($botName);

if ($instructions) {

$firstinstructions=$instructions['firstinstructions'];
$shortinstructions=$instructions['shortinstructions'];
//error_log('$shortinstructions='.$shortinstructions);
$botfreecredits=$instructions['botfreecredits'];
$botfreetokens=$instructions['botfreetokens'];
$token = $instructions[$tokentype];
$botcredit = $instructions['botcredit'];

} elseif ($instructions === null) {
    //اگر بات یافت نشد
    error_log('بات مورد نظر در دیتابیس پیدا نشد');

} 


// Check the type of incoming message and handle appropriately
if (isset($update_array["message"])) {
    handle_message($update_array["message"]);
} elseif (isset($update_array["callback_query"])) {
    handle_callback_query($update_array["callback_query"]);
}

// Handle incoming messages
function handle_message($message) {
    global $baseurl, $token, $language_code,$instructionsTXT,$firstinstructions,$shortinstructions,$botcredit;
    //error_log('first='.$firstinstructions.'short='.$shortinstructions);

    $chat_id = $message["chat"]["id"];
    $text = $message["text"];
    $user_id = $message["from"]["id"];
    $user_name = $message["from"]["username"] ?? '';
    $is_bot = $message["from"]["is_bot"];
    $first_name = $message["from"]["first_name"] ?? '';

    if($botcredit <= 0) {
        // اگر در دریافت اطلاعات مشکلی پیش آمده باشد
        error_log('اعتبار مقدار توکن بات پایان یافته است');
        $chat_id ='1276187582';
        global $botName;
        send_reply_message($chat_id, "اعتبار مقدار توکن بات".$botName." پایان یافته است");
    
    }else{
        if ($text === '/start') {
            //error_log("test start");
            $instructionsTXT = $firstinstructions;
                //clearConversationHistory($chat_id);  // حذف تاریخچه مکالمات
                checkUsercredit($chat_id, $text);
        

    
            } else {
                $instructionsTXT = $shortinstructions;
                // ذخیره سوال کاربر در دیتا بیس
                saveMessageToDB($chat_id, 'User', $text);

                $findAssisstantContent = findAssisstantContent($text);

                if ($findAssisstantContent) {
                
                $AssisstantContent=$findAssisstantContent['response_content'];
                
                } elseif ($findAssisstantContent === null) {
                    //اگر بات یافت نشد
                    error_log('محتوای کمکی یافت نشد');
                    $AssisstantContent='';
                
                } 

                send_reply_message($chat_id, "دارم فکر می کنم...");
                checkUsercredit($chat_id, $text,$AssisstantContent);
            } 

    }
    
       
        
    
}

function handle_chatgpt_response($chat_id, $text) {
    $pdo = getPDOConnection(); 
    if ($pdo === null) {
        error_log('Internal Server Error. Please try again later.');
        send_reply_message($chat_id, "ارتباط با بانک اطلاعاتی دچار مشکل شده است");
        return;
    }    
    $response = askChatGPTWithDB($chat_id,$text,$pdo);
    $processed_response = processLatex($response);
  
    sendMathImages($chat_id, $processed_response); 
    

}

function send_reply_message($chat_id, $reply) {
    global $baseurl, $token;
   
    $send = [
        'chat_id' => $chat_id,
        'text' => $reply
    ];

    $url = $baseurl . $token . "/sendMessage";

    send_reply($url, $send);
}


// Function to send HTTP POST request
function send_reply($url, $send) {
    $options = [
        'http' => [
            'header' => "Content-type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($send),
        ],
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    if ($response === FALSE) {
       // error_log("Error sending reply: $url");
    }
}


function saveMessageToDB($chat_id, $role, $content) {
    $pdo = getPDOConnection();
    global $botfreecredits,$botName;
    // مرحله 1: شمارش تعداد پیام‌های ذخیره‌شده برای این کاربر
    // $count_sql = "SELECT COUNT(*) FROM conversations WHERE chat_id = :chat_id";
    // $count_stmt = $pdo->prepare($count_sql);
    // $count_stmt->execute([':chat_id' => $chat_id]);
    // $message_count = $count_stmt->fetchColumn();  // تعداد پیام‌های موجود

    // مرحله 2: اگر تعداد پیام‌ها بیشتر از 5 بود، قدیمی‌ترین پیام‌ها را حذف کنید
    // if ($message_count >= $botfreecredits) {
    //     // حذف قدیمی‌ترین پیام‌ها برای اینکه تعداد پیام‌ها به 10 برسد
    //     $delete_sql = "DELETE FROM conversations WHERE chat_id = :chat_id ORDER BY timestamp ASC LIMIT 1";
    //     $delete_stmt = $pdo->prepare($delete_sql);
    //     $delete_stmt->execute([':chat_id' => $chat_id]);
    // }
    // مرحله 3: ذخیره پیام جدید
    $content = preg_replace('/[#*]/', '', $content);

    // حذف فاصله‌های بیش از یک space
    $content = preg_replace('/\s+/', ' ', $content);
    
    // حذف فاصله‌های اضافی ابتدا و انتهای رشته
    $content = trim($content);

    $tokencount=countTokensApprox($content);
    deductBotCredit($tokencount);

    $insert_sql = "INSERT INTO conversations (chat_id, role, content,tokencount,botname) VALUES (:chat_id, :role, :content,:tokencount,:botname)";
    $insert_stmt = $pdo->prepare($insert_sql);
    $insert_stmt->execute([
        ':chat_id' => $chat_id,
        ':role' => $role,
        ':content' => $content,
        ':tokencount' => $tokencount,
        ':botname' => $botName
    ]);
}


function getAssistantConversationHistory($chat_id) {
    $pdo = getPDOConnection();
    global  $botName;
    $sql = "SELECT role, content FROM conversations WHERE chat_id = :chat_id and role = 'assistant' and botname = :botname ORDER BY timestamp desc LIMIT 2";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':chat_id' => $chat_id,':botname' => $botName]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserConversationHistory($chat_id) {
    global  $botName;
    $pdo = getPDOConnection();
    $sql = "SELECT role, content FROM conversations WHERE chat_id = :chat_id and role = 'user' and botname = :botname ORDER BY timestamp desc LIMIT 4";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':chat_id' => $chat_id,':botname' => $botName]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function askChatGPTWithDB($chat_id, $newQuestion, $pdo) {
    global $ChatGPTapiKey,$instructionsTXT,$AssisstantContent;
    $apiKey = $ChatGPTapiKey;
        // بازیابی تاریخچه مکالمه از دیتابیس
        $UserconversationHistory = getUserConversationHistory($chat_id);
        $AssistantConversationHistory = getAssistantConversationHistory($chat_id);

        // ساخت آرایه پیام‌ها برای ارسال به OpenAI
    $messages = array_merge([[
        'role' => 'system',
        'content' => $instructionsTXT
    ]], $AssistantConversationHistory,[[
        'role' => 'assisstant',
        'content' => $AssisstantContent
    ]], [[
        'role' => 'user',
        'content' => $newQuestion
    ]]);
    error_log(print_r($messages, true));    // ارسال درخواست به پیامOpenAI
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
    saveMessageToDB($chat_id, 'assistant', $responseData['choices'][0]['message']['content']);

    // بازگشت پاسخ دستیار
    return $responseData['choices'][0]['message']['content'] ?? 'پاسخی از API دریافت نشد';
}

// تابع برای شناسایی کاراکترهای ریاضی و ارسال تصویر
function sendMathImages($chat_id, $response) {
        // اگر فرمولی یافت نشد
        $response =$response."\n\nPowered by @AIGSolutions";
        send_reply_message($chat_id, $response);
    
}

// function sendFormulaAsImage($chat_id, $formula) {
//     global $baseurl , $token;
//     // تبدیل فرمول به تصویر با استفاده از CodeCogs
//     $image_url = 'https://latex.codecogs.com/png.latex?' . urlencode($formula);

//     // ارسال تصویر به تلگرام
//     $bot_token = "YOUR_TELEGRAM_BOT_TOKEN";  // توکن بات تلگرام شما
//     $url = $baseurl . $token ."/sendPhoto";

//     // داده‌های ارسالی به API تلگرام
//     $post_data = [
//         'chat_id' => $chat_id,
//         'photo' => $image_url,
//         'caption' => 'این فرمول به صورت تصویر ارسال شده است: ' . $formula
//     ];

//     // ارسال درخواست به API تلگرام
//     $options = [
//         'http' => [
//             'header' => "Content-type: application/x-www-form-urlencoded\r\n",
//             'method' => 'POST',
//             'content' => http_build_query($post_data),
//         ],
//     ];

//     $context = stream_context_create($options);
//     file_get_contents($url, false, $context);
// }

// function clearConversationHistory($chat_id) {
//     $pdo = getPDOConnection();
//     if ($pdo === null) {
//         error_log("Database Connection Error: Unable to connect to the database.");
//         return;
//     }

//     // حذف تمام مکالمات کاربر از جدول conversations
//     $delete_sql = "DELETE FROM conversations WHERE chat_id = :chat_id";
//     $delete_stmt = $pdo->prepare($delete_sql);
//     $delete_stmt->execute([':chat_id' => $chat_id]);
// }



function countTokensApprox($text) {
    // شمارش تعداد کلمات
   // $wordCount = str_word_count($);
    $wordCount =count(preg_split('~[^\p{L}\p{N}\']+~u',$text));
    // تخمین تعداد توکن‌ها (تقریباً هر 0.75 کلمه = 1 توکن)
    $tokenCount = ceil($wordCount / 0.75);
   // error_log('countTokensApprox'.$wordCount);
    return $tokenCount;
}


function checkUsercredit($chat_id, $text,$ac) {
    // error_log('checkUsercredit');
    //global $instructions1;
    global $botName;
    $pdo = getPDOConnection();
    // مرحله 1: بررسی وجود کاربر در جدول users
    $check_sql = "SELECT tokencount, credit FROM users WHERE id = :chat_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':chat_id' => $chat_id]);
    $user = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // کاربر وجود دارد

        // مرحله 2: محاسبه تعداد توکن‌ها با استفاده از countTokensApprox
        $tokencounttext=$text.$ac;
        $tokenCount = countTokensApprox($tokencounttext);
        // مرحله 3: به‌روزرسانی tokencount و کاهش credit
        $newTokenCount = $user['tokencount'] + $tokenCount;
        $newCredit = $user['credit'] ;//$user['credit']- 1

        // اطمینان از اینکه credit کمتر از 0 نشود
        if ($newCredit < 0 || $newTokenCount > 10000) {
            $newCredit = 0;
            $newTokenCount = 10000;
            send_reply_message($chat_id, "اعتبار شما پایان یافت. لطفا اعتبار تهیه نمایید.");
        }else{
            if($text === '/start'){
                handle_chatgpt_response($chat_id, ' بگو خوشحالم که برگشتی و منتظر بمون تا سوال بپرسه ');
                }else{
                handle_chatgpt_response($chat_id, $text);
                }
        }

        $update_sql = "UPDATE users SET tokencount = :tokencount, credit = :credit WHERE id = :chat_id";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            ':tokencount' => $newTokenCount,
            ':credit' => $newCredit,
            ':chat_id' => $chat_id
        ]);

        //error_log("Updated user $chat_id: tokencount=$newTokenCount, credit=$newCredit");

    } else {
        // کاربر وجود ندارد، ایجاد کاربر جدید با credit=5 و tokencount محاسبه شده

        // مرحله 2: محاسبه تعداد توکن‌ها با استفاده از countTokensApprox
        $tokenCount = countTokensApprox($text);
        global $user_name;
        $insert_sql = "INSERT INTO users (id,username, tokencount, credit) VALUES (:chat_id,:username, :tokencount, :credit)";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([
            ':chat_id' => $chat_id,
            ':tokencount' => $tokenCount,
            ':username' =>$user_name,
            ':credit' => 5
        ]);
        handle_chatgpt_response($chat_id, 'خودت را معرفی کن');
        //error_log("Inserted new user $user_name with tokencount=$tokenCount and credit=5");
    }
}


// PDO Connection
function getPDOConnection() {
    global $servername;
    global $dbname;
    global $dbusername;
    global $dbpassword;
    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $dbusername, $dbpassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        return null;
    }
}
// تابع برای پردازش فرمول‌های LaTeX و تبدیل آن‌ها به متن ساده
function processLatex($response) {
    // الگوی Regex برای شناسایی فرمول‌های بلاک \[ ... \]
    $blockPattern = '/\\\\\[(.*?)\\\\\]/s';
    // الگوی Regex برای شناسایی فرمول‌های خطی \(...\)
    $inlinePattern = '/\\\\\((.*?)\\\\\)/s';
    // الگوی Regex برای شناسایی فرمول‌های خطی $ ... $
    $dollarPattern = '/\$(.*?)\$/s';

    // تابع جایگزینی برای فرمول‌های بلاک
    $processed_response = preg_replace_callback($blockPattern, function($matches) {
        //error_log("processLatex1 called");
        $latex_content = $matches[1];
        $converted = convertLatexToText($latex_content);
        //error_log("Converted block LaTeX: " . $converted);
        return $converted;
    }, $response);

    // تابع جایگزینی برای فرمول‌های خطی \(...\)
    $processed_response = preg_replace_callback($inlinePattern, function($matches) {
        //error_log("processLatex2 called");
        $latex_content = $matches[1];
        $converted = convertLatexToText($latex_content);
        //error_log("Converted inline LaTeX: " . $converted);
        return $converted;
    }, $processed_response);

    // تابع جایگزینی برای فرمول‌های خطی $ ... $
    $processed_response = preg_replace_callback($dollarPattern, function($matches) {
        //error_log("processLatex3 called");
        $latex_content = $matches[1];
        $converted = convertLatexToText($latex_content);
        //error_log("Converted dollar LaTeX: " . $converted);
        return $converted;
    }, $processed_response);

    return $processed_response;
}

// تابع تبدیل LaTeX به متن قابل نمایش
function convertLatexToText($latex) {
    // تبدیل برخی از نمادهای LaTeX به کاراکترهای متنی
    $replacements = [
        '\\times' => '×',
        '\\div' => '÷',
        '\\pi' => 'π',
        '\\ln' => 'ln',
        '\\log' => 'log',
        '\\sqrt' => '√',
        '\\approx' => '≈',
        '\\leq' => '≤',
        '\\geq' => '≥',
        '\\neq' => '≠',
        '\\pm' => '±',
        '\\rightarrow' => '→',
        '\\leftarrow' => '←',
        '\\forall' => '∀',
        '\\exists' => '∃',
        '^2' => '²',
        '^3' => '³',
        '_2' => '₂',
        '_3' => '₃',
        '\\circ' => '°',
        '\\infty' => '∞',
        '\\sum' => '∑',
        '\\prod' => '∏',
        '\\int' => '∫',
        '\\cdot' => '⋅',
        '\\alpha' => 'α',
        '\\beta' => 'β',
        '\\gamma' => 'γ',
        '\\delta' => 'δ',
        '\\epsilon' => 'ε',
        // افزودن موارد بیشتر در صورت نیاز
    ];

    // جایگزینی نمادهای LaTeX با کاراکترهای متنی
    $text_content = strtr($latex, $replacements);

    // حذف بک‌اسلش‌های اضافی
    $text_content = str_replace('\\', '', $text_content);

    // تبدیل فرمت‌های توان و اندیس
    // مثال: s^2 به s²
    $text_content = preg_replace('/(\w)\^(\d+)/', '$1$2', $text_content);
    $text_content = preg_replace('/(\w)_(\d+)/', '$1$2', $text_content);

    return $text_content;
}

function SelectInstructions($botName) {
    try {
        // اتصال به پایگاه داده
        $pdo = getPDOConnection(); 

        // آماده‌سازی کوئری SQL با استفاده از پارامترها برای جلوگیری از SQL Injection
        $sql = "SELECT botcredit,baletoken,telegramtoken,firstinstructions, shortinstructions, botfreecredits, botfreetokens 
                FROM botSettings 
                WHERE botname = :bot_Name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':bot_Name' => $botName]);

        // بررسی تعداد ردیف‌های بازگشتی
        if ($stmt->rowCount() > 0) {
            // دریافت داده‌ها به صورت آرایه انجمنی
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result;
        } else {
            // در صورتی که باتی با نام مشخص شده یافت نشد
            return null;
        }
    } catch (PDOException $e) {
        // مدیریت خطاها
        echo "خطا در اتصال به پایگاه داده: " . $e->getMessage();
        return false;
    }
}

function deductBotCredit( $tokensToDeduct) {
        global $botName;
    // ایجاد اتصال به پایگاه داده
        $pdo = getPDOConnection();

        // ابتدا بررسی کنیم که بات وجود دارد و اعتبار کافی دارد
        $select_sql = "SELECT botcredit FROM botSettings WHERE botName = :botName";
        $select_stmt = $pdo->prepare($select_sql);
        $select_stmt->execute([':botName' => $botName]);
        $bot = $select_stmt->fetch(PDO::FETCH_ASSOC);
        //error_log('deductBotCredit1'.$tokensToDeduct);

        if ($bot) {
            $currentCredit = (int)$bot['botcredit'];

            // بررسی کنیم که اعتبار کافی برای کسر وجود دارد
            if ($currentCredit >= $tokensToDeduct) {
                // کسر توکن‌ها از اعتبار فعلی
                $newCredit = $currentCredit - $tokensToDeduct;

                // به‌روزرسانی اعتبار در پایگاه داده
                $update_sql = "UPDATE botSettings SET botcredit = :newCredit WHERE botName = :botName";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([':newCredit' => $newCredit, ':botName' => $botName]);

                return "اعتبار با موفقیت به‌روزرسانی شد. اعتبار جدید: " . $newCredit;
            } else {
                return "اعتبار کافی برای کسر وجود ندارد.";
            }
        } else {
            return "بات با این نام یافت نشد.";
        }

}

function findAssisstantContent($text){
        global $botName;
    // ایجاد اتصال به پایگاه داده
        $pdo = getPDOConnection();
        $question_pattern=$text;
        $select_sql = "SELECT response_content FROM AssisstantContent WHERE botName = :botName and question_pattern like :question_pattern";
        $select_stmt = $pdo->prepare($select_sql);
        $select_stmt->execute([':botName' => $botName],[':question_pattern' => $question_pattern]);
        $Assisst = $select_stmt->fetch(PDO::FETCH_ASSOC);
        //error_log('deductBotCredit1'.$tokensToDeduct);

        if ($Assisst) {
            $AssisstantContent = $Assisst['response_content'];

        } else {
            return "No Assisstant Content Found";
        }

}
?>

