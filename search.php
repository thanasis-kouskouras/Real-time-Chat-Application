<?php
require 'header.php';
$path = $GLOBALS['rootUrl'] . '/static';
echo '<script src="' . $path . '/js/search-page.js"></script>';
?>

<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-10 col-lg-8 col-xl-6">
                <div id="card-searchUp" class="card p-4">
                    <h2 class="text-center">Search</h2><br>
                    <div id="search-count"></div>
                    <div id="card-search" class="card-body">
                        <p class="text-center text-muted">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require 'footer.php';
?>