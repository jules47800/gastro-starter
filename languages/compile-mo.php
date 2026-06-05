<?php
/**
 * Compile .po files into .mo files
 * Run once, then delete this file.
 * 
 * Usage: visit /wp-content/themes/gastro-starter/languages/compile-mo.php in your browser
 * OR include it from WordPress context.
 */

function po_to_mo($po_file, $mo_file) {
    $entries = [];
    $current_msgid = null;
    $current_msgstr = null;
    $current_msgid_plural = null;
    $current_msgstr_plural = [];
    $in_msgid = false;
    $in_msgstr = false;
    $in_msgid_plural = false;
    $in_msgstr_plural = false;
    $plural_index = 0;

    $lines = file($po_file, FILE_IGNORE_NEW_LINES);

    $save_entry = function() use (&$entries, &$current_msgid, &$current_msgstr, &$current_msgid_plural, &$current_msgstr_plural) {
        if ($current_msgid !== null) {
            if ($current_msgid_plural !== null) {
                // Plural form
                $key = $current_msgid . "\x00" . $current_msgid_plural;
                ksort($current_msgstr_plural);
                $val = implode("\x00", $current_msgstr_plural);
                $entries[$key] = $val;
            } else {
                $entries[$current_msgid] = $current_msgstr;
            }
        }
        $current_msgid = null;
        $current_msgstr = null;
        $current_msgid_plural = null;
        $current_msgstr_plural = [];
    };

    foreach ($lines as $line) {
        $line = trim($line);
        
        if ($line === '' || $line[0] === '#') {
            if ($current_msgid !== null || $current_msgstr !== null) {
                $save_entry();
                $in_msgid = $in_msgstr = $in_msgid_plural = $in_msgstr_plural = false;
            }
            continue;
        }

        if (preg_match('/^msgid\s+"(.*)"$/', $line, $m)) {
            if ($current_msgid !== null) {
                $save_entry();
            }
            $current_msgid = stripcslashes($m[1]);
            $current_msgstr = '';
            $in_msgid = true;
            $in_msgstr = $in_msgid_plural = $in_msgstr_plural = false;
        } elseif (preg_match('/^msgid_plural\s+"(.*)"$/', $line, $m)) {
            $current_msgid_plural = stripcslashes($m[1]);
            $in_msgid_plural = true;
            $in_msgid = $in_msgstr = $in_msgstr_plural = false;
        } elseif (preg_match('/^msgstr\s+"(.*)"$/', $line, $m)) {
            $current_msgstr = stripcslashes($m[1]);
            $in_msgstr = true;
            $in_msgid = $in_msgid_plural = $in_msgstr_plural = false;
        } elseif (preg_match('/^msgstr\[(\d+)\]\s+"(.*)"$/', $line, $m)) {
            $plural_index = (int)$m[1];
            $current_msgstr_plural[$plural_index] = stripcslashes($m[2]);
            $in_msgstr_plural = true;
            $in_msgid = $in_msgstr = $in_msgid_plural = false;
        } elseif (preg_match('/^"(.*)"$/', $line, $m)) {
            $str = stripcslashes($m[1]);
            if ($in_msgid) {
                $current_msgid .= $str;
            } elseif ($in_msgid_plural) {
                $current_msgid_plural .= $str;
            } elseif ($in_msgstr) {
                $current_msgstr .= $str;
            } elseif ($in_msgstr_plural) {
                $current_msgstr_plural[$plural_index] .= $str;
            }
        }
    }
    $save_entry();

    // Remove empty header entry key but keep the value
    $header = isset($entries['']) ? $entries[''] : '';
    unset($entries['']);

    // Sort entries by original string
    ksort($entries);

    // Re-add header at the beginning
    $sorted = ['' => $header];
    foreach ($entries as $k => $v) {
        $sorted[$k] = $v;
    }
    $entries = $sorted;

    // Build .mo file
    $count = count($entries);
    $originals_offset = 28;
    $translations_offset = $originals_offset + $count * 8;
    $hash_size = 0;
    $hash_offset = $translations_offset + $count * 8;
    $strings_offset = $hash_offset;

    $originals = array_keys($entries);
    $translations = array_values($entries);

    // Calculate string positions
    $orig_table = '';
    $trans_table = '';
    $strings = '';
    $offset = $strings_offset;

    $orig_offsets = [];
    foreach ($originals as $str) {
        $len = strlen($str);
        $orig_offsets[] = [$len, $offset];
        $strings .= $str . "\x00";
        $offset += $len + 1;
    }

    $trans_offsets = [];
    foreach ($translations as $str) {
        $len = strlen($str);
        $trans_offsets[] = [$len, $offset];
        $strings .= $str . "\x00";
        $offset += $len + 1;
    }

    // Build binary
    $mo = pack('L', 0x950412de); // magic
    $mo .= pack('L', 0); // revision
    $mo .= pack('L', $count); // number of strings
    $mo .= pack('L', $originals_offset); // offset of originals table
    $mo .= pack('L', $translations_offset); // offset of translations table
    $mo .= pack('L', $hash_size); // hash table size
    $mo .= pack('L', $hash_offset); // hash table offset

    foreach ($orig_offsets as $o) {
        $mo .= pack('L', $o[0]);
        $mo .= pack('L', $o[1]);
    }

    foreach ($trans_offsets as $o) {
        $mo .= pack('L', $o[0]);
        $mo .= pack('L', $o[1]);
    }

    $mo .= $strings;

    file_put_contents($mo_file, $mo);
    return $count;
}

$dir = __DIR__;
$count_en = po_to_mo($dir . '/en_US.po', $dir . '/en_US.mo');

echo "Compiled en_US.mo: {$count_en} entries.\n";
echo "You can now delete this file (compile-mo.php).\n";
