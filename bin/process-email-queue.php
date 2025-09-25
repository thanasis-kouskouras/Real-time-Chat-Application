<?php

require_once dirname(__FILE__) . '/../includes/functions.inc.php';
require_once dirname(__FILE__) . '/../includes/email_queue_manager.php';
require_once dirname(__FILE__) . '/../config.php';

// Prevent multiple instances from running simultaneously
$lockFile = __DIR__ . '/../tmp/email_processor.lock';

if (file_exists($lockFile)) {
    $pid = file_get_contents($lockFile);
    
    // Check if the process is still running
    if (PHP_OS_FAMILY !== 'Windows') {
        if (posix_kill($pid, 0)) {
            echo "Email processor is already running (PID: $pid)\n";
            exit(0);
        }
    }
}

// Create lock file
file_put_contents($lockFile, getmypid());

// Process the queue
try {
    EmailQueue::processQueue();
} catch (Exception $e) {
    echo "Error processing email queue: " . $e->getMessage() . "\n";
} finally {
    // Remove lock file
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}
