<?php

define('DS', DIRECTORY_SEPARATOR);
//
define('IN_ITA_JS_FILE', "test_ita.po");
define('IN_ENG_JS_FILE', "test_eng.po");
define('OUT_JSON_FILE', "test_eng.json");

define('INPUT_PATH', __DIR__ . DS . "data" . DS . "in" . DS . "po" . DS);
define('OUTPUT_PATH', __DIR__ . DS . "data" . DS . "out" . DS . "json" . DS);
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
define('TRANSLATION_OBJ_CTX', "context");
define('TRANSLATION_OBJ_ID', "idKey");
define('TRANSLATION_OBJ_TRANSLATION', "traslation");
define('TRANSLATION_OBJ_UNTRANSLATED', "untraslated");
//
define('SINGLE_LINE', "singleline");
define('MULTI_LINE', "multiline");
//
define('SINGULAR', "singular");
define('PLURALS', "plurals");

//
define('END_OF_STRING', -1);

//
function getEmptyArray()
{
    return [];
}

//
function getHeaderKW(string $str)
{
    $headKWSpaceSepPos = strpos($str, " ");
    if (!$headKWSpaceSepPos) {
        return trim($str);
    }
    return substr($str, 0, $headKWSpaceSepPos);
}

//
function parsePO(&$baseResource, array $translatedData, array &$translations)
{
    //
    $newGroup = [
        TRANSLATION_OBJ_CTX => "",
        TRANSLATION_OBJ_ID => [SINGULAR => [], PLURALS => []],
        TRANSLATION_OBJ_UNTRANSLATED => [SINGULAR => [], PLURALS => []],
        TRANSLATION_OBJ_TRANSLATION => [SINGULAR => [], PLURALS => []]
    ];

    // Data
    $translations = getEmptyArray();
    $group = $newGroup;
    $scope = [];
    $type = "";
    $mLIndex = 1;

    // Read Content
    while ($line = fgets($baseResource)) {
        $line = trim($line);

        switch (true) {
                // Check if read data group finished
            case ('' === $line):
                if (1 <= count($group[TRANSLATION_OBJ_ID][$type])) {
                    if (in_array(MULTI_LINE, $scope)) {
                        array_unshift($group[TRANSLATION_OBJ_ID][$type], "");
                        array_unshift($group[TRANSLATION_OBJ_UNTRANSLATED][$type], "");
                        array_unshift($group[TRANSLATION_OBJ_TRANSLATION][$type], "");
                    }
                    $translations[] = $group;
                }
                $group = $newGroup;
                $scope = [];
                $type = "";
                $mLIndex = 1;
                break;

                // Check if line begin with msgid
            case (PO_MSGID === substr($line, 0, PO_MSGID_LEN)):
                // save value and proceed to read new group
                $idKey = substr($line, PO_MSGID_LEN, END_OF_STRING);
                $type = SINGULAR;
                if ("" !== $idKey) {
                    $group[TRANSLATION_OBJ_ID][$type][] = stripcslashes($idKey);
                }
                $scope = [PO_MSGID];
                break;

                // Check if line begin with msgstr
            case (PO_MSGSTR === substr($line, 0, PO_MSGSTR_LEN)):
                $l10nTxt = substr($line, PO_MSGSTR_LEN, END_OF_STRING);
                $type = SINGULAR;
                if ("" !== $l10nTxt) {
                    $treeKey = buildNodeKey($group[TRANSLATION_OBJ_ID][$type]);
                    $group[TRANSLATION_OBJ_UNTRANSLATED][$type][] = !empty($translatedData[$treeKey]) ? "" : stripcslashes($l10nTxt);
                    $group[TRANSLATION_OBJ_TRANSLATION][$type][] = !empty($translatedData[$treeKey]) ? $translatedData[$treeKey] : "";
                }
                $scope = [PO_MSGSTR];
                break;
                // Check if line is part of multi line idKey
            case ('"' === $line[0]):
                if ((PO_MSGID === $scope[0] || PO_MSGSTR === $scope[0]) && !in_array(MULTI_LINE, $scope)) {
                    $scope[] = MULTI_LINE;
                }
                if (PO_MSGID === $scope[0]) {
                    $group[TRANSLATION_OBJ_ID][$type][] = substr($line, 1, END_OF_STRING);
                }
                if (PO_MSGSTR === $scope[0]) {
                    $treeKey = buildNodeKey($group[TRANSLATION_OBJ_ID][$type]);
                    $group[TRANSLATION_OBJ_UNTRANSLATED][$type][] = !empty($translatedData[$treeKey]) ? "" : substr($line, 1, END_OF_STRING);
                    $group[TRANSLATION_OBJ_TRANSLATION][$type][] = !empty($translatedData[$treeKey]) ? $translatedData[$treeKey][$mLIndex] : "";
                    ++$mLIndex;
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

                // Check if line is part of plurals idKey
            case (PO_MSGSTR_PLURAL === substr($line, 0, PO_MSGSTR_PLURAL_LEN)):
                $size = strpos($line, PO_MSGSTR_PLURAL_END_TKN);
                $row = (int)substr($line, PO_MSGSTR_PLURAL_LEN, 1);
                $pL10nTxt = substr($line, $size + 3, END_OF_STRING);
                $type = PLURALS;
                if ("" !== $pL10nTxt) {
                    // Compute Plurals Row index
                    $typeRowIndex = !$row ? $row : $row - 1;
                    // Compute translated data key
                    $treeKey = buildNodeKey([
                        $group[TRANSLATION_OBJ_ID][SINGULAR][0],
                        $group[TRANSLATION_OBJ_ID][$type][$typeRowIndex]
                    ]);
                    if (!$row) {
                        $group[TRANSLATION_OBJ_UNTRANSLATED][SINGULAR][$row] = !empty($translatedData[$treeKey]) ? "" : $pL10nTxt;
                        $group[TRANSLATION_OBJ_TRANSLATION][SINGULAR][$row] = !empty($translatedData[$treeKey]) ? $translatedData[$treeKey][$row] : "";
                    }
                    $group[TRANSLATION_OBJ_UNTRANSLATED][$type][$row] = !empty($translatedData[$treeKey]) ? "" : $pL10nTxt;
                    $group[TRANSLATION_OBJ_TRANSLATION][$type][$row] = !empty($translatedData[$treeKey]) ? $translatedData[$treeKey][$row] : "";
                }
                $scope = [PO_MSGSTR_PLURAL];
                break;
            case (PO_MSGCTX === substr($line, 0, PO_MSGCTX_LEN)):
                $ctx = substr($line, PO_MSGCTX_LEN, END_OF_STRING);
                if ('' !== $ctx) {
                    $group[TRANSLATION_OBJ_CTX][] = $ctx;
                }
                $scope = [PO_MSGCTX];
                break;
            default:
                break;
        }
    }

    $translations[] = $group;
}

function buildPOTree(&$resource)
{
    $tree = [];
    $scope = [];
    $node = [
        'key' => [],
        'value' => []
    ];
    $treeNode = $node;
    while ($line = fgets($resource)) {
        $line = trim($line);

        switch (true) {
                // Check if read data group finished
            case ('' === $line):
                if (1 <= count($treeNode['key'])) {

                    if (in_array(MULTI_LINE, $scope)) {
                        array_unshift($treeNode['value'], "");
                    }
                    $treeKey = buildNodeKey($treeNode['key']);
                    addTreeNode($treeKey, $treeNode['value'], $tree);
                }
                $treeNode = $node;
                break;
            case (PO_MSGCTX === substr($line, 0, PO_MSGCTX_LEN)):
                $ctx = substr($line, PO_MSGCTX_LEN, END_OF_STRING);
                if ('' !== $ctx) {
                    $treeNode['key'][] = $ctx;
                }
                $scope = [PO_MSGCTX];
                break;

                // Check if line begin with msgid
            case (PO_MSGID === substr($line, 0, PO_MSGID_LEN)):
                // save value and proceed to read new group
                $idKey = substr($line, PO_MSGID_LEN, END_OF_STRING);
                if ("" !== $idKey) {
                    $treeNode['key'][] = $idKey;
                }
                $scope = [PO_MSGID];
                break;

                // Check if line begin with msgstr
            case (PO_MSGSTR === substr($line, 0, PO_MSGSTR_LEN)):
                $l10nTxt = substr($line, PO_MSGSTR_LEN, END_OF_STRING);
                if ("" !== $l10nTxt) {
                    $treeNode['value'][] = $l10nTxt;
                }
                $scope = [PO_MSGSTR];
                break;
                // Check if line is part of multi line idKey
            case ('"' === $line[0]):
                if ((PO_MSGID === $scope[0] || PO_MSGSTR === $scope[0]) && !in_array(MULTI_LINE, $scope)) {
                    $scope[] = MULTI_LINE;
                }
                if (PO_MSGID === $scope[0]) {
                    $treeNode['key'][] = substr($line, 1, END_OF_STRING);
                }
                if (PO_MSGSTR === $scope[0]) {
                    $treeNode['value'][] = substr($line, 1, END_OF_STRING);
                }
                break;

                // Check if line begin with msgid_plural
            case (PO_MSGID_PLURAL === substr($line, 0, PO_MSGID_PLURAL_LEN)):
                $pId = substr($line, PO_MSGID_PLURAL_LEN, END_OF_STRING);
                if ("" !== $pId) {
                    $treeNode['key'][] = $pId;
                }
                $scope = [PO_MSGID_PLURAL];
                break;

                // Check if line is part of plurals idKey
            case (PO_MSGSTR_PLURAL === substr($line, 0, PO_MSGSTR_PLURAL_LEN)):
                $size = strpos($line, PO_MSGSTR_PLURAL_END_TKN);
                $pL10nTxt = substr($line, $size + 3, END_OF_STRING);
                if ("" !== $pL10nTxt) {
                    $treeNode['value'][] = $pL10nTxt;
                }
                $scope = [PO_MSGSTR_PLURAL];
                break;
            default:
                break;
        }
    }

    $treeKey = buildNodeKey($treeNode['key']);
    addTreeNode($treeKey, $treeNode['value'], $tree);

    return $tree;
}

function addTreeNode(string $nodeKey, array $nodeValue, array &$tree)
{
    if (1 < count($nodeValue)) {
        $tree[$nodeKey] =  $nodeValue;
    } elseif (1 === count($nodeValue)) {
        $tree[$nodeKey] =  [$nodeValue[0]];
    } elseif (empty($nodeValue)) {
        $tree[$nodeKey] =  [];
    }
}

function buildNodeKey(array $keyParts, string $joinChar = '_')
{
    if (1 < count($keyParts)) {
        return implode($joinChar, $keyParts);
    } elseif (1 === count($keyParts)) {
        return $keyParts[0];
    }
}

//
$baseFileName = INPUT_PATH . IN_ITA_PO_FILE;
$baseFH = fopen($baseFileName, READ);
$translatedFH = null;

$baseTree = [];
$translatedTree = [];

$translatedFileName = INPUT_PATH . IN_ENG_PO_FILE;
if (file_exists($translatedFileName)) {
    $translatedFH = fopen($translatedFileName, READ);
    $translatedTree = buildPOTree($translatedFH);
    fclose($translatedFH);
}

$tData = [];
$tData['translations']['name'] = IN_ENG_PO_FILE;

if (!array_key_exists('data', $tData['translations'])) {
    $tData['translations']['data'] = [];
}



parsePO($baseFH, $translatedTree, $tData['translations']['data']);

if ($baseFH) {
    fclose($baseFH);
}

$fileContent = json_encode($tData, JSON_PRETTY_PRINT);
file_put_contents(OUTPUT_PATH . OUT_TEST_FILE,  $fileContent, LOCK_EX);
