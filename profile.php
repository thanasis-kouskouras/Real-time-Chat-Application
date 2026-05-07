<?php
require 'header.php';
?>

<script src='<?php echo $path . "/js/auth.js" ?>'></script>
<script src='<?php echo $path . "/js/profile-page.js" ?>'></script>

<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5 profile-page">
                <div class="card p-4 profile-card-full">
                    <div id="card-profile-outer" class="card-body">
                        <div class="text-center m-auto">
                            <h2>Profile</h2>
                            <div id="profile-loading" class="d-none">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading profile data...</p>
                            </div>
                        </div>
                        <!-- Display area for account info (Username and Email Address) -->
                        <div class="form-group mb-3">
                            <div class="d-flex align-items-center mb-2 gap-1">
                                <span>Username</span>
                                <span class='span-blue' id='username-display'></span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span>Email Address</span>
                                <span class='span-blue' id='email-display'></span>
                            </div>
                        </div>
                        <!-- Change Username section -->
                        <form action="#" method="post" id="username-form">
                            <div class="form-group mb-3">
                                <span id="underline">Change Username</span>
                                <p></p>
                                <label for="new_username">New Username</label>
                                <input type="text" name="new_username" id="new_username" maxlength="30"
                                    placeholder="Enter your new username" class="form-control">
                                <p></p>
                                <button class="app-btn app-btn-primary w-100" type="submit" name="username-submit"><i
                                        class="fa-solid fa-arrow-right-arrow-left"></i>Change Username
                                </button>
                            </div>
                        </form>
                        <!-- Change Password section -->
                        <form action="#" method="post" id="password-form">
                            <span id="underline">Change Password</span>
                            <p></p>
                            <div class="form-group mb-3">
                                <label for="current_password">Current Password</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" name="current_password"
                                        id="current_password" placeholder="Enter your password" minlength="8"
                                        maxlength="128">
                                    <div class="input-group-addon">
                                        <span id="view-password" onclick="viewPassword('current_password')"><i
                                                class="fa fa-lg fa-eye eye-icon" aria-hidden="true" id="eye"></i></span>
                                    </div>
                                </div>
                            </div>
                            <p></p>
                            <div class="form-group mb-3">
                                <label for="new_password">New Password</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" name="new_password" id="new_password"
                                        placeholder="Enter your new password" minlength="8" maxlength="128">
                                    <div class="input-group-addon">
                                        <span id="view-password" onclick="viewPassword('new_password', 'new-eye')"><i
                                                class="fa fa-lg fa-eye eye-icon" aria-hidden="true"
                                                id="new-eye"></i></span>
                                    </div>
                                </div>
                            </div>
                            <p></p>
                            <div class="form-group mb-3">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" id="confirm_password"
                                        name="confirm_password" placeholder="Re-enter your new password" minlength="8"
                                        maxlength="128">
                                    <div class="input-group-addon">
                                        <span id="view-password"
                                            onclick="viewPassword('confirm_password', 'new-eye2')"><i
                                                class="fa fa-lg fa-eye eye-icon" aria-hidden="true"
                                                id="new-eye2"></i></span>
                                    </div>
                                </div>
                                <p></p>
                                <button class="app-btn app-btn-primary w-100" type="submit" name="password-submit"><i
                                        class="fa-solid fa-arrow-right-arrow-left"></i>Change Password
                                </button>
                            </div>
                        </form>
                        <!-- Profile Image section -->
                        <form action="#" method="post" enctype="multipart/form-data" id="upload-image-form">
                            <span id="underline">Upload Profile Image</span>
                            <p></p>
                            <div class="input-group mb-2">
                                <input class="form-control" type="file" name="file" id="inputGroupFile04"
                                    aria-describedby="inputGroupFileAddon04" aria-label="Upload"
                                    accept=".jpg,.jpeg,.png">
                                <button class="app-btn app-btn-primary" type="submit" name="profile-img-submit"
                                    id="inputGroupFileAddon04">
                                    <i class="fa-solid fa-upload"></i> Upload
                                </button>
                            </div>
                        </form>

                        <form action="#" method="post" id="delete-image-form">
                            <button id="button-delete-profile-img" class="app-btn app-btn-outline-secondary w-100 mb-2"
                                type="submit" name="delete-profile-img-submit">
                                <i class="fa-solid fa-trash"></i>Delete Profile Image
                            </button>
                        </form>
                        <!-- Delete Account section -->
                        <button id="button-delete" class="app-btn app-btn-outline-danger w-100" name="delete-account">
                            <i class="fa-solid fa-user-xmark"></i> Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
require 'footer.php';
?>