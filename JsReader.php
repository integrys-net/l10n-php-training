<?php

define('DS', DIRECTORY_SEPARATOR);
//
define('IN_ITA_JS_FILE', "test_ita.js");
define('IN_ENG_JS_FILE', "test_eng.js");
define('OUT_JSON_FILE', "test_eng.json");

define('INPUT_PATH', __DIR__ . DS . "data" . DS . "in" . DS . "js" . DS);
define('OUTPUT_PATH', __DIR__ . DS . "data" . DS . "out" . DS . "json" . DS);

//
define('TRANSLATION_OBJ_ID', "id");
define('TRANSLATION_OBJ_TRANSLATION', "traslation");
define('TRANSLATION_OBJ_UNTRANSLATED', "untraslated");

//
define('END_OF_STRING', -1);

function getNewGroup()
{
    return [
        TRANSLATION_OBJ_ID => "",
        TRANSLATION_OBJ_UNTRANSLATED => "",
        TRANSLATION_OBJ_TRANSLATION => ""
    ];
}

function parseJs(&$resource, array &$translations, array $translatedTree = [])
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
                parseJSObj($resource, $translations[$lineData[0]], $translatedTree, "", $lineData[0]);
            }
        }
    }
}

function parseJsObj(&$resource, array &$translations, array $translatedTree, string $key = "", string $tKey = "")
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
                $keyParts = [];
                if (!empty($tKey)) {
                    $keyParts[] = $tKey;
                }
                $keyParts[] = $lineKVPair[0];
                $lineKey = $lineKVPair[0];
                $translatedKey = buildNodeKey($keyParts);
                parseJsObj($resource, $data, $translatedTree, $lineKey, $translatedKey);
                $translations = array_merge($translations, $data);
                break;
            case '"':
                $group = getNewGroup();
                $keyParts = [];
                if ((!empty($tKey) && empty($key)) || (!empty($key) && !empty($tKey))) {
                    $keyParts[] = $tKey;
                } elseif (!empty($key) && empty($tKey)) {
                    $keyParts[] = $key;
                }
                $keyParts[] = $lineKVPair[0];
                $translatedKey = buildNodeKey($keyParts);
                $group[TRANSLATION_OBJ_ID] = $lineKVPair[0];
                $group[TRANSLATION_OBJ_UNTRANSLATED] = (empty($translatedTree) && empty($translatedKey))
                    ? substr(trim($lineKVPair[1]), 1, -1)
                    : "";
                $group[TRANSLATION_OBJ_TRANSLATION] = (!empty($translatedTree) && !empty($translatedKey))
                    ? $translatedTree[$translatedKey]
                    : "";
                if (!empty($key)) {
                    $translations[$key][] = $group;
                    break;
                }
                $translations[] = $group;
                break;
            case ',':
                $group = getNewGroup();
                $keyParts = [];
                if ((!empty($tKey) && empty($key)) || (!empty($key) && !empty($tKey))) {
                    $keyParts[] = $tKey;
                } elseif (!empty($key) && empty($tKey)) {
                    $keyParts[] = $key;
                }
                $keyParts[] = $lineKVPair[0];
                $translatedKey = buildNodeKey($keyParts);

                $group[TRANSLATION_OBJ_ID] = $lineKVPair[0];
                $group[TRANSLATION_OBJ_UNTRANSLATED] = (empty($translatedTree) && empty($tKey))
                    ? substr(trim($lineKVPair[1]), 1, -2)
                    : "";
                $group[TRANSLATION_OBJ_TRANSLATION] = (!empty($translatedTree) && !empty($tKey))
                    ? $translatedTree[$translatedKey]
                    : "";
                //
                if (!empty($key)) {
                    $translations[$key][] = $group;
                    break;
                }
                $translations[] = $group;
                break;
            default:
                break;
        }
    }
}

function buildJsTree(&$resource, array &$tree, string $key = "")
{
    while (!in_array(($line = trim(fgets($resource))), ["},", "}"])) {
        $lineLen = strlen($line);
        if (!strpos($line, ":")) {
            continue;
        }
        $lineKVPair = explode(":", $line);
        switch ($line[$lineLen - 1]) {
            case '{':
                $keyParts = [];
                if (!empty($key)) {
                    $keyParts[] = $key;
                }
                $keyParts[] = $lineKVPair[0];
                $nodeKey = buildNodeKey($keyParts);
                buildJsTree($resource, $tree, $nodeKey);
                break;
            case '"':
                $treeKeyParts = [];
                if (!empty($key)) {
                    $treeKeyParts[] = $key;
                }
                $treeKeyParts[] = $lineKVPair[0];
                $treeKey = buildNodeKey($treeKeyParts);
                $tree[$treeKey] = substr(trim($lineKVPair[1]), 1, -1);
                break;
            case ',':
                $treeKeyParts = [];
                if (!empty($key)) {
                    $treeKeyParts[] = $key;
                }
                $treeKeyParts[] = $lineKVPair[0];
                $treeKey = buildNodeKey($treeKeyParts);
                $tree[$treeKey] = substr(trim($lineKVPair[1]), 1, -2);
                break;
            default:
                break;
        }
    }
}

function buildNodeKey(array $keyParts, string $joinChar = '_')
{
    return 1 < count($keyParts) ? implode($joinChar, $keyParts) : $keyParts[0];
}

function getValue(string $inStr, int $start, int $end)
{
    return substr(trim($inStr), $start, $end);
}

//
$baseFilename = INPUT_PATH . IN_ITA_JS_FILE;
$baseFH = fopen($baseFilename, "r");
$translatedFilename = INPUT_PATH . IN_ENG_JS_FILE;
$translatedTree = [];

if (file_exists($translatedFilename)) {
    $translatedFH = fopen($translatedFilename, "r");
    buildJsTree($translatedFH, $translatedTree);
}

$tData = [];
$tData['translations']['name'] = IN_ENG_JS_FILE;
$tData['translations']['data'] = [];

parseJs($baseFH, $tData['translations']['data'], $translatedTree);

$outFile = OUTPUT_PATH . OUT_JSON_FILE;
file_put_contents($outFile, json_encode($tData, JSON_PRETTY_PRINT));
