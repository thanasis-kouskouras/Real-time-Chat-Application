<?php
require 'header.php';
?>
    <main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
        <div class="container mt-5 pt-5 mb-5">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-6 col-lg-4 col-xl-4">
                    <div class="card-body text-center">
                        <div class='text-center'>
                            <img id='homepage-center-profile-image' src='img/profiledefault.jpg' alt='Loading profile...' >
                            <strong><p id='blackBigUsername'>Loading...</p></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Index page specific JavaScript -->
    <script src='<?php echo $GLOBALS['rootUrl'] . "/static/js/index-page.js" ?>'></script>
    
<?php
require 'footer.php';
?>