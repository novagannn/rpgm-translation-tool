<?php  
ini_set('display_errors', 1);  
error_reporting(E_ALL);  
header("Content-Type: application/json");  
  
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['progress'])) {  
    $filename = basename($_GET['progress']);  
    $progressFile = "logs/progress_{$filename}.json";  
    if (file_exists($progressFile)) {  
        echo file_get_contents($progressFile);  
    } else {  
        echo json_encode(['percent' => 0]);  
    }  
    exit;  
}  
  
$rulesFile    = "rules.json";  
$wordbankFile = "wordbank.json";  
$uploadDir    = "uploads";  
$logDir       = "logs";  
  
$fromLang = $_POST['from'] ?? 'ja';  
$toLang   = $_POST['to']   ?? 'id';  
$langKey  = "{$fromLang}-{$toLang}";  
  
$rules    = loadJsonFile($rulesFile, []);  
$wordbank = loadJsonFile($wordbankFile, []);  
if (!isset($wordbank[$langKey])) $wordbank[$langKey] = [];  
  
if (!isset($_FILES['jsonFile'])) {  
    error_log("[UPLOAD ERROR] File 'jsonFile' tidak ditemukan");  
    exitWithError("No file uploaded");  
}  
$file = $_FILES['jsonFile']['tmp_name'];  
$json = file_get_contents($file);  
if (!$json) {  
    error_log("[UPLOAD ERROR] File kosong");  
    exitWithError("Empty file content");  
}  
  
$data = json_decode($json, true);  
if (!is_array($data)) {  
    error_log("[JSON ERROR] " . json_last_error_msg());  
    exitWithError("Invalid JSON format", $json);  
}  
  
$totalCount = countTranslatableStrings($data);  
  
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);  
if (!file_exists($logDir))    mkdir($logDir, 0777, true);  
  
$originalName = pathinfo($_FILES['jsonFile']['name'], PATHINFO_FILENAME);  
$filename     = $originalName . '_' . rand(1000000, 9999999);  
$progressFile = "$logDir/progress_{$filename}.json";  
  
$log   = [];  
$count = 0;  
$logMeta = [ "translated" => [], "skipped" => [], "total" => $totalCount ];  
  
file_put_contents($progressFile, json_encode([  
    'percent' => 0,  
    'current' => 0,  
    'total'   => $totalCount,  
    'timestamp' => time()  
]));  
  
walkAndTranslate($data, $fromLang, $toLang, $rules, $wordbank, $langKey, $log, $count, $logMeta, $progressFile);  
  
