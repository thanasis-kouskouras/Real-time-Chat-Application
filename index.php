<?php
require 'header.php';
?>
    <main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
        <div class="container mt-5 pt-5 mb-5">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4 col-xl-4">
                    <div class="card-body">
                        <?php
                        require 'footerMessage.php';
                        list($user, $error) = getUserById($userid);
                        checkError($error);
                        list($rowImg, $error) = getProfileImage($userid);
                        checkError($error);
                        if ($rowImg) {
                            $imgType = $rowImg['imgType'];
                            $imgStatus = $rowImg['imgStatus'];
                            echo "<div>";
                            if ($imgStatus == 0) {
                                echo "<img id=homepage-center-image src='uploads/profile" . $userid . "." . $imgType . "'>";
                            } else
                                echo "<img id='homepage-center-profile-image' src='img/profiledefault.jpg'  >";
                        }
                        echo "<strong><p id='blackBigUsername'>{$user['usersUsername']} </p></strong>";
                        echo "</div>";
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
<?php
require 'footerMessage.php';
checkAndPrintErrorAtBottom();
require 'footer.php';
?>