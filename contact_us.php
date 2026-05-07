<?php
require 'header.php';
?>

<script src='<?php echo $path . "/js/contact-page.js" ?>'></script>

<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-10 col-lg-8 col-xl-5">
                <div class="card p-4">

                    <div id="card-contact" class="card-body">
                        <h2 class="text-center">Contact Us</h2>

                        <h6 class='alert-white'>
                            <p class='text-center'>Send us your message and we will reply to you
                                via E-mail.</p>
                        </h6>

                        <form action="#" method="post" id="contact-form">
                            <div class="messages"></div>
                            <div class="controls">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-2">
                                            <label for="nickname">Nickname</label>
                                            <input id="nickname" type="text" name="nickname" class="form-control"
                                                maxlength="50" placeholder="Enter your nickname">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-2">
                                            <label for="surname">Surname</label>
                                            <input id="surname" type="text" name="surname" class="form-control"
                                                maxlength="50" placeholder="Enter your surname">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-2">
                                            <label for="subject">Subject <span class="text-danger">*</span></label>
                                            <input id="subject" type="text" name="subject" class="form-control"
                                                maxlength="100" placeholder="Enter your subject" required="required">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-2">
                                            <label for="message">Message <span class="text-danger">*</span></label>
                                            <textarea id="message" name="message" class="form-control"
                                                placeholder="Enter your message" rows="10" maxlength="1000"
                                                required="required"></textarea>
                                            <small id="message-counter" class="form-text text-muted text-end d-block">0
                                                / 1000</small>
                                        </div>
                                    </div>
                                    <div class="form-group mb-0 text-center">
                                        <button class="app-btn app-btn-primary w-100" type="submit"
                                            name="contact_us-submit">
                                            <i class="fas fa-paper-plane"></i> Send Message
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</main>

<?php
require 'footer.php';
?>