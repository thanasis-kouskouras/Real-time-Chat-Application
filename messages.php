<?php
require 'header.php';
$path = $GLOBALS['rootUrl'] . '/static';
echo '<link rel="stylesheet" href="' . $path . '/css/messages.css">';
echo '<script src="' . $path . '/js/messages-page.js"></script>';
?>

<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-10 col-lg-8 col-xl-6">
                <div id="card-searchUp" class="card p-4">
                    <h2 class="text-center mb-3">Messages</h2>
                    <!-- One-to-One Messages Section -->
                    <div class="mb-2">
                        <h5 class="text-center mb-2 message-count">
                            <i class="fas fa-comment"></i> Direct Messages
                        </h5>
                    </div>
                    <div id="card-search" class="px-3">
                        <p class="text-center text-muted">Loading messages...</p>
                    </div>

                    <!-- Divider -->
                    <hr class="my-4">

                    <!-- Group Messages Section -->
                    <div id="groupChatsSection">
                        <h5 class="text-center mb-3">
                            <i class="fas fa-users"></i> Group Messages
                        </h5>
                        <div id="groupChatsList" class="px-3">
                            <p class="text-center text-muted">Loading group messages...</p>
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