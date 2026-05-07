<?php
/* EMAIL QUEUE PROCESSOR

Background CLI script spawned by EmailQueue::triggerAsyncProcessing() to process
the pending email queue. 
Uses a lock file to prevent concurrent executions. */

require_once dirname(__FILE__) . '/../includes/functions.inc.php';
require_once dirname(__FILE__) . '/../includes/EmailQueue.php';
require_once dirname(__FILE__) . '/../config.php';

//Prevent multiple instances from running simultaneously
$lockFile = __DIR__ . '/../tmp/email_processor.lock';

//Ensure tmp directory exists
$tmpDir = dirname($lockFile);
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0755, true);
}

if (file_exists($lockFile)) {
    if (PHP_OS_FAMILY === 'Windows') {
        //Cannot inspect PID on Windows (assume the process is still running)
        app_log("[EmailProcessor] Lock file exists, assuming already running");
        exit(0);
    }
    $pid = (int)file_get_contents($lockFile);
    if (posix_kill($pid, 0)) {
        app_log("[EmailProcessor] Already running (PID: $pid)");
        exit(0);
    }
    //Stale lock file on Linux (continue and overwrite it)
}

//Create lock file
file_put_contents($lockFile, getmypid());

//Process the queue
try {
    app_log("[EmailProcessor] Starting queue processing (PID: " . getmypid() . ")");
    EmailQueue::processQueue();
    app_log("[EmailProcessor] Queue processing finished");
} catch (Exception $e) {
    app_log("[EmailProcessor] ERROR: " . $e->getMessage());
    app_log("[EmailProcessor] Stack trace: " . $e->getTraceAsString());
} finally {
    //Remove lock file
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}