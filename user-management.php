<?php
//Include header must be before any output (sets up session and $user)
require 'header.php';

//Check if user is admin
if (!isset($user) || $user['user_role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

//Include admin CSS and JS
$path = $GLOBALS['rootUrl'] . '/static';
echo '<link rel="stylesheet" href="' . $path . '/css/admin.css" />';
echo '<script src="' . $path . '/js/admin-management.js"></script>';

//Get search term from URL for initial page load
$searchTerm = $_GET['search'] ?? '';
?>

<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-12 col-md-12 col-lg-10 col-xl-8">
                <div id="card-user-management-outer" class="card p-4">
                    <div class="card-body">
                        <h2 class="text-center">User Management</h2><br>

                        <!-- Search Form -->
                        <div>
                            <form id="search-form" class="d-flex gap-2">
                                <input class="form-control" type="search" name="search" id="search-input"
                                    placeholder="Search by username or email"
                                    value="<?php echo htmlspecialchars($searchTerm); ?>" aria-label="Search"
                                    maxlength="254">
                                <button class="app-btn app-btn-outline-primary btn-min-w-100" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </form>
                        </div>

                        <!-- Status Filter Dropdown -->
                        <div class="d-flex justify-content-end mb-3">
                            <select id="status-filter" class="form-select filter-select-sm">
                                <option value="default">Default</option>
                                <option value="active">Active</option>
                                <option value="offline">Offline</option>
                                <option value="banned">Banned</option>
                            </select>
                        </div>

                        <!-- User Count -->
                        <div id="user-count" class="text-center">
                            <h6 class='alert-white'>
                                <p class='text-center'>Loading users...</p>
                            </h6>
                            <br>
                        </div>

                        <!-- User Cards Container -->
                        <div id="card-users" class="card-body">
                            <!-- Users will be loaded dynamically via JavaScript -->
                        </div>

                        <!-- Back to User Management link (shown when searching) -->
                        <div id="backToUserManagementLink" class="d-none">
                            <p class="text-center mt-4">Back to <a href="user-management.php"
                                    class="theme-primary-text">User Management →</a></p>
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