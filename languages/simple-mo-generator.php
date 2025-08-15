<?php
// Simple MO file generator without WordPress dependencies
// This creates a basic .mo file from the .po file

$po_file = __DIR__ . '/gs-gdpr-consent-nl_NL.po';
$mo_file = __DIR__ . '/gs-gdpr-consent-nl_NL.mo';

if (!file_exists($po_file)) {
    die("PO file not found: $po_file\n");
}

// Read PO file
$po_content = file_get_contents($po_file);

// Simple parser for msgid and msgstr
preg_match_all('/msgid\s+"([^"]*)"(?:\s*msgstr\s+"([^"]*)")?/s', $po_content, $matches, PREG_SET_ORDER);

$translations = array();
foreach ($matches as $match) {
    if (isset($match[2]) && $match[2] !== '' && $match[1] !== '') {
        $msgid = stripcslashes($match[1]);
        $msgstr = stripcslashes($match[2]);
        $translations[$msgid] = $msgstr;
    }
}

// Create basic MO file structure
$mo_data = '';

// MO file header
$mo_data .= pack('V', 0x950412de); // Magic number
$mo_data .= pack('V', 0); // Version
$mo_data .= pack('V', count($translations)); // Number of strings
$mo_data .= pack('V', 28); // Offset of table with original strings
$mo_data .= pack('V', 28 + count($translations) * 8); // Offset of table with translation strings

$original_table = '';
$translation_table = '';
$original_data = '';
$translation_data = '';

$offset = 28 + count($translations) * 16;

foreach ($translations as $original => $translation) {
    $original_table .= pack('V', strlen($original));
    $original_table .= pack('V', $offset);
    $original_data .= $original . "\0";
    $offset += strlen($original) + 1;
}

foreach ($translations as $original => $translation) {
    $translation_table .= pack('V', strlen($translation));
    $translation_table .= pack('V', $offset);
    $translation_data .= $translation . "\0";
    $offset += strlen($translation) + 1;
}

$mo_data .= $original_table . $translation_table . $original_data . $translation_data;

// Write MO file
file_put_contents($mo_file, $mo_data);

echo "MO file generated: $mo_file\n";
?>
