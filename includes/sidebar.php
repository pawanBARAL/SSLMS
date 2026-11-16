<?php

/*
|--------------------------------------------------------------------------
| SSLMS Sidebar
|--------------------------------------------------------------------------
|
| Main navigation sidebar used throughout the Student Smart Life
| Management System.
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Current Page
|--------------------------------------------------------------------------
*/

$currentUri =
    $_SERVER["REQUEST_URI"]
    ?? "";


/*
|--------------------------------------------------------------------------
| Project Base URL
|--------------------------------------------------------------------------
|
| Change this only if your project folder is renamed.
|
|--------------------------------------------------------------------------
*/

$baseUrl = "/sslms";


/*
|--------------------------------------------------------------------------
| User Information
|--------------------------------------------------------------------------
*/

$sidebarFullName =
    $_SESSION["full_name"]
    ?? "Student";

$sidebarEmail =
    $_SESSION["email"]
    ?? "";


/*
|--------------------------------------------------------------------------
| User Initials
|--------------------------------------------------------------------------
*/

$nameParts =
    preg_split(
        "/\s+/",
        trim($sidebarFullName)
    );

$initials = "";

if (!empty($nameParts[0])) {

    $initials .=
        strtoupper(
            substr(
                $nameParts[0],
                0,
                1
            )
        );
}

