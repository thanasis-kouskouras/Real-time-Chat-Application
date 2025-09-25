<?php

class EmailQueue {
    private static string $queueFile = __DIR__ . '/../tmp/email_queue.json';
    
    public static function addToQueue($type, $userId, $senderName, $content): void
    {
        $emailData = [
            'type' => $type,
            'userId' => $userId,
            'senderName' => $senderName,
            'content' => $content,
            'timestamp' => time(),
            'attempts' => 0
        ];
        
        // Ensure tmp directory exists
        $tmpDir = dirname(self::$queueFile);
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        
        // Load existing queue
        $fp = null;
        $queue = self::loadQueueWithLock($fp);
        $queue[] = $emailData;
        
        // Save queue
        self::saveQueueWithLock($fp, $queue);
        
        // Trigger async processing
        self::triggerAsyncProcessing();
    }
    private static function loadQueueWithLock(&$fp = null): array
    {
        $queueFile = self::$queueFile;
        $fp = fopen($queueFile, 'c+');
        if (!$fp) return []; // Fail gracefully

        // Acquire lock (blocks until available)
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return [];
        }

        // Read file contents
        $content = stream_get_contents($fp);
        $queue = json_decode($content, true);
        return is_array($queue) ? $queue : [];
    }

    private static function saveQueueWithLock($fp, $queue) {
        // Truncate and write
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($queue, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN); // Unlock
        fclose($fp);
    }

    private static function triggerAsyncProcessing() {
        // Use exec to run the email processor in the background
        $scriptPath = __DIR__ . '/../bin/process-email-queue.php';
        
        if (PHP_OS_FAMILY === 'Windows') {
           echo exec("start /B php \"$scriptPath\" > NUL 2>&1");
        } else {
           echo exec("php \"$scriptPath\" > /dev/null 2>&1 &");
        }
    }
    
    public static function processQueue() {
        $fp = null;
        $queue = self::loadQueueWithLock($fp);
        $processedQueue = [];
        
        foreach ($queue as $emailData) {
            if ($emailData['attempts'] >= 3) {
                // Skip emails that have failed 3 times
                echo "Skipping email after 3 failed attempts: " . json_encode($emailData) . "\n";
                continue;
            }
            
            try {
                $success = false;
                
                if ($emailData['type'] === 'message' || $emailData['type'] === 'attachment') {
                    $success = sendMessageNotificationEmail(
                        $emailData['userId'],
                        $emailData['senderName'],
                        $emailData['content']
                    );
                }
                
                if ($success) {
                    echo "Email sent successfully to user ID: {$emailData['userId']}\n";
                } else {
                    // Increment attempts and keep in queue
                    $emailData['attempts']++;
                    $processedQueue[] = $emailData;
                    echo "Email failed, attempt {$emailData['attempts']} for user ID: {$emailData['userId']}\n";
                }
                
            } catch (Exception $e) {
                // Increment attempts and keep in queue
                $emailData['attempts']++;
                $processedQueue[] = $emailData;
                echo "Email exception: " . $e->getMessage() . "\n";
            }
        }
        
        // Save the updated queue (only failed emails remain)
        self::saveQueueWithLock($fp, $processedQueue);
    }
}
