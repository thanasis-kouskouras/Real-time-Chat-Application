<?php
require 'header.php';
require_once 'includes/functions.inc.php'
?>
<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4 col-xl-4">
                <div id="card-friend-search-outer" class="card p-4">
                    <div class="card-body">
                        <h2 class="text-center">Friend List</h2><br>
                        <div>
                            <?php
                                $searchString = escapeString(filter_var($_POST["search"], FILTER_SANITIZE_STRING));
                                if (isset($_POST["search-friend-submit"])) {
                                    if (empty($searchString) !== false) {
                                    echo "<h6 class='alert-danger'><p class='text-center'>Fill in the Search Field!</p></h6>";
                                    echo '<div class="form-group mb-0 d-flex justify-content-between">  
                                    <br><p class="muted pt-2">Back to <a href="friends.php" class="theme-secondary-text">Friends  →<a><p></div>';
                                    exit();
                                    }
                                    if (invalidUsername($searchString) !== false) {
                                        echo "<h6 class='alert-danger'><p class='text-center'>Invalid Username!</p></h6>";
                                        echo '<div class="form-group mb-0 d-flex justify-content-between">  
                                        <br><p class="muted pt-2">Back to <a href="friends.php" class="theme-secondary-text">Friends  →<a><p></div>';
                                        exit();
                                    }
                                    list($result, $error) = searchUser($searchString);
                                    checkError($error);
                                    $counter = 0;
                                    foreach ($result as $rowSearch) {
                                        $userUid = $rowSearch['usersUsername'];
                                        $UserID = $rowSearch['UsersID'];
                                        list($requests,$error) = getConfirmedFriendOnly($UserID, $userid);
                                        checkError($error);
                                        foreach ($requests as $row) {
                                            $rqstFromId = $row['rqstFromId'];
                                            $rqstToId = $row['rqstToId'];
                                            $rqstStatus = $row['rqstStatus'];
                                            $rqstNotificationStatus = $row['rqstNotificationStatus'];
                                            if ($rqstNotificationStatus == "Yes" && $rqstStatus == "Confirm" && $rqstToId == "$userid") {
                                                $counter = $counter + 1;
                                            }
                                        }
                                    }
                                    if ($counter == 0) {
                                        echo "<h6 class='alert-white'><p class='text-center'>There is no such friend!</p></h6>";
                                        echo '<div class="form-group mb-0 d-flex justify-content-between">  
                                        <br><p class="muted pt-2">Back to <a href="friends.php" class="theme-secondary-text">Friends  →<a><p></div>';
                                        exit();
                                    } else if ($counter == 1) {
                                        echo "<h6 class='alert-white'><p class='text-center'>There is " . $counter . " friend!</p></h6>";
                                        echo "<br>";
                                    } else if ($counter > 1) {
                                        echo "<h6 class='alert-white'><p class='text-center'>There are " . $counter . " friends!</p></h6>";
                                        echo "<br>";
                                    }
                                    echo '</div>';
                                    echo '<div  id="card-friend-search" class="card-body" >';
                                    $searchString = escapeString(filter_var($_POST["search"], FILTER_SANITIZE_STRING));
                                    list($result, $error) = searchUser($searchString);
                                    checkError($error);
                                    foreach ($result as $rowSearch) {
                                        $userUid = $rowSearch['usersUsername'];
                                        $UserID = $rowSearch['UsersID'];
                                        list($requests, $error) = getConfirmedFriendOnly($UserID, $userid);
                                        checkError($error);
                                        foreach ($requests as $row) {
                                            $rqstFromId = $row['rqstFromId'];
                                            $rqstToId = $row['rqstToId'];
                                            $rqstStatus = $row['rqstStatus'];
                                            $rqstNotificationStatus = $row['rqstNotificationStatus'];
                                            if ($rqstNotificationStatus == "Yes" && $rqstStatus == "Confirm" && $rqstToId == "$userid") {
                                                list($img, $error) = getProfileImage($rqstFromId);
                                                checkError($error);
                                                if ($img) {
                                                    list($user, $error) = getUserById($rqstFromId);
                                                    checkError($error);
                                                    generateFriendsHtml($user['usersUsername'], $img, $rqstFromId);
                                                    echo "<p></p>";
                                                }
                                            }
                                        }
                                    }
                                }
                                ?>
                        </div>
                        <div class="form-group mb-0 d-flex justify-content-between">
                            <br>
                            <p class="muted pt-2">Back to <a href="friends.php" class="theme-secondary-text">Friends
                                    →<a>
                                        <p>
                        </div>
                        <?php require 'footerMessage.php';?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
require 'footer.php';
?>