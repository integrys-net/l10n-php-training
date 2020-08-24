<?php

define('DS', DIRECTORY_SEPARATOR);
//
define('PO_TEST_FILE', "default.po");
define('JS_TEST_FILE', "test.js");
define('TEST_FILE', "test.json");
//
define('INPUT_PATH', __DIR__ . DS . "data" . DS);
define('OUTPUT_PATH', __DIR__ . DS . "output" . DS);
//
define('READ', "r");
define('WRITE', "w");
define('APPEND', "a");
define('READ_WRITE', "a+");
//
define('PO_EMPTY_LINE', '');
define('PO_MSGCTX', 'msgctx "');
define('PO_MSGID', 'msgid "');
define('PO_MSGID_PLURAL', 'msgid_plural "');
define('PO_MSGSTR', 'msgstr "');
define('PO_MSGSTR_PLURAL', 'msgstr[');
define('PO_MSGSTR_PLURAL_END_TKN', ']');
define('PLURAL_PREFIX', 'p:');
//
define('PO_MSGCTX_LEN', strlen(PO_MSGCTX));
define('PO_MSGID_LEN', strlen(PO_MSGID));
define('PO_MSGID_PLURAL_LEN', strlen(PO_MSGID_PLURAL));
define('PO_MSGSTR_LEN', strlen(PO_MSGSTR));
define('PO_MSGSTR_PLURAL_LEN', strlen(PO_MSGSTR_PLURAL));
//
define('TRANSLATION_OBJ_ID', "id");
define('TRANSLATION_OBJ_UNTRANSLATED', "untraslated");
define('TRANSLATION_OBJ_TRANSLATION', "traslation");
//
define('SINGLE_LINE', "singleline");
define('MULTI_LINE', "multiline");
//
define('SINGULAR', "singular");
define('PLURALS', "plurals");
//
define('END_OF_STRING', -1);
//
define('PO_GRP', "po");
define('JS_GRP', "js");

//
function getEmptyArray()
{
    return [];
}

function getNewGroup(string $groupType)
{
    $groups =  [
        'po' => [
            TRANSLATION_OBJ_ID => [SINGULAR => [], PLURALS => []],
            TRANSLATION_OBJ_UNTRANSLATED => [SINGULAR => [], PLURALS => []],
            TRANSLATION_OBJ_TRANSLATION => [SINGULAR => [], PLURALS => []]
        ],
        'js' => [
            TRANSLATION_OBJ_ID => "",
            TRANSLATION_OBJ_UNTRANSLATED => "",
            TRANSLATION_OBJ_TRANSLATION => ""
        ]
    ];
    return  $groups[$groupType];
}

// Parse CakePHP po files
function parsePO($resource, array &$translations)
{
    // Data
    $translations = getEmptyArray();
    $group = getNewGroup(PO_GRP);
    $scope = getEmptyArray();
    $type = "";

    // Open File

    // Read Content
    while ($line = fgets($resource)) {
        $line = trim($line);

        switch (true) {
                // Check if read data group finished
            case ("" === $line):
                if (1 <= count($group[TRANSLATION_OBJ_ID][$type])) {
                    if (in_array(MULTI_LINE, $scope)) {
                        array_unshift($group[TRANSLATION_OBJ_ID][$type], "");
                        array_unshift($group[TRANSLATION_OBJ_UNTRANSLATED][$type], "");
                        array_unshift($group[TRANSLATION_OBJ_TRANSLATION][$type], "");
                    }
                    $translations[] = $group;
                }
                $group = getNewGroup(PO_GRP);
                $scope = [];
                $type = "";
                break;


                // Check if line begin with msgid
            case (PO_MSGID === substr($line, 0, PO_MSGID_LEN)):
                // save value and proceed to read new group
                $idKey = substr($line, PO_MSGID_LEN, END_OF_STRING);
                $type = SINGULAR;
                if ("" !== $idKey) {
                    $group[TRANSLATION_OBJ_ID][$type][] = $idKey;
                }
                $scope = [PO_MSGID];
                break;

                // Check if line begin with msgstr
            case (PO_MSGSTR === substr($line, 0, PO_MSGSTR_LEN)):
                $l10nTxt = substr($line, PO_MSGSTR_LEN, END_OF_STRING);
                $type = SINGULAR;
                if ("" !== $l10nTxt) {
                    $group[TRANSLATION_OBJ_UNTRANSLATED][$type][] = $l10nTxt;
                    $group[TRANSLATION_OBJ_TRANSLATION][$type][] = "";
                }
                $scope = [PO_MSGSTR];
                break;
                // Check if line is part of multi line id
            case ('"' === $line[0]):
                if ((PO_MSGID === $scope[0] || PO_MSGSTR === $scope[0]) && !in_array(MULTI_LINE, $scope)) {
                    $scope[] = MULTI_LINE;
                }
                if (PO_MSGID === $scope[0]) {
                    $group[TRANSLATION_OBJ_ID][$type][] = substr($line, 1, END_OF_STRING);
                }
                if (PO_MSGSTR === $scope[0]) {
                    $group[TRANSLATION_OBJ_UNTRANSLATED][$type][] = substr($line, 1, END_OF_STRING);
                    $group[TRANSLATION_OBJ_TRANSLATION][$type][] = "";
                }
                break;

                // Check if line begin with msgid_plural
            case (PO_MSGID_PLURAL === substr($line, 0, PO_MSGID_PLURAL_LEN)):
                $pId = substr($line, PO_MSGID_PLURAL_LEN, END_OF_STRING);
                $type = PLURALS;
                if ("" !== $pId) {
                    $group[TRANSLATION_OBJ_ID][$type][] = $pId;
                }
                $scope = [PO_MSGID_PLURAL];
                break;

                // Check if line is part of plurals id
            case (PO_MSGSTR_PLURAL === substr($line, 0, PO_MSGSTR_PLURAL_LEN)):
                $size = strpos($line, PO_MSGSTR_PLURAL_END_TKN);
                $row = (int)substr($line, PO_MSGSTR_PLURAL_LEN, 1);
                $pL10nTxt = substr($line, $size + 3, END_OF_STRING);
                $type = PLURALS;
                if ("" !== $pL10nTxt) {
                    if (!$row) {
                        $group[TRANSLATION_OBJ_UNTRANSLATED][SINGULAR][$row] = $pL10nTxt;
                        $group[TRANSLATION_OBJ_TRANSLATION][SINGULAR][$row] = "";
                    }
                    $group[TRANSLATION_OBJ_UNTRANSLATED][$type][$row] = $pL10nTxt;
                    $group[TRANSLATION_OBJ_TRANSLATION][$type][$row] = "";
                }
                $scope = [PO_MSGSTR_PLURAL];
                break;
            case (PO_MSGCTX === substr($line, 0, PO_MSGCTX_LEN)):
                $scope = [PO_MSGCTX];
                break;
            default:
                break;
        }
    }

    $translations[] = $group;
}

