<?php
require 'header.php';
?>

<link rel="stylesheet" href="static/css/privacy-policy.css">

<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-6 col-lg-4 col-xl-4">
                <div class="card p-4">
                    <div class="card-body">
                        <div class="text-center m-auto">
                            <h2>Settings</h2>
                        </div>
                        <p></p>

                        <form id="settings-form">
                            <div class="form-group mb-3">
                                <span id="underline">Privacy Settings</span>
                                <p></p>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="hide_account_from_search"
                                        name="hide_account_from_search">
                                    <label class="form-check-label" for="hide_account_from_search">Hide account from
                                        search</label>
                                    <p class="text-muted small">Your profile will only appear in search results when
                                        someone searches for your exact username.</p>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <span id="underline">Notification Settings</span>
                                <p></p>

                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="email_notifications"
                                        name="email_notifications">
                                    <label class="form-check-label" for="email_notifications">Email
                                        notifications</label>
                                    <p class="text-muted small">Get notified by email when someone sends you a friend request or a direct message while you're offline.</p>
                                </div>
                            </div>

                            <button class="app-btn app-btn-primary w-100" type="submit" name="save_settings">
                                <i class="fa-solid fa-save"></i>Save Settings
                            </button>
                        </form>

                        <div class="text-center m-auto">
                            <!-- Messages will be handled by JavaScript toast notifications -->
                        </div>

                        <hr class="my-3">

                        <p class="text-center text-muted small mb-0">
                            <a href="privacy-policy.php" data-privacy-link>
                                <i class="fa-solid fa-shield-halved"></i> Privacy Policy
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Settings page JavaScript -->
<script src="static/js/settings-page.js"></script>
<script src="static/js/modules/privacy-policy-modal.js"></script>

<?php
require 'footer.php';
?>