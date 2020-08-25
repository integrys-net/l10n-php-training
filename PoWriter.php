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
    // Insert first empty block
    writeRow($oResource, PO_MSGID, '');
    appendRowTermination($oResource);
    insertEmptyLine($oResource);
    writeRow($oResource, PO_MSGSTR, '');
    appendRowTermination($oResource);
    insertEmptyLine($oResource);
    insertEmptyLine($oResource);
    // fwrite($oResource, PHP_EOL);

    foreach ($data as $translation) {
        if (is_array($translation)) {
            parsePoGroup($oResource, $translation);
        }
        fwrite($oResource, PHP_EOL);
    }
}

function parsePoGroup(&$oResource, array $group)
{
    $scope = [];
    foreach ($group as $groupKey => $groupValue) {
        if (is_string($groupKey)) {
            switch ($groupKey) {
                case TRANSLATION_OBJ_CTX:
                    writeContextRow($oResource, $groupValue);
                    break;
                case TRANSLATION_OBJ_ID:
                    writeTranslationId($oResource, $groupValue, $scope);
                    break;
                case TRANSLATION_OBJ_TRANSLATION:
                    writeTranslationValue($oResource, $groupValue, $scope);
                    break;
                default:
                    break;
            }
        }
    }
}

function writeContextRow(&$oResource, string $ctxValue)
{
    if (!empty($ctxValue)) {
        writeRow($oResource, PO_MSGCTX, $ctxValue);
    }
}

function writeTranslationId(&$oResource, array $data, array &$scope)
{
    if (1 === count($data[PLURALS])) {
        writeRow($oResource, PO_MSGID, $data[SINGULAR][0]);
        appendRowTermination($oResource);
        insertEmptyLine($oResource);
        writeRow($oResource, PO_MSGID_PLURAL, $data[PLURALS][0]);
        appendRowTermination($oResource);
        insertEmptyLine($oResource);
        $scope[] = PLURALS;
    } elseif (1 === count($data[SINGULAR])) {
        writeRow($oResource, PO_MSGID, $data[SINGULAR][0]);
        appendRowTermination($oResource);
        insertEmptyLine($oResource);
        $scope[] = SINGULAR;
    } elseif (1 < count($data[SINGULAR])) {
        writeMultiRow($oResource, PO_MSGID, ' "', $data[SINGULAR]);
        $scope = [SINGULAR, MULTILINE];
    }
}

function writeTranslationValue(&$oResource, array $data, array $scope)
{
    switch (count($scope)) {
        case 2:
            writeMultiRow($oResource, PO_MSGSTR, ' "', $data[$scope[0]]);
            break;
        case 1:
            if (SINGULAR === $scope[0]) {
                writeRow($oResource, PO_MSGSTR, $data[SINGULAR][0][0]);
                appendRowTermination($oResource);
                insertEmptyLine($oResource);
            } elseif (PLURALS === $scope[0]) {
                writePlurals($oResource, $data);
            }
            break;
        default:
            break;
    }
}

function writePlurals(&$oResource, array $data)
{
    foreach ($data[PLURALS] as $gIndex => $gValue) {
        $rowPrefix = PO_MSGSTR_PLURAL . $gIndex . PO_MSGSTR_PLURAL_END_TKN . ' "';
        if (!$gIndex) {
            writeRow($oResource,  $rowPrefix, $data[SINGULAR][$gIndex]);
        }
        if (1 <= $gIndex) {
            writeRow($oResource,  $rowPrefix, $gValue);
        }
        appendRowTermination($oResource);
        insertEmptyLine($oResource);
    }
}

function writeMultiRow(&$oResource, string $firstRowPrefix, string $rowsPrefix, array $data)
{
    foreach ($data as $dIndex => $dValue) {
        if (!$dIndex) {
            writeRow($oResource, $firstRowPrefix, $dValue);
        }
        if (1 <= $dIndex) {
            writeRow($oResource, $rowsPrefix, $dValue);
        }
        appendRowTermination($oResource);
        insertEmptyLine($oResource);
    }
}

function writeRow(&$oResource, string $rowPrefix, string $rowValue)
{
    fwrite($oResource, $rowPrefix . $rowValue);
}

function appendRowTermination($oResource)
{
    fwrite($oResource, '"');
}

function insertEmptyLine($oResource)
{
    fwrite($oResource, PHP_EOL);
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
