<?php

define('DS', DIRECTORY_SEPARATOR);
//
define('PO_TEST_FILE', "test.po");
define('PO_DATA_FILE', "default.po");
define('INPUT_DATA', __DIR__ . DS . "data" . DS . PO_TEST_FILE);
define('OUTPUT_DATA', __DIR__ . DS . "output" . DS . "default.json");
//
define('READ', "r");
define('WRITE', "w");
define('APPEND', "a");
define('READ_WRITE', "a+");

include_once(__DIR__ . DS . "CakePoFileParser.php");

$parser = new PoFileParser();

print_r($parser->parse(INPUT_DATA));
