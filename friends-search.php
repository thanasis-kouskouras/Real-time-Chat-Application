<?php
require 'header.php';
$path = $GLOBALS['rootUrl'] . '/static';
echo '<script src="' . $path . '/js/friends-search-page.js"></script>';
?>

<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-10 col-lg-8 col-xl-6">
                <div id="card-friend-search-outer" class="card p-4">
                    <h2 class="text-center">Friend List</h2><br>
                    <div class="friend-count">
                        <h6 class='alert-white'>
                            <p class='text-center'>Loading...</p>
                        </h6>
                    </div>
                    <div id="card-friend-search" class="card-body">
                        <!-- Search results will be loaded dynamically via JavaScript -->
                    </div>
                    <div class="form-group mb-0 text-center">
                        <p class="muted pt-2">Back to <a href="friends.php" class="theme-secondary-text">Friends →</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require 'footer.php';
?>