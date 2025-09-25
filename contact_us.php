<?php
require 'header.php';
?>
<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4 col-xl-4">
                <div class="card p-4">
                    <div id="card-contact" class="card-body">

                        <h2 class="text-center">Contact Us</h2>

                        <h6 class='alert-white'>
                            <p class='text-center'>Send us your message and we will reply to you via E-mail.</p>
                        </h6>

                        <form action="includes/contact_us.inc.php" method="post">
                            <div class="messages"></div>
                            <div class="controls">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-2">
                                            <label for="form_nickname">Nickname : </label>
                                            <?php
                                                if (isset($_GET["nickname"])) {
                                                    $nickname = filter_var($_GET["nickname"], FILTER_SANITIZE_STRING);
                                                    echo '<input type="text" name="nickname" class="form-control" placeholder="Enter Nickname" value="' . $nickname . '">';
                                                } else {
                                                    echo '<input type="text" name="nickname" class="form-control" placeholder="Enter Nickname">';
                                                }
                                                ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-2">
                                            <label for="form_surname">Surname : </label>
                                            <?php
                                                if (isset($_GET["surname"])) {
                                                    $surname = filter_var($_GET["surname"], FILTER_SANITIZE_STRING);
                                                    echo '<input type="text" name="surname" class="form-control" placeholder="Enter Surname" value="' . $surname . '">';
                                                } else {
                                                    echo '<input type="text" name="surname" class="form-control" placeholder="Enter Surname">';
                                                }
                                                ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="">
                                    <div class="">
                                        <div class="form-group mb-2">
                                            <label for="form_subject">Subject : <span
                                                    class="text-danger">*</span></label>
                                            <?php
                                                if (isset($_GET["subject"])) {
                                                    $subject = filter_var($_GET["subject"], FILTER_SANITIZE_STRING);
                                                    echo '<input id="center" type="text" name="subject" class="form-control" maxlength="100"  placeholder="Enter Subject" required="required" value="' . $subject . '">';
                                                } else {
                                                    echo '<input id="center" type="text" name="subject" class="form-control" maxlength="100"  placeholder="Enter Subject" required="required">';
                                                }
                                                ?>
                                        </div>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group mb-2">
                                            <label for="form_message">Message : <span
                                                    class="text-danger">*</span></label>
                                            <?php
                                                if (isset($_GET["message"])) {
                                                    $message = filter_var($_GET["message"], FILTER_SANITIZE_STRING);
                                                    echo '<textarea id="center" name="message" class="form-control" placeholder="Enter Message" rows="10" required="required">' . $message . '</textarea>';
                                                } else {
                                                    echo '<textarea id="center" name="message" class="form-control" placeholder="Enter Message" rows="10" required="required"></textarea>';
                                                }
                                                ?><p></p>
                                        </div>
                                    </div>
                                    <div class="form-group mb-0 ">
                                        <button class="btn btn-primary btn-block" type="submit"
                                            name="contact_us-submit">Send
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="text-center m-auto">
                            <p></p>
                            <?php
                                if (!isset($_GET["error"])) {
                                    exit();
                                } else {
                                    if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "mailSend") {
                                        echo "<p class='alert-success'>Message successfully sent!</p>";
                                        exit();
                                    } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "invalidNickSurName") {
                                        echo "<p class='alert-danger'>Invalid Nickname and Surname!</p>";
                                        exit();
                                    } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "invalidSurname") {
                                        echo "<p class='alert-danger'>Invalid Surname!</p>";
                                        exit();
                                    } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "invalidNickname") {
                                        echo "<p class='alert-danger'>Invalid Nickname!</p>";
                                        exit();
                                    } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "emptySubjectMessage") {
                                        echo "<p class='alert-danger'>Fill in the Subject and Message fields!</p>";
                                        exit();
                                    } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "emptySubject") {
                                        echo "<p class='alert-danger'>Fill in the Subject field!</p>";
                                        exit();
                                    } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "emptyMessage") {
                                        echo "<p class='alert-danger'>Fill in the Message field!</p>";
                                        exit();
                                    }
                                }
                                ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>


<?php
require 'footerMessage.php';
require 'footer.php';
?>