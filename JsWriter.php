<?php

define('DS', DIRECTORY_SEPARATOR);
//
define('JSON_TRANSLATED_FILE', "test_eng.json");

define('INPUT_PATH', __DIR__ . DS . "data" . DS . "in" . DS . "json" . DS);
define('OUTPUT_PATH', __DIR__ . DS . "data" . DS . "out" . DS . "js" . DS);
//
define('READ', "r");
define('WRITE', "w");
define('APPEND', "a");
define('READ_WRITE', "a+");

//
define('TRANSLATION_OBJ_ID', "id");
define('TRANSLATION_OBJ_TRANSLATION', "traslation");
define('TRANSLATION_OBJ_UNTRANSLATED', "untraslated");

function getLeftPaddedString(string $str, int $padLen)
{
    $strLen = strlen($str);
    return str_pad($str, $strLen + $padLen, " ", STR_PAD_LEFT);
}

function addTermination(&$resource, bool $append)
{
    if ($append) {
        fwrite($resource, "," . PHP_EOL);
        return false;
    }
    fwrite($resource, PHP_EOL);
    return $append;
}

function writeJSObj(&$resource, array $group, int &$level)
{
    $insertTerminantion = true;
    $groupLen = count($group);
    $groupEl = 0;
    foreach ($group as $groupKey => $groupValue) {
        if (is_string($groupKey)) {
            if (is_array($groupValue)) {
                ++$level;
                $key = getLeftPaddedString($groupKey, $level);
                fwrite($resource, $key . ": {" . PHP_EOL);
                writeJSObj($resource, $groupValue, $level);
                fwrite($resource, getLeftPaddedString("}", $level));
                $insertTerminantion = addTermination($resource, $insertTerminantion);
                --$level;
                continue;
            }
            $key = getLeftPaddedString($groupKey, $level);
            fwrite($resource, $key . ': "' . $groupValue . '"');
            $insertTerminantion = addTermination($resource, $insertTerminantion);
        } elseif (is_int($groupKey)) {
            $id = $groupValue[TRANSLATION_OBJ_ID];
            $value = $groupValue[TRANSLATION_OBJ_TRANSLATION];
            $key = getLeftPaddedString($id, $level + 1);
            fwrite($resource, $key . ': "' . $value . '"');
            $groupEl++;
            $insertTerminantion = addTermination($resource, $groupEl < $groupLen);
        }
    }
}

// Application
$inFile = INPUT_PATH . IN_JS_TEST_FILE;
$fileContent = file_get_contents($inFile);

$data = json_decode($fileContent, true);
file_put_contents(OUTPUT_PATH . "decoded.txt", print_r($data, true));

if (!array_key_exists('translations', $data) || !array_key_exists('name', $data['translations'])) {
    die();
}

$extSepPos = strpos($data['translations']['name'], '.');
if (!$extSepPos) {
    die();
}

$fileName = $data['translations']['name'];
$objName = substr($data['translations']['name'], 0, $extSepPos);

$oFile = OUTPUT_PATH . $fileName;
$oFH = fopen($oFile, WRITE);
// Begin JS Object
fwrite($oFH, "let " . $objName . " = {" . PHP_EOL);

$level = 1;
$insertTerminantion = true;
foreach ($data['translations']['data'] as $dKey => $dValue) {
    if (is_string($dKey)) {
        $key = getLeftPaddedString($dKey, $level);
        if (is_array($dValue)) {
            fwrite($oFH, $key . ": {" . PHP_EOL);
            writeJSObj($oFH, $dValue, $level);
            fwrite($oFH, getLeftPaddedString("}", $level));
            $insertTerminantion = addTermination($oFH, $insertTerminantion);
            continue;
        }
        fwrite($oFH, $key . ": " . $dValue);
        $insertTerminantion = addTermination($oFH, !$insertTerminantion);
    } elseif (is_int($dKey)) {
        fwrite($oFH, $dValue[TRANSLATION_OBJ_ID] . ": " . $dValue[TRANSLATION_OBJ_TRANSLATION] . "," . PHP_EOL);
        $insertTerminantion = addTermination($oFH, $insertTerminantion);
    }
}
// Closing JS Object
fwrite($oFH, "}" . PHP_EOL);
fclose($oFH);
