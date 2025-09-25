<?php
require 'header.php';
$userUsername = $user['usersUsername'];
$userEmail = $user["usersEmail"];
$path = $GLOBALS['rootUrl'] . '/static'
?>
<script src='<?php echo $path . "/js/passwordUtilities.js" ?>'></script>
<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4 col-xl-4">
                <div class="card p-4">
                    <div id="card-profile-outer" class="card-body">
                        <div class="text-center m-auto">
                            <h2>Profile</h2>
                        </div>
                        <div class="form-group mb-3">
                            <label for="username">Username :</label>
                            <?php
                                echo "<span id='blue'> $userUsername</span>";
                                ?><br>
                            <span>Email Address :</span>
                            <?php echo "<span class='span-blue'> $userEmail</span>"; ?>
                        </div>

                        <form action="includes/profile.inc.php" method="post">
                            <div class="form-group mb-3">
                                <span id="underline">Change Username :</span>
                                <p></p>
                                <label for="newUsername">New Username :</label>
                                <input type="text" name="newUsername" maxlength="20" placeholder="Enter New Username"
                                    class="form-control">
                                <p></p>
                                <button class="btn btn-primary btn-block" type="submit"
                                    name="change-username-submit">Change
                                </button>
                            </div>
                        </form>

                        <form action="includes/profile.inc.php" method="post">
                            <span id="underline">Change Password :</span>
                            <p></p>
                            <div class="form-group mb-3">
                                <label for="password">Password :</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" name="password" id="password"
                                        placeholder="Enter Password" required="" minlength="8">
                                    <div class="input-group-addon">
                                        <span id="view-password" onclick="viewPassword('password')"><i
                                                class="fa fa-lg fa-eye" aria-hidden="true" id="eye"></i></span>
                                    </div>
                                </div>
                            </div>
                            <p></p>
                            <div class="form-group mb-3">
                                <label for="new-password">New Password :</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" name="newPassword" id="new-password"
                                        placeholder="Enter New Password" required="" minlength="8">
                                    <div class="input-group-addon">
                                        <span id="view-password" onclick="viewPassword('new-password', 'new-eye')"><i
                                                class="fa fa-lg fa-eye" aria-hidden="true" id="new-eye"></i></span>
                                    </div>
                                </div>
                            </div>
                            <p></p>
                            <div class="form-group mb-3">
                                <label for="reapeat-new-password">Confirm New Password :</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" id="confirm-new-password"
                                        name="newPasswordRepeat" placeholder="Confirm New Password" required=""
                                        minlength="8">
                                    <div class="input-group-addon">
                                        <span id="view-password"
                                            onclick="viewPassword('confirm-new-password', 'new-eye2')"><i
                                                class="fa fa-lg fa-eye" aria-hidden="true" id="new-eye2"></i></span>
                                    </div>
                                </div>
                                <p></p>
                                <button class="btn btn-primary btn-block" type="submit"
                                    name="change-password-submit">Change
                                </button>
                            </div>
                        </form>

                        <form action="includes/profile-img.inc.php" method="post" enctype="multipart/form-data">
                            <span id="underline">Upload Profile Image :</span>
                            <p></p>
                            <div class="input-group">
                                <input class="form-control" type="file" name="file" id="inputGroupFile04"
                                    aria-describedby="inputGroupFileAddon04" aria-label="Upload">
                                <button class="btn btn-primary btn-block" type="submit" name="profile-img-submit"
                                    id="inputGroupFileAddon04">Upload</button>
                            </div>
                            <div>
                                <form action="includes/profile-img.inc.php" method="post">
                                    <button id="button-delete-profile-img" class="btn btn-primary btn-block"
                                        type="submit" name="delete-profile-img-submit">Delete</button>
                                </form>
                            </div>
                        </form>
                        <button id="button-delete" class="btn btn-danger btn-block" name="delete-account"
                            onclick="deleteAccount()">Delete Account</button>
                        <p></p>
                        <div class="text-center m-auto">
                            <?php
                                checkAndPrintErrorAtBottom();
                                if (!isset($_GET["error"])) {
                                    exit();
                                } else {
                                    $currentError = filter_var($_GET["error"], FILTER_SANITIZE_STRING);
                                    if ($currentError == "nuidNone") {
                                        echo "<p class='alert-success'>Username successfully changed!</p>";
                                        exit();
                                    } else if ($currentError == "emptyInputNewUsername") {
                                        echo "<p class='alert-danger'>Enter a new Username!</p>";
                                        exit();
                                    } else if ($currentError == "invalidNewUid") {
                                        echo "<p class='alert-danger'>Invalid new Username. Only letters, numbers and _- of the special characters are allowed!</p>";
                                        exit();
                                    } else if ($currentError == "stmtfailed") {
                                        echo "<p class='alert-danger'>Something went wrong.Try again!</p>";
                                        exit();
                                    }
                                }


                                if (!isset($_GET["error"])) {
                                    exit();
                                } else {
                                    if ($_GET["error"] == "emptyInputNewPassword") {
                                        echo "<p class='alert-danger'>Fill in all the fields!</p>";
                                        exit();
                                    } else if ($_GET["error"] == "stmtfailed") {
                                        echo "<p class='alert-danger'>Something went wrong.Try again!</p>";
                                        exit();
                                    } else if ($_GET["error"] == "wrongOldPassword") {
                                        echo "<p class='alert-danger'>Enter the correct Password!</p>";
                                        exit();
                                    } else if ($_GET["error"] == "invalidNewPassword") {
                                        echo "<p class='alert-danger'>Invalid new Password!</p>";
                                        exit();
                                    } else if ($_GET["error"] == "newpasswordsdontmatch") {
                                        echo "<p class='alert-danger'>New Passwords do not match!</p>";
                                        exit();
                                    } else if ($_GET["error"] == "newpasswordNone") {
                                        echo "<p class='alert-success'>Password successfully changed!</p>";
                                        exit();
                                    }
                                }


                                if (!isset($_GET["error"])) {
                                    exit();
                                } else {
                                    if ($_GET["error"] == "none") {
                                        echo "<p class='alert-success'>Image successfully uploaded!</p>";
                                        exit();
                                    } else if ($_GET["error"] == "bigFile") {
                                        echo "<p class='alert-danger'>Very big file!</p>";
                                        exit();
                                    } else if ($_GET["error"] == "uploadError") {
                                        echo "<p class='alert-danger'>Uploading Error!</p>";
                                        exit();
                                    } else if ($_GET["error"] == "wrongType") {
                                        echo "<p class='alert-danger'>Only jpg, jpeg and png type of files are allowed!</p>";
                                        exit();
                                    }
                                }


                                if (!isset($_GET["error"])) {
                                    exit();
                                } else {
                                    if ($_GET["error"] == "notdeleted") {
                                        echo "<p class='alert-danger'>Image is not deleted!</p>";
                                        exit();
                                    } else if ($_GET["error"] == "deletedImg") {
                                        echo "<p class='alert-danger'>There is no image to delete!</p>";
                                        exit();
                                    } else if ($_GET["error"] == "successdelete") {
                                        echo "<p class='alert-success'>Image deleted!</p>";
                                        exit();
                                    }
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