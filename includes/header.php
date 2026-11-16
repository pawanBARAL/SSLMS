<?php

/*
|--------------------------------------------------------------------------
| Shared Header
|--------------------------------------------------------------------------
|
| This file contains the common HTML header and top navigation used
| throughout SSLMS.
|
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Project Base URL
|--------------------------------------------------------------------------
|
| Change "/sslms" only if your project folder has a different name
| inside htdocs.
|
| Example:
|
| C:/xampp/htdocs/sslms/
|
| becomes:
|
| /sslms
|
|--------------------------------------------------------------------------
*/

$baseUrl = "/sslms";


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

if (!isset($pageTitle) || trim($pageTitle) === "") {
    $pageTitle = "SSLMS";
}


/*
|--------------------------------------------------------------------------
| Logged-In Student Name
|--------------------------------------------------------------------------
*/

$headerUserName =
    $_SESSION["full_name"]
    ?? "Student";


/*
|--------------------------------------------------------------------------
| Student Initials
|--------------------------------------------------------------------------
*/

$headerInitials = "S";

$nameParts = preg_split(
    "/\s+/",
    trim($headerUserName)
);

if (!empty($nameParts[0])) {

    $headerInitials =
        strtoupper(
            substr(
                $nameParts[0],
                0,
                1
            )
        );

    if (count($nameParts) > 1) {

        $headerInitials .=
            strtoupper(
                substr(
                    $nameParts[
                        count($nameParts) - 1
                    ],
                    0,
                    1
                )
            );
    }
}
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Student Smart Life Management System"
    >

    <title>
        <?php echo htmlspecialchars($pageTitle); ?>
        | SSLMS
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    >


    <!-- SSLMS Stylesheet -->

    <link
        rel="stylesheet"
        href="<?php echo $baseUrl; ?>/assets/css/style.css"
    >

</head>


<body>


<div class="app-wrapper">


    <!-- ================================================================
         MOBILE OVERLAY
         ================================================================ -->

    <div
        class="sidebar-overlay"
        id="sidebarOverlay"
    ></div>


    <!-- ================================================================
         TOP NAVIGATION
         ================================================================ -->

    <header class="topbar">


        <div class="topbar-left">


            <!-- Mobile menu button -->

            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Open navigation"
            >

                <i class="bi bi-list"></i>

            </button>


            <!-- Mobile logo -->

            <a
                href="<?php echo $baseUrl; ?>/index.php"
                class="mobile-brand"
            >

                SSLMS

            </a>


        </div>


        <div class="topbar-right">


            <!-- Calendar -->

            <a
                href="<?php echo $baseUrl; ?>/calendar/index.php"
                class="topbar-icon-button"
                title="Calendar"
            >

                <i class="bi bi-calendar3"></i>

            </a>


            <!-- Reminders -->

            <a
                href="<?php echo $baseUrl; ?>/reminders/index.php"
                class="topbar-icon-button"
                title="Reminders"
            >

                <i class="bi bi-bell"></i>

            </a>


            <!-- Profile dropdown -->

            <div class="dropdown">


                <button
                    class="profile-menu-button"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >


                    <span class="topbar-avatar">

                        <?php
                        echo htmlspecialchars(
                            $headerInitials
                        );
                        ?>

                    </span>


                    <span class="topbar-user-info">


                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $headerUserName
                            );
                            ?>

                        </strong>


                        <small>

                            Student

                        </small>


                    </span>


                    <i class="bi bi-chevron-down"></i>


                </button>


                <ul
                    class="dropdown-menu
                           dropdown-menu-end
                           shadow-sm"
                >


                    <li>

                        <a
                            class="dropdown-item"
                            href="<?php
                            echo $baseUrl;
                            ?>/profile/index.php"
                        >

                            <i
                                class="bi bi-person me-2"
                            ></i>

                            My Profile

                        </a>

                    </li>


                    <li>

                        <a
                            class="dropdown-item"
                            href="<?php
                            echo $baseUrl;
                            ?>/index.php"
                        >

                            <i
                                class="bi bi-grid me-2"
                            ></i>

                            Dashboard

                        </a>

                    </li>


                    <li>

                        <hr class="dropdown-divider">

                    </li>


                    <li>

                        <a
                            class="dropdown-item text-danger"
                            href="<?php
                            echo $baseUrl;
                            ?>/auth/logout.php"
                        >

                            <i
                                class="bi
                                       bi-box-arrow-right
                                       me-2"
                            ></i>

                            Logout

                        </a>

                    </li>


                </ul>


            </div>


        </div>


    </header>