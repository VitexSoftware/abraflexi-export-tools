#!/usr/bin/env php
<?php

declare(strict_types=1);

use AbraFlexi\FakturaPrijata;
use AbraFlexi\Priloha;
use Ease\Shared;


require_once '../vendor/autoload.php';

\define('APP_NAME', 'AbraFlexi Received Invoices Export');

$exitcode = 0;
$report = [];
/**
 * Get today's Statements list.
 */
$options = getopt('o::e::', ['output::environment::']);
Shared::init(
    [
        'ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY',
    ],
    \array_key_exists('environment', $options) ? $options['environment'] : (\array_key_exists('e', $options) ? $options['e'] : '../.env'),
);
$destination = \array_key_exists('o', $options) ? $options['o'] : (\array_key_exists('output', $options) ? $options['output'] : \Ease\Shared::cfg('RESULT_FILE', 'php://stdout'));


$destDir = sys_get_temp_dir() . '/flexibee-invoices/';
if (!is_dir($destDir)) {
    mkdir($destDir, 0777, true);
}

$files = glob($destDir . '*');
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

$invoices = new FakturaPrijata();
$all = $invoices->getColumnsFromAbraFlexi(['id', 'kod'], ['limit'=>0], 'id');
$pdfFiles = [];

foreach ($all as $invoice) {
    $inv = new FakturaPrijata( \AbraFlexi\Functions::code($invoice['kod']));
    $pdfPath = $inv->downloadInFormat('pdf', $destDir);
    if ($pdfPath) {
        $pdfFiles[] = $pdfPath;
    }
    $invoices->addStatusMessage(sprintf(_('Downloaded invoice %s (%s)'), $inv->getRecordCode(), $inv->getRecordId()), 'success');
    $report['exported'][$inv->getRecordCode()] = [];
    // Download all attachments
    $attachments = Priloha::getAttachmentsList($inv);
    foreach ($attachments as $attachment) {
        if (isset($attachment['id'])) {
            $saved = Priloha::saveToFile((int)$attachment['id'], $destDir);
            if ($saved) {
                $attachmentPath = $destDir . $attachment['nazSoub'];
                // Check if the attachment is an ISDOCX file
                $ext = strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION));
                if ($ext === 'isdocx') {
                    $zip = new ZipArchive();
                    if ($zip->open($attachmentPath) === TRUE) {
                        // Look for PDF inside the ISDOCX archive
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $entry = $zip->getNameIndex($i);
                            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'pdf') {
                                $pdfContent = $zip->getFromName($entry);
                                $pdfInsideIsdocx = $attachmentPath . '.pdf';
                                file_put_contents($pdfInsideIsdocx, $pdfContent);
                                $pdfFiles[] = $pdfInsideIsdocx;
                                $report['exported'][$inv->getRecordCode()][] = basename($pdfInsideIsdocx);
                                $invoices->addStatusMessage(sprintf(_('Extracted PDF from %s'), $attachment['nazSoub']), 'success');
                            }
                        }
                        $zip->close();
                    } else {
                        $invoices->addStatusMessage(sprintf(_('Failed to open ISDOCX file %s'), $attachment['nazSoub']), 'error');
                    }
                } elseif ($ext === 'isdoc') {
                    // Skip ISDOC files entirely
                    $invoices->addStatusMessage(sprintf(_('Skipped ISDOC file %s'), $attachment['nazSoub']), 'info');
                } elseif ($ext === 'pdf') {
                    $pdfFiles[] = $attachmentPath;
                    $report['exported'][$inv->getRecordCode()][] = basename($attachmentPath);
                }
                $invoices->addStatusMessage(sprintf(_('Downloaded invoice %s attachment %s'), $inv->getRecordCode(), $attachment['nazSoub']), 'success');
            }
        }
    }
}

// Merge all PDFs into one
$outputPdf = getcwd() . '/all-invoices-and-attachments.pdf';
$mergeCmd = 'pdfunite ' . implode(' ', array_map('escapeshellarg', $pdfFiles)) . ' ' . escapeshellarg($outputPdf);

passthru($mergeCmd, $mergeResult);

if ($mergeResult === 0) {
    $invoices->addStatusMessage('Merged PDF created: '.$outputPdf, 'success');
} else {
    $invoices->addStatusMessage('Failed to merge PDFs', 'error');
    $exitcode = 2;
}


$invoices->addStatusMessage('stage 6/6: saving report', 'debug');

$report['exitcode'] = $exitcode;
$written = file_put_contents($destination, json_encode($report, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$invoices->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