function parseJsFile(&$resource, array &$translations)
{
    while ($line = fgets($resource)) {
        $line = trim($line);
        $lineLen = strlen($line);
        if (strpos($line, "=")) {
            continue;
        }
        if (strpos($line, ":")) {
            $lineData = explode(":", $line);
            if ('{' === $line[$lineLen - 1]) {
                if (!array_key_exists($lineData[0], $translations)) {
                    $translations[$lineData[0]] = [];
                }
                parseJSObj($resource, $translations[$lineData[0]]);
            }
        }
    }
}

function parseJsObj(&$resource, array &$translations, string $key = "")
{
    while (!in_array(($line = trim(fgets($resource))), ["},", "}"])) {
        $lineLen = strlen($line);
        if (!strpos($line, ":")) {
            continue;
        }
        $lineKVPair = explode(":", $line);
        switch ($line[$lineLen - 1]) {
            case '{':
                $data = [];
                $lineKey = $lineKVPair[0];
                parseJsObj($resource, $data, $lineKey);
                $translations[] = $data;
                break;
            case '"':
                $group = getNewGroup(JS_GRP);
                $group[TRANSLATION_OBJ_ID] = $lineKVPair[0];
                $group[TRANSLATION_OBJ_UNTRANSLATED] = substr(trim($lineKVPair[1]), 1, -1);
                if (empty($key)) {
                    $translations[] = $group;
                    break;
                }
                $translations[$key][] = $group;
                break;
            case ',':
                $group = getNewGroup(JS_GRP);
                $group[TRANSLATION_OBJ_ID] = $lineKVPair[0];
                $group[TRANSLATION_OBJ_UNTRANSLATED] = substr(trim($lineKVPair[1]), 1, -2);
                if (empty($key)) {
                    $translations[] = $group;
                    break;
                }
                $translations[$key][] = $group;
                break;
            default:
                break;
        }
    }
}

/**
 *
 */
$translations = [];
$inputFiles = [PO_TEST_FILE, JS_TEST_FILE];
$inputFilesLen = count($inputFiles);

for ($i = 0; $i < $inputFilesLen; ++$i) {

    $fileToParse = $inputFiles[$i];
    $extSepPos = strpos($fileToParse, ".");
    if (!$extSepPos) {
        continue;
    }
    $fileExt = substr($fileToParse, $extSepPos + 1);
    $file = INPUT_PATH . $fileToParse;
    $inFH = fopen($file, READ);
    $translations['translations'][$i]['name'] = $fileToParse;

    if (!array_key_exists('data', $translations['translations'])) {
        $translations['translations'][$i]['data'] = [];
    }

    switch ($fileExt) {
        case PO_GRP:
            parsePO($inFH, $translations['translations'][$i]['data']);
            break;
        case JS_GRP:
            parseJsFile($inFH, $translations['translations'][$i]['data']);
            break;
        default:
    }

    if ($inFH) {
        fclose($inFH);
    }
}

// echo json_encode($translations, JSON_PRETTY_PRINT);
$fileContent = json_encode($translations, JSON_PRETTY_PRINT);
$destFile = OUTPUT_PATH . TEST_FILE;
file_put_contents($destFile,  $fileContent, LOCK_EX);
