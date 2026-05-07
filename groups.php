<?php
require 'header.php';

//Get search term from GET for initial input value
$searchTerm = $_GET["search"] ?? "";
?>

<link rel="stylesheet" href="<?php echo $GLOBALS['rootUrl'] . '/static/css/groups.css'; ?>">

<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-6">
                <div class="groups-card card p-4">

                    <!-- TITLE -->
                    <h1 class="text-center mb-4">Groups</h1>

                    <!-- SEARCH BAR -->
                    <form method="get" class="row g-2 mb-2">
                        <div class="col">
                            <input type="search" name="search" class="form-control groups-search-input"
                                placeholder="Search groups..." value="<?php echo htmlspecialchars($searchTerm); ?>"
                                maxlength="50">
                        </div>

                        <div class="col-auto">
                            <button class="app-btn app-btn-outline-primary app-btn-fixed" type="submit">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>

                    <!-- CREATE GROUP BUTTON -->
                    <p>
                    <form id="createGroupForm" action="group_create.php" method="get" class="mb-3">
                        <button class="app-btn app-btn-success" type="submit">
                            <i class="fa-solid fa-users"></i> Create Group
                        </button>
                    </form>
                    </p>

                    <!-- COUNTER WITH DYNAMIC MESSAGE -->
                    <h6 class="alert-white groups-count">
                        <p class="text-center m-0">Loading groups...</p>
                    </h6>

                    <!-- GROUPS LIST -->
                    <div id="groupsList">
                        <!-- Groups will be loaded dynamically via JavaScript -->
                    </div>

                    <!-- BACK TO GROUPS LINK (shown when searching) -->
                    <div id="backToGroupsLink" class="d-none">
                        <p class="text-center mt-4">Back to <a href="groups.php" class="theme-primary-text">Groups →</a>
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</main>

<?php 
//Include groups page JavaScript after the HTML content
$path = $GLOBALS['rootUrl'] . '/static';
echo '<script type="module" src="' . $path . '/js/modules/group-form-handler.js"></script>';
echo '<script type="module" src="' . $path . '/js/modules/groups-realtime.js"></script>';
echo '<script src="' . $path . '/js/groups-page.js"></script>';

require_once "footer.php"; 
?>