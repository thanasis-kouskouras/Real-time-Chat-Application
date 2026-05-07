<?php
require 'header.php';
$path = $GLOBALS['rootUrl'] . '/static';
echo '<link rel="stylesheet" href="' . $path . '/css/notifications.css">';
echo '<script src="' . $path . '/js/notifications-page.js"></script>';
?>

<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-10 col-lg-8 col-xl-6">
                <div id='card-searchUp' class="card p-4">
                    <h2 class="text-center mb-3">Notifications</h2>

                    <!-- Friend Requests Section -->
                    <div class="mb-2 mt-4">
                        <h5 class="text-center mb-2 notification-count">
                            <i class="fas fa-user-plus"></i> Friend Requests
                        </h5>
                    </div>
                    <div id="card-search" class="notification-section">
                        <p class="text-center text-muted">Loading notifications...</p>
                    </div>

                    <!-- Divider -->
                    <hr class="my-4">

                    <!-- Group Chat Notifications Section -->
                    <div class="mb-2 mt-4">
                        <h5 class="text-center mb-2 group-notification-count">
                            <i class="fas fa-users"></i> Group Chat Notifications
                        </h5>
                    </div>
                    <div id="group-notifications-list" class="notification-section">
                        <p class="text-center text-muted">Loading group notifications...</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<?php
require 'footer.php';
?>