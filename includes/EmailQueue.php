<?php
/* EMAIL QUEUE

Manages a file-based email queue stored in tmp/email_queue.json, handling async email delivery.
Spawns a background process via bin/process-email-queue.php with retry logic (up to 3 attempts). */

class EmailQueue {
    private static string $queueFile = __DIR__ . '/../tmp/email_queue.json';

    public static function addToQueue($type, $user_guid, $senderName, $content, $senderGuid = null): void
    {
        $emailData = [
            'type' => $type,
            'user_guid' => $user_guid,
            'senderGuid' => $senderGuid,
            'senderName' => $senderName,
            'content' => $content,
            'timestamp' => time(),
            'attempts' => 0
        ];

        //Ensure tmp directory exists
        $tmpDir = dirname(self::$queueFile);
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        //Load existing queue, append new entry, save
        $fp = null;
        $queue = self::loadQueueWithLock($fp);
        $queue[] = $emailData;
        self::saveQueueWithLock($fp, $queue);

        //Trigger async processing
        self::triggerAsyncProcessing();
    }

    private static function loadQueueWithLock(&$fp = null): array
    {
        $queueFile = self::$queueFile;

        //Ensure directory exists
        $dir = dirname($queueFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($queueFile, 'c+');
        if (!$fp) return []; //Fail gracefully

        //Acquire lock (blocks until available)
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return [];
        }

        //Read file contents
        $content = stream_get_contents($fp);
        $queue = json_decode($content, true);
        return is_array($queue) ? $queue : [];
    }

    private static function saveQueueWithLock($fp, $queue): void
    {
        //Truncate and write
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($queue, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN); //Unlock
        fclose($fp);
    }

    private static function triggerAsyncProcessing(): void
    {
        //Run the email processor in the background
        $scriptPath = __DIR__ . '/../bin/process-email-queue.php';

        if (PHP_OS_FAMILY === 'Windows') {
            exec("start /B php \"$scriptPath\" > NUL 2>&1");
        } else {
            exec("php \"$scriptPath\" > /dev/null 2>&1 &");
        }
    }

    public static function removeQueueEntriesForUser(string $user_guid): void
    {
        $fp = null;
        $queue = self::loadQueueWithLock($fp);

        $filtered = array_values(array_filter($queue, function ($entry) use ($user_guid) {
            return ($entry['user_guid'] ?? null) !== $user_guid;
        }));

        self::saveQueueWithLock($fp, $filtered);
    }

    public static function processQueue(): void
    {
        $fp = null;
        $queue = self::loadQueueWithLock($fp);

        $processedQueue = [];

        foreach ($queue as $emailData) {
            //Skip emails that have failed 3 times
            if ($emailData['attempts'] >= 3) {
                continue;
            }

            try {
                $success = false;

                if ($emailData['type'] === 'message' || $emailData['type'] === 'attachment') {
                    $success = sendMessageNotificationEmail(
                        $emailData['user_guid'],
                        $emailData['senderName'],
                        $emailData['content'],
                        $emailData['senderGuid']
                    );
                }

                if (!$success) {
                    //Increment attempts and keep in queue for retry
                    $emailData['attempts']++;
                    $processedQueue[] = $emailData;
                }

            } catch (Exception $e) {
                app_log('[EmailQueue] Failed to send email: ' . $e->getMessage());
                //Increment attempts and keep in queue for retry
                $emailData['attempts']++;
                $processedQueue[] = $emailData;
            }
        }

        //Save updated queue (only failed/pending emails remain)
        self::saveQueueWithLock($fp, $processedQueue);
    }
}