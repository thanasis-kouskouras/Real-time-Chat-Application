<?php
require 'header.php';
require_once('includes/searchShow.inc.php')
?>
<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4 col-xl-4">
                <div id="card-searchUp" class="card p-4">
                    <h2 class="text-center">Search</h2><br>
                    <div class="text-center">
                        <div id="card-search" class="card-body">
                            <?php
                                $input = null;
                                if (isset($_POST["search-submit"])) {
                                    $input = filter_var($_POST['search'], FILTER_SANITIZE_STRING);
                                } else if (isset($_GET["search"])) {
                                    $input = (filter_var($_GET['search'], FILTER_SANITIZE_STRING));
                                }
                                if ($input != null) {
                                    list($users, $error) = searchUserExceptMe($input, $userid);
                                    checkError($error);
                                    $search = escapeString($input);
                                    echo "<input hidden id='searchString' value='{$search}'>";
                                    htmlCheckInputAndResult($users, $search);
                                    foreach ($users as $row) {
                                        generateHtml($row, $search, $userid);
                                    }
                                }
                                ?>
                        </div>
                    </div>
                    <?php require 'footerMessage.php'; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
require 'footer.php';
?>