file_put_contents("$uploadDir/{$filename}.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));  
  
$reviewLog = [  
    "translated" => $logMeta['translated'],  
    "skipped"    => $logMeta['skipped'],  
    "from"       => $fromLang,  
    "to"         => $toLang,  
    "timestamp"  => date('Y-m-d H:i:s')  
];  
file_put_contents("$logDir/{$filename}.json", json_encode($reviewLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));  
  
file_put_contents($wordbankFile, json_encode($wordbank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));  
if (file_exists($progressFile)) unlink($progressFile);  
  
echo json_encode([  
    "success" => true,  
    "translated_count" => $count,  
    "log" => implode("\n", $log),  
    "download_url" => "download.php?file={$filename}.json"  
]);  
  
function updateProgress($current, $total, $progressFile) {  
    $percent = $total > 0 ? round(($current / $total) * 100) : 0;  
    file_put_contents($progressFile, json_encode([  
        'percent' => $percent,  
        'current' => $current,  
        'total'   => $total,  
        'timestamp' => time()  
    ]));  
}  
  
function countTranslatableStrings($data) {  
    $count = 0;  
    $stack = [$data];  
    while ($stack) {  
        $item = array_pop($stack);  
        if (is_array($item)) {  
            foreach ($item as $v) {  
                if (is_array($v)) $stack[] = $v;  
                elseif (is_string($v) && shouldTranslate($v)) $count++;  
            }  
        }  
    }  
    return $count;  
}  
  
function loadJsonFile($file, $default = []) {  
    if (!file_exists($file)) return $default;  
    $data = json_decode(file_get_contents($file), true);  
    return is_array($data) ? $data : $default;  
}  
  
function exitWithError($message, $raw = null) {  
    $response = ["error" => $message];  
    if ($raw !== null) $response['raw'] = $raw;  
    echo json_encode($response);  
    exit;  
}  
  
function shouldTranslate($text) {  
    $text = trim($text);  
    if (  
        $text === '' ||  
        preg_match('/^\\w+\[[^\]]*\]$/u', $text) ||  
        preg_match('/^<.*?>$/u', $text) ||  
        preg_match('/^!?\$?\w+\.(png|jpe?g|ogg|wav|mp3|m4a)$/i', $text) ||  
        preg_match('/^[!$@%]/', $text) ||  
        preg_match('/^(Audio|img|movies|system|titles)\//i', $text) ||  
        preg_match('/^\xE5\x86\x8D\xE7\x94\x9F\s[a-z0-9\-]+\s.*/iu', $text)  
    ) return false;  
  
    if (  
        preg_match('/^[A-Z0-9_\-]{4,}$/', $text) ||  
        preg_match('/^\w+_\w+$/', $text) ||  
        preg_match('/^[\p{Hiragana}\p{Katakana}\p{Han}a-zA-Z0-9 ]{1,2}$/u', $text)  
    ) return false;  
  
    return true;  
}  
  
function translateText($text, $from, $to, &$wordbank, $langKey) {  
    $trimmed = trim($text);  
    if (isset($wordbank[$langKey][$trimmed])) {  
        return $wordbank[$langKey][$trimmed];  
    }  
  
    $q = urlencode($trimmed);  
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl={$from}&tl={$to}&dt=t&q={$q}";  
    $res = @file_get_contents($url);  
    if (!$res) return $text;  
  
    $j = json_decode($res, true);  
    $translated = $j[0][0][0] ?? $text;  
  
    if ($translated !== '' && $translated !== $trimmed) {  
        $wordbank[$langKey][$trimmed] = $translated;  
  
        // Simpan wordbank secara langsung setelah satu translasi berhasil  
        file_put_contents('wordbank.json', json_encode($wordbank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));  
    }  
  
    return $translated;  
}  
  
function applyPostProcessing($text, $rules) {  
    foreach ($rules as $rule) {  
        $search = $rule['from'] ?? '';  
        $replace = $rule['to'] ?? '';  
        if ($search !== '') {  
            $text = str_replace($search, $replace, $text);  
        }  
    }  
    return $text;  
}  
  
function translateWithPreserve($text, $from, $to, $rules, &$wordbank, $langKey) {  
    $controlCodeRegex = '/(\\[A-Za-z]+(?:\[[^\]]*\])?|\\[{}.|!^<>\\])/';  
    $parts = preg_split($controlCodeRegex, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);  
  
    $hasRealText = false;  
    foreach ($parts as $part) {  
        if (!preg_match($controlCodeRegex, $part) && trim($part) !== '') {  
            $hasRealText = true;  
            break;  
        }  
    }  
    if (!$hasRealText) return $text;  
  
    $translatedText = '';  
    foreach ($parts as $part) {  
        if (preg_match($controlCodeRegex, $part)) {  
            $translatedText .= $part;  
        } else {  
            $trimmed = trim($part);  
            if ($trimmed === '' || !shouldTranslate($trimmed)) {  
                $translatedText .= $part;  
            } else {  
                $translated = translateText($part, $from, $to, $wordbank, $langKey);  
                $corrected  = applyPostProcessing($translated, $rules);  
                $translatedText .= $corrected;  
            }  
        }  
    }  
    return $translatedText;  
}  
  
function walkAndTranslate(&$data, $from, $to, $rules, &$wordbank, $langKey, &$log, &$count, &$logMeta, $progressFile, $path = '') {  
    if (is_array($data)) {  
        foreach ($data as $key => &$value) {  
            $currentPath = $path === '' ? $key : "$path.$key";  
            if (is_array($value)) {  
                walkAndTranslate($value, $from, $to, $rules, $wordbank, $langKey, $log, $count, $logMeta, $progressFile, $currentPath);  
            } elseif (is_string($value)) {  
                if (!shouldTranslate($value) && !preg_match('/\\w+\[[^\]]*\]/', $value)) {  
                    $log[] = "[SKIPPED] $value";  
                    $logMeta['skipped'][] = $value;  
                    continue;  
                }  
                $translated = translateWithPreserve($value, $from, $to, $rules, $wordbank, $langKey);  
                if ($translated !== $value) {  
                    $log[] = "[CHECKING] $value\nBefore: $value\nAfter: $translated\n---";  
                    $logMeta['translated'][] = [  
                        "original" => $value,  
                        "translated" => $translated,  
                        "path" => $currentPath  
                    ];  
                    $value = $translated;  
                    $count++;  
                    updateProgress($count, $logMeta['total'], $progressFile);  
                }  
            }  
        }  
    }  
}