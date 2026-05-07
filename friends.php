<?php
require 'header.php';
echo '<script src="' . $path . '/js/friends-page.js"></script>';
?>

<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-10 col-lg-8 col-xl-6">
                <div id="card-friend-search-outer" class="card p-4">
                    <h2 class="text-center">Friend List</h2><br>
                    <div>
                        <form class="d-flex gap-2" action="friends-search.php" method="get">
                            <input class="form-control" type="search" name="search" placeholder="Search friends..."
                                aria-label="Search" required maxlength="30">
                            <button class="app-btn app-btn-outline-primary btn-min-w-100" type="submit">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </form>
                        <br>
                    </div>
                    <div id="friend-count">
                        <h6 class='alert-white'>
                            <p class='text-center'>Loading friends...</p>
                        </h6>
                        <br>
                    </div>
                    <div id="card-friend" class="card-body">
                        <!-- Friends will be loaded dynamically via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require 'footer.php';
?>