<?php

class Item {

    public $translatable = true;
    public $formatted = true;
    public $key;
    public $value;
    public $type;
    public $items;

}

function php4_scandir($dir, $listDirectories = false, $skipDots = true, $filter = null) {
    $dirArray = array();
    if ($handle = opendir($dir)) {
        while (false !== ($file = readdir($handle))) {
            if ((($file != "." && $file != "..") || $skipDots != true) && ($listDirectories != false || !is_dir($file)) && ($filter == null || (strpos($file, $filter) === 0))) {
                array_push($dirArray, basename($file));
            }
        }
        closedir($handle);
    }
    return $dirArray;
}

function getLocale($name) {
    if (strcmp($name, "values") == 0)
        return "default";
    return ($pos = strpos($name, "-")) > 0 ? substr($name, $pos + 1) : null;
}

function getFolderName($key) {
    if (strtolower($key) == "default") {
        return "values";
    }
    return "values-$key";
}

function getItems($node) {
    $result = array();
    $children = $node->getElementsByTagName("item");
    foreach ($children as $child) {
        $result[] = $child->nodeValue;
    }
    return $result;
}

function parseXml($filename) {
    $result = array();
    $doc = new DOMDocument('1.0');
    $doc->load($filename);
    $children = $doc->getElementsByTagName("*");
    foreach ($children as $child) {
        if (strcmp($child->nodeName, "string") === 0) {
            $item = new Item();
            $item->key = $child->getAttribute('name');
            $item->translatable = (strcmp("false", $child->getAttribute('translatable')) == 0) ? false : true;
            $item->formatted = (strcmp("false", $child->getAttribute('formatted')) == 0) ? false : true;
            $item->value = $child->nodeValue;
            $item->type = $child->nodeName;
            $item->items = null;
            $result[$item->key] = $item;
        } else if (strcmp($child->nodeName, "string-array") === 0) {
            $item = new Item();
            $item->key = $child->getAttribute('name');
            $item->value = null;
            $item->type = $child->nodeName;
            $item->items = getItems($child);
            $result[$item->key] = $item;
        }
    }
    return $result;
}

function endsWith($haystack, $needle) {
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
}

function processData($file, $outpath) {
    $lines = explode("\r", $file);
    $first = true;
    $localizations = array();
    $header = NULL;
    foreach ($lines as $value) {
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

        for ($e = 0; $e < $keys; $e++) {
            $values = $localizations[$e];
            if (strlen($values[1]) > 0 && strlen($values[$index]) > 0) {
                $title = $doc->createElement("string");
                $title->setAttribute("name", $values[1]);
                $title = $root->appendChild($title);

                $text = $doc->createTextNode($values[$index]);
                $title->appendChild($text);
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

if (count($argv) == 3) {
    $in = $argv[1];
    $out = $argv[2];

    $list = php4_scandir($in, true, true, "values");
    $localizations = array();
    $hashset = array();
    foreach ($list as $path) {
        $strings_path = $in . "\\" . $path . "\\strings.xml";
        if (!is_dir($in . "\\" . $path) || !file_exists($strings_path))
            continue;
        $locale = getLocale($path);
        $map = parseXml($strings_path);
        if ($map != null) {
            foreach ($map as $key => $val) {
                $hashset[$key] = true;
            }
            $localizations[$locale] = $map;
        }
    }
    $result = "Type\tKey";
    foreach ($localizations as $locale => $val) {
        $result.= "\t" . $locale;
    }
    $result.= PHP_EOL;

    //   var_dump($localizations);
    ksort($hashset);
    foreach ($hashset as $key => $val) {
        $first = true;
        $array_values = null;
        $skip = 0;
        foreach ($localizations as $locale => $val) {
            if (!array_key_exists($key, $val)) {
                ++$skip;
                continue;
            }
            $item = $val[$key];
            if ($first) {
                $first = false;
                $result.= $item->type;
                if (!$item->formatted) {
                    $result.= "/nf";
                }
                if (!$item->translatable) {
                    $result.= "/tf";
                }
                $result.= "\t" . $key;
                if ($item->type == "string-array") {
                    $array_values = array();
                }
            }
            if ($item->type == "string") {
                for ($index = 0; $index < $skip; $index++) {
                    $result.= "\t";
                }
                $result.= "\t" . $item->value;
            } else if ($item->type == "string-array") {
                $array_values_index = 0;
                foreach ($item->items as $value) {
                    $value2 = "";
                    for ($index = 0; $index < $skip; $index++) {
                        $value2.= "\t";
                    }
                    $value2.=$value;

                    if ($array_values_index >= count($array_values)) {
                        $array_values[] = $value2;
                    } else {
                        $array_values[$array_values_index].= "\t" . $value2;
                    }
                    ++$array_values_index;
                }
            }
        }
        $result.= PHP_EOL;
        if ($array_values != null) {
            foreach ($array_values as $item) {
                $result.= "item\t\t" . $item . PHP_EOL;
            }
        }
    }
    file_put_contents($out, $result);
} else
    echo "run \"php " . $argv[0] . " {resources path} {output file}";
?>