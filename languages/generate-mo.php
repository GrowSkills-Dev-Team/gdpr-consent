<?php
// Simple script to create .mo file from .po file
// Run this once to generate the translation file

require_once(dirname(__FILE__) . '/../../../../wp-config.php');

$po_file = dirname(__FILE__) . '/gs-gdpr-consent-nl_NL.po';
$mo_file = dirname(__FILE__) . '/gs-gdpr-consent-nl_NL.mo';

// Include WordPress POMO classes
require_once(ABSPATH . 'wp-includes/pomo/po.php');
require_once(ABSPATH . 'wp-includes/pomo/mo.php');

if (file_exists($po_file)) {
    $po = new PO();
    if ($po->import_from_file($po_file)) {
        $mo = new MO();
        $mo->headers = $po->headers;
        $mo->entries = $po->entries;
        
        if ($mo->export_to_file($mo_file)) {
            echo "Success: Created $mo_file from $po_file\n";
        } else {
            echo "Error: Could not create $mo_file\n";
        }
    } else {
        echo "Error: Could not read $po_file\n";
    }
} else {
    echo "Error: $po_file not found\n";
}
?>
