<div id="footerMessage" class='text-center'>
    <?php
    if (isset($_POST["message"])) {
        $message = filter_var($_POST["message"]);
        if (isset($_POST["error"])) {
            echo "<input id='errorMessage' hidden value='$message'>";
        } else
            echo "<input id='successMessage' hidden value='$message'>";
    }
    ?>
</div>
<output></output>