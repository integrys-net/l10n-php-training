<?php

define('DS', DIRECTORY_SEPARATOR);
//
define('JSON_TRANSLATED_FILE', "test_eng.json");

define('INPUT_PATH', __DIR__ . DS . "data" . DS . "in" . DS . "json" . DS);
define('OUTPUT_PATH', __DIR__ . DS . "data" . DS . "out" . DS . "po" . DS);
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
//
define('SINGULAR', "singular");
define('PLURALS', "plurals");
//
define('MULTILINE', "multiline");

//
define('TRANSLATION_OBJ_CTX', "context");
define('TRANSLATION_OBJ_ID', "idKey");
define('TRANSLATION_OBJ_UNTRANSLATED', "untraslated");
define('TRANSLATION_OBJ_TRANSLATION', "traslation");

function parsePoTranslation(&$oResource, array $data)
{

    foreach ($data as $gValue) {
        if (is_array($gValue)) {
            writePoGroup($oResource, $gValue);
        }
        fwrite($oResource, PHP_EOL);
    }
}

function writePoGroup(&$oResource, array $group)
{
    $scope = [];
    foreach ($group as $groupKey => $groupValue) {
        if (is_string($groupKey)) {
            switch ($groupKey) {
                case TRANSLATION_OBJ_CTX:
                    if (!empty($groupValue)) {
                        fwrite($oResource, PO_MSGCTX . $groupValue . '"' . PHP_EOL);
                    }
                    break;
                case TRANSLATION_OBJ_ID:
                    if (1 === count($groupValue[PLURALS])) {
                        fwrite($oResource, PO_MSGID . $groupValue[SINGULAR][0] . '"' . PHP_EOL);
                        fwrite($oResource, PO_MSGID_PLURAL . $groupValue[PLURALS][0] . '"' . PHP_EOL);
                        $scope[] = PLURALS;
                    } elseif (1 === count($groupValue[SINGULAR])) {
                        fwrite($oResource, PO_MSGID . $groupValue[SINGULAR][0] . '"' . PHP_EOL);
                        $scope[] = SINGULAR;
                    } elseif (1 < count($groupValue[SINGULAR])) {

                        writeMultiRow($oResource, PO_MSGID, ' "', $groupValue[SINGULAR]);
                        $scope = [SINGULAR, MULTILINE];
                    }
                    break;
                case TRANSLATION_OBJ_TRANSLATION:

                    switch (count($scope)) {
                        case 2:
                            writeMultiRow($oResource, PO_MSGSTR, ' "', $groupValue[$scope[0]]);
                            break;
                        case 1:
                            if (SINGULAR === $scope[0]) {
                                fwrite($oResource, PO_MSGSTR . $groupValue[$scope[0]][0][0] . '"' . PHP_EOL);
                            } elseif (PLURALS === $scope[0]) {
                                foreach ($groupValue[PLURALS] as $gIndex => $gValue) {
                                    if (!$gIndex) {
                                        fwrite($oResource, PO_MSGSTR_PLURAL . $gIndex . PO_MSGSTR_PLURAL_END_TKN . ' "' . $groupValue[SINGULAR][$gIndex] . '"' . PHP_EOL);
                                    }
                                    if (1 <= $gIndex) {
                                        fwrite($oResource, PO_MSGSTR_PLURAL . $gIndex . PO_MSGSTR_PLURAL_END_TKN . ' "' . $gValue[$gIndex] . '"' . PHP_EOL);
                                    }
                                }
                            }
                            break;
                        default:
                            break;
                    }
                    break;
                default:
                    break;
            }
        }
        // writeRow($oResource, $rowParts, 2 < count($scope) ? $scope[1] : $scope);
    }
}

function writeMultiRow(&$oResource, string $firstRowPrefix, string $rowsPrefix, array $data)
{
    foreach ($data as $dIndex => $dValue) {
        if (!$dIndex) {
            fwrite($oResource, $firstRowPrefix . $dValue);
        }
        if (1 <= $dIndex) {
            fwrite($oResource, $rowsPrefix . $dValue);
        }
        fwrite($oResource, '"' . PHP_EOL);
    }
}

function writeRow(&$oResource, $rowParts, string $scope)
{
    $row = "";
    $isMultiline = MULTI_LINE === $scope;
    foreach ($rowParts as $rowValue) {
        if ($isMultiline) {
            foreach ($rowValue as $rIndex => $rValue) {
                if (!$rIndex) {
                    $row = $rValue;
                }
                if (1 <= $rIndex) {
                    $row = $rValue;
                }
                $row .= '"' . PHP_EOL;
                fwrite($oResource, $row);
            }
        }
        $row =  is_array($rowValue) ? implode('', $rowValue) : $rowValue;
        $row .= '"' . PHP_EOL;
        fwrite($oResource, $row);
    }
}

// Application
$inFile = INPUT_PATH . JSON_TRANSLATED_FILE;
$fileContent = file_get_contents($inFile);

$data = json_decode($fileContent, true);

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
$oFH = fopen($oFile, "w");

parsePoTranslation($oFH, $data['translations']['data']);

fclose($oFH);