if (
    count($nameParts) > 1
    &&
    !empty(
        $nameParts[
            count($nameParts) - 1
        ]
    )
) {

    $initials .=
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

if ($initials === "") {
    $initials = "ST";
}


/*
|--------------------------------------------------------------------------
| Active Navigation Helper
|--------------------------------------------------------------------------
*/

function isSidebarPage(
    string $currentUri,
    string $page
): bool {

    return
        strpos(
            $currentUri,
            $page
        ) !== false;
}


/*
|--------------------------------------------------------------------------
| Navigation States
|--------------------------------------------------------------------------
*/

$isDashboard =
    parse_url(
        $currentUri,
        PHP_URL_PATH
    ) ===
    $baseUrl . "/index.php"
    ||
    parse_url(
        $currentUri,
        PHP_URL_PATH
    ) ===
    $baseUrl . "/";


$isCalendar =
    isSidebarPage(
        $currentUri,
        "/calendar/"
    );


$isStudyPlanner =
    isSidebarPage(
        $currentUri,
        "/academics/study_planner.php"
    );


$isAcademics =
    isSidebarPage(
        $currentUri,
        "/academics/"
    )
    &&
    !$isStudyPlanner;


$isFinance =
    isSidebarPage(
        $currentUri,
        "/finance/"
    );


$isWork =
    isSidebarPage(
        $currentUri,
        "/work/"
    );


$isReminders =
    isSidebarPage(
        $currentUri,
        "/reminders/"
    );


$isAnalytics =
    isSidebarPage(
        $currentUri,
        "/analytics/"
    );


$isProfile =
    isSidebarPage(
        $currentUri,
        "/profile/"
    );

$isGuide = isSidebarPage($currentUri, "/guide/");

?>


<!-- ======================================================================
     MOBILE SIDEBAR OVERLAY
     ====================================================================== -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<!-- ======================================================================
     SIDEBAR
     ====================================================================== -->

<aside
    class="sidebar"
    id="sidebar"
>


    <!-- ==================================================================
         BRAND
         ================================================================== -->

    <div class="sidebar-brand">


        <a
            href="<?php
            echo $baseUrl;
            ?>/index.php"
        >


            <div class="sidebar-logo">

                <i class="bi bi-journal-bookmark-fill"></i>

            </div>


            <div>

                <strong>
                    SSLMS
                </strong>

                <small>
                    Your student space
                </small>

            </div>


        </a>


    </div>


    <!-- ==================================================================
         NAVIGATION
         ================================================================== -->

    <nav class="sidebar-navigation">


        <!-- ==============================================================
             OVERVIEW
             ============================================================== -->

        <div class="sidebar-section-title">

            OVERVIEW

        </div>


        <!-- Dashboard -->

        <a
            href="<?php
            echo $baseUrl;
            ?>/index.php"
            class="sidebar-link <?php
            echo
                $isDashboard
                ? "active"
                : "";
            ?>"
        >

            <i class="bi bi-grid-1x2-fill"></i>

            <span>
                Dashboard
            </span>

        </a>


        <!-- Calendar -->

        <a
            href="<?php
            echo $baseUrl;
            ?>/calendar/index.php"
            class="sidebar-link <?php
            echo
                $isCalendar
                ? "active"
                : "";
            ?>"
        >

            <i class="bi bi-calendar3"></i>

            <span>
                Calendar
            </span>

        </a>



        <!-- ==============================================================
             STUDENT LIFE
             ============================================================== -->

        <div class="sidebar-section-title">

            STUDENT LIFE

        </div>


        <!-- Academics -->

        <a
            href="<?php
            echo $baseUrl;
            ?>/academics/index.php"
            class="sidebar-link <?php
            echo
                $isAcademics
                ? "active"
                : "";
            ?>"
        >

            <i class="bi bi-mortarboard"></i>

            <span>
                Academics
            </span>

        </a>


        <!-- Study Planner -->

        <a
            href="<?php
            echo $baseUrl;
            ?>/academics/study_planner.php"
            class="sidebar-link <?php
            echo
                $isStudyPlanner
                ? "active"
                : "";
            ?>"
        >

            <i class="bi bi-lightbulb"></i>

            <span>
                Study Planner
            </span>

        </a>


        <!-- Finance -->

        <a
            href="<?php
            echo $baseUrl;
            ?>/finance/index.php"
            class="sidebar-link <?php
            echo
                $isFinance
                ? "active"
                : "";
            ?>"
        >

            <i class="bi bi-wallet2"></i>

            <span>
                Finance
            </span>

        </a>


        <!-- Work -->

        <a
            href="<?php
            echo $baseUrl;
            ?>/work/index.php"
            class="sidebar-link <?php
            echo
                $isWork
                ? "active"
                : "";
            ?>"
        >

            <i class="bi bi-briefcase"></i>

            <span>
                Work Management
            </span>

        </a>


        <!-- Reminders -->

        <a
            href="<?php
            echo $baseUrl;
            ?>/reminders/index.php"
            class="sidebar-link <?php
            echo
                $isReminders
                ? "active"
                : "";
            ?>"
        >

            <i class="bi bi-bell"></i>

            <span>
                Reminders
            </span>

        </a>



        <!-- ==============================================================
             INSIGHTS
             ============================================================== -->

        <div class="sidebar-section-title">

            INSIGHTS

        </div>


        <!-- Analytics -->

        <a
            href="<?php
            echo $baseUrl;
            ?>/analytics/index.php"
            class="sidebar-link <?php
            echo
                $isAnalytics
                ? "active"
                : "";
            ?>"
        >

            <i class="bi bi-bar-chart-line"></i>

            <span>
                Analytics
            </span>

        </a>



        <!-- Help for students -->
        <a href="<?= htmlspecialchars($baseUrl) ?>/guide/index.php"
           class="sidebar-link <?= $isGuide ? 'active' : '' ?>">
            <i class="bi bi-question-circle"></i>
            <span>User Guide</span>
        </a>


        <!-- ==============================================================
             ACCOUNT
             ============================================================== -->

        <div class="sidebar-section-title">

            ACCOUNT

        </div>


        <!-- Profile -->

        <a
            href="<?php
            echo $baseUrl;
            ?>/profile/index.php"
            class="sidebar-link <?php
            echo
                $isProfile
                ? "active"
                : "";
            ?>"
        >

            <i class="bi bi-person-circle"></i>

            <span>
                My Profile
            </span>

        </a>


    </nav>


    <!-- ==================================================================
         LOGGED-IN STUDENT
         ================================================================== -->

    <div class="sidebar-user">


        <!-- Avatar -->

        <div class="sidebar-user-avatar">

            <?php
            echo htmlspecialchars(
                $initials
            );
            ?>

        </div>


        <!-- Student Information -->

        <div class="sidebar-user-details">

            <strong>

                <?php
                echo htmlspecialchars(
                    $sidebarFullName
                );
                ?>

            </strong>

            <small>

                <?php

                if ($sidebarEmail !== "") {

                    echo htmlspecialchars(
                        $sidebarEmail
                    );

                } else {

                    echo "Student";
                }

                ?>

            </small>

        </div>


        <!-- Logout -->

        <a
            href="<?php
            echo $baseUrl;
            ?>/auth/logout.php"
            class="sidebar-logout"
            title="Sign out"
            aria-label="Sign out"
        >

            <i class="bi bi-box-arrow-right"></i>

        </a>


    </div>


</aside>