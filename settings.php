<?php
require 'header.php';
require_once(__DIR__ . '/protect.php'); // Ensure user is logged in

// Initialize default settings
$userSettings = [
    'hide_account_from_search' => 0,
    'email_notifications' => 0
];



// Get existing user settings
$existingSettings = getUserSettings($userid);
if (!empty($existingSettings)) {
    $userSettings = array_merge($userSettings, $existingSettings);
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $newSettings = [
        'hide_account_from_search' => isset($_POST['hide_account_from_search']) ? 1 : 0,
        'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0
    ];
    
    if (saveUserSettings($userid, $newSettings)) {
        $userSettings = $newSettings;
        $message = '<div class="alert-success">Settings saved successfully!</div>';
    } else {
        $message = '<div class="alert-danger">Error saving settings. Please try again.</div>';
    }
}
?>

<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4 col-xl-4">
                <div class="card p-4">
                    <div class="card-body">
                        <div class="text-center m-auto">
                            <h2>Settings</h2>
                        </div>
                        <p></p>

                        <form action="settings.php" method="post">
                            <div class="form-group mb-3">
                                <span id="underline">Privacy Settings</span>
                                <p></p>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="hide_account_from_search"
                                           name="hide_account_from_search" <?php echo $userSettings['hide_account_from_search'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="hide_account_from_search">Hide account from search</label>
                                    <p class="text-muted small">Your profile will only appear in search results when someone searches for your exact username.</p>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <span id="underline">Notification Settings</span>
                                <p></p>

                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="email_notifications"
                                           name="email_notifications" <?php echo $userSettings['email_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">Email notifications</label>
                                    <p class="text-muted small">Receive email notifications when you get new messages or notifications while offline.</p>
                                </div>
                            </div>

                            <button class="btn btn-primary btn-block" type="submit" name="save_settings">Save Settings</button>
                        </form>

                        <div class="text-center m-auto">
                            <?php
                            echo "<p>$message<p>";
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
require 'footer.php';
?>
