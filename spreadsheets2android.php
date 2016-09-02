<?php

function getFolderName($key) {
    if (strtolower($key) == "default") {
        return "values";
    }
    return "values-$key";
}

function endsWith($haystack, $needle) {
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
}

function addItem($doc, $root, $type, $name, $value, $nf = false) {
    $title = $doc->createElement($type);
    if (strlen($name) > 0) {
        $title->setAttribute("name", $name);
    }
    if ($nf) {
        $title->setAttribute("formatted", "false");
    }
    $title = $root->appendChild($title);

    if (strlen($value) > 0) {
        $text = $doc->createTextNode($value);
        $title->appendChild($text);
    }
}

function getValue($values, $index) {
    return $index >= count($values) ? "" : $values[$index];
}

function getItemType($value) {
    $pos = strpos($value, "/");
    return $pos > 0 ? substr($value, 0, $pos) : $value;
}

function checkFlag($value, $flag) {
    return strpos($value, "/$flag") > 0;
}

function processData($file, $outpath) {
    $lines = explode("\r", $file);
    $first = true;
    $localizations = array();
    $header = NULL;
    foreach ($lines as $value) {
        $value = trim($value);
        if ($header == NULL) {
            $header = explode("\t", $value);
        } else {
            $localizations[] = explode("\t", $value);
        }
    }
    $keys = count($localizations);
    echo "ok\n";
    for ($index = 2; $index < sizeof($header); $index++) {
        $locale = $header[$index];
        if (strlen($locale) < 1)
            continue;

        $doc = new DOMDocument('1.0');

        $root = $doc->createElement('resources');
        $root = $doc->appendChild($root);

        $doc->formatOutput = true;
        $doc->encoding = "UTF-8";
        $empty_items = strcmp($locale, "default") == 0;
        for ($e = 0; $e < $keys; $e++) {
            $values = $localizations[$e];
            if (strlen($values[0]) > 0 && strlen($values[1]) > 0) {
                $type = getItemType($values[0]);
                $nf = checkFlag($values[0], "nf");
                if (strcmp($type, "string") == 0 && ($empty_items || strlen(getValue($values, $index)) > 0)) {
                    addItem($doc, $root, $type, $values[1], getValue($values, $index), $nf);
                } else if (strcmp($type, "string-array") == 0) {
                    $item = $doc->createElement($type);
                    $item->setAttribute("name", $values[1]);
                    $count = 0;

                    while ($e + 1 < $keys && strcmp($localizations[$e + 1][0], "item") == 0) {
                        ++$e;
                        ++$count;
                        $values = $localizations[$e];
                        addItem($doc, $item, "item", null, getValue($values, $index));
                    }
                    if ($count > 0) {
                        $root->appendChild($item);
                    }
                }
            }
        }
        $path = $outpath . "\\" . getFolderName($locale);
        echo "\n" . $path;
        if (!file_exists($path)) {
            mkdir($path, "0777", true);
        }
        $doc->save($path . "/strings.xml");
    }
    return false;
}

if (count($argv) == 4) {
    $key = $argv[1];
    $gid = $argv[2];
    $path = "https://docs.google.com/spreadsheets/d/$key/export?format=tsv&gid=$gid";
    $outpath = $argv[3];
    $file = file_get_contents($path, true);
    if ($file != NULL && strlen($file) > 0) {
        processData($file, $outpath);
        exit;
    }
} else
    echo "run \"php " . $argv[0] . " key gid {out folder}";
?>