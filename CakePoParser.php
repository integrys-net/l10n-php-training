<?php

define('DS', DIRECTORY_SEPARATOR);
//
define('PO_TEST_FILE', "test.po");
define('PO_DATA_FILE', "default.po");
define('INPUT_DATA', __DIR__ . DS . "data" . DS . PO_TEST_FILE);
define('OUTPUT_DATA', __DIR__ . DS . "output" . DS . "default.json");

//
define('PLURAL_PREFIX', 'p:');

/**
 * Parses portable object (PO) format.
 *
 * From https://www.gnu.org/software/gettext/manual/gettext.html#PO-Files
 * we should be able to parse files having:
 *
 * white-space
 * #  translator-comments
 * #. extracted-comments
 * #: reference...
 * #, flag...
 * #| msgid previous-untranslated-string
 * msgid untranslated-string
 * msgstr translated-string
 *
 * extra or different lines are:
 *
 * #| msgctxt previous-context
 * #| msgid previous-untranslated-string
 * msgctxt context
 *
 * #| msgid previous-untranslated-string-singular
 * #| msgid_plural previous-untranslated-string-plural
 * msgid untranslated-string-singular
 * msgid_plural untranslated-string-plural
 * msgstr[0] translated-string-case-0
 * ...
 * msgstr[N] translated-string-case-n
 *
 * The definition states:
 * - white-space and comments are optional.
 * - msgid "" that an empty singleline defines a header.
 *
 * This parser sacrifices some features of the reference implementation the
 * differences to that implementation are as follows.
 * - Translator and extracted comments are treated as being the same type.
 * - Message IDs are allowed to have other encodings as just US-ASCII.
 *
 * Items with an empty id are ignored.
 *
 * @param string $resource The file name to parse
 * @return array
 */
function parse($resource)
{
    $stream = fopen($resource, 'rb');

    $defaults = [
        'ids' => [],
        'translated' => null,
    ];

    $messages = [];
    $item = $defaults;
    $stage = [];

    while ($line = fgets($stream)) {
        $line = trim($line);

        if ($line === '') {
            // Whitespace indicated current item is done
            _addMessage($messages, $item);
            $item = $defaults;
            $stage = [];
        } elseif (substr($line, 0, 7) === 'msgid "') {
            // We start a new msg so save previous
            _addMessage($messages, $item);
            $item['ids']['singular'] = substr($line, 7, -1);
            $stage = ['ids', 'singular'];
        } elseif (substr($line, 0, 8) === 'msgstr "') {
            $item['translated'] = substr($line, 8, -1);
            $stage = ['translated'];
        } elseif (substr($line, 0, 9) === 'msgctxt "') {
            $item['context'] = substr($line, 9, -1);
            $stage = ['context'];
        } elseif ($line[0] === '"') {
            switch (count($stage)) {
                case 2:
                    $item[$stage[0]][$stage[1]] .= substr($line, 1, -1);
                    break;

                case 1:
                    $item[$stage[0]] .= substr($line, 1, -1);
                    break;
            }
        } elseif (substr($line, 0, 14) === 'msgid_plural "') {
            $item['ids']['plural'] = substr($line, 14, -1);
            $stage = ['ids', 'plural'];
        } elseif (substr($line, 0, 7) === 'msgstr[') {
            $size = strpos($line, ']');
            $row = (int)substr($line, 7, 1);
            $item['translated'][$row] = substr($line, $size + 3, -1);
            $stage = ['translated', $row];
        }
    }
    // save last item
    _addMessage($messages, $item);
    fclose($stream);

    return $messages;
}

/**
 * Saves a translation item to the messages.
 *
 * @param array $messages The messages array being collected from the file
 * @param array $item The current item being inspected
 * @return void
 */
function _addMessage(array &$messages, array $item)
{
    if (empty($item['ids']['singular']) && empty($item['ids']['plural'])) {
        return;
    }

    $singular = stripcslashes($item['ids']['singular']);
    $context = isset($item['context']) ? $item['context'] : null;
    $translation = $item['translated'];

    if (is_array($translation)) {
        $translation = $translation[0];
    }

    $translation = stripcslashes($translation);

    if ($context !== null && !isset($messages[$singular]['_context'][$context])) {
        $messages[$singular]['_context'][$context] = $translation;
    } elseif (!isset($messages[$singular]['_context'][''])) {
        $messages[$singular]['_context'][''] = $translation;
    }

    if (isset($item['ids']['plural'])) {
        $plurals = $item['translated'];
        // PO are by definition indexed so sort by index.
        ksort($plurals);

        // Make sure every index is filled.
        end($plurals);
        $count = (int)key($plurals);

        // Fill missing spots with an empty string.
        $empties = array_fill(0, $count + 1, '');
        $plurals += $empties;
        ksort($plurals);

        $plurals = array_map('stripcslashes', $plurals);
        $key = stripcslashes($item['ids']['plural']);

        if ($context !== null) {
            $messages[PLURAL_PREFIX . $key]['_context'][$context] = $plurals;
        } else {
            $messages[PLURAL_PREFIX . $key]['_context'][''] = $plurals;
        }
    }
}

file_put_contents(OUTPUT_DATA, json_encode(parse(INPUT_DATA), JSON_PRETTY_PRINT));
