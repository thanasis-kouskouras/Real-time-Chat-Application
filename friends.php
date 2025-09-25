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
                            <form class="d-flex" action="friends-search.php" method="post">
                                <input class="form-control me-2" type="search" name="search" placeholder="Search" <?php
                                           if (isset($_POST["search-friend-submit"])){
                                           $search = escapeString(filter_var($_POST["search"], FILTER_SANITIZE_STRING));
                                           require_once 'includes/functions.inc.php';
                                           if (!invalidUsername($search) !== false){
                                           ?>value=<?php echo $search; ?> aria-label=<?php echo "Search"; ?> required=<?php echo "";
                                           }
                                           else if (invalidUsername($search) !== false) { ?> required <?php echo "";
                                           header('Location: friends-search.php?error=invalidusername');
                                           echo exit();
                                           }
                                    } ?>>
                                <button class="btn btn-outline-primary" type="submit" name="search-friend-submit">
                                    Search
                                </button>
                            </form>
                            <br>
                        </div>
                        <div>
                            <?php
                                list($friends, $error) = getFriends($userid);
                                checkError($error);
                                $friendsCount = count($friends);
                                if ($friendsCount == 0) {
                                    echo "<h6 class='alert-white'><p class='text-center'>There are no friends!</p></h6>";
                                } else if ($friendsCount == 1) {
                                    echo "<h6 class='alert-white'><p class='text-center'>There is " . $friendsCount . " friend!</p></h6>";
                                } else if ($friendsCount > 1) {
                                    echo "<h6 class='alert-white'><p class='text-center'>There are " . $friendsCount . " friends!</p></h6>";
                                }
                                echo "<br>";
                                ?>
                        </div>
                        <div id="card-friend" class="card-body">
                            <?php
                                foreach ($friends as $row) {
                                    $rqstFromId = $row['rqstFromId'];
                                    list($profileImage, $error) = getProfileImage($rqstFromId);
                                    checkError($error);
                                    list($friend, $error) = getUserById($rqstFromId);
                                    checkError($error);
                                    generateFriendsHtml($friend['usersUsername'], $profileImage, $rqstFromId);
                                    echo "<p></p>";
                                }
                                ?>
                        </div>
                        <div class='text-center'>
                            <?php
                                echo "<br>";
                                if (isset($_GET["error"])) {
                                    if ($_GET["error"] == "stmtfailed") {
                                        echo "<h6><p class='alert-danger'>Something went wrong.Try again!</p></h6>";
                                    } else if ($_GET["error"] == "deletefriend") {
                                        echo "<h6><p class='alert-success'>Friend successfully deleted!</p></h6>";
                                    }
                                    exit();
                                }
                                require 'footerMessage.php';
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