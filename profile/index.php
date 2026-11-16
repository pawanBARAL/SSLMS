<?php

/*
|--------------------------------------------------------------------------
| Profile & Account Management
|--------------------------------------------------------------------------
|
| This page allows the logged-in student to:
|
| - View account information
| - Update their name and email address
| - Store university and course information
| - Change their password securely
| - View basic account information
|
| All database operations are restricted to the currently logged-in user.
|
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = $_SESSION["user_id"];

$profileSuccess = "";
$profileError = "";

$passwordSuccess = "";
$passwordError = "";


/*
|--------------------------------------------------------------------------
| Retrieve Current User
|--------------------------------------------------------------------------
*/

$getUser = $pdo->prepare(
    "SELECT
        id,
        full_name,
        email,
        university,
        course,
        student_number,
        year_of_study,
        created_at
     FROM users
     WHERE id = ?"
);

$getUser->execute([
    $userId
]);

$user = $getUser->fetch();


/*
|--------------------------------------------------------------------------
| Make Sure User Exists
|--------------------------------------------------------------------------
*/

if (!$user) {

    session_destroy();

    header(
        "Location: ../auth/login.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Update Profile Information
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["update_profile"])
) {

    /*
    |--------------------------------------------------------------------------
    | Read Submitted Information
    |--------------------------------------------------------------------------
    */

    $fullName =
        trim(
            $_POST["full_name"] ?? ""
        );

    $email =
        trim(
            $_POST["email"] ?? ""
        );

    $university =
        trim(
            $_POST["university"] ?? ""
        );

    $course =
        trim(
            $_POST["course"] ?? ""
        );

    $studentNumber =
        trim(
            $_POST["student_number"] ?? ""
        );

    $yearOfStudy =
        trim(
            $_POST["year_of_study"] ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | Validate Required Information
    |--------------------------------------------------------------------------
    */

    if (
        $fullName === ""
        ||
        $email === ""
    ) {

        $profileError =
            "Your name and email address are required.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $profileError =
            "Please enter a valid email address.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Check Whether Email Is Already Used
        |--------------------------------------------------------------------------
        |
        | Another user should not be allowed to use the same email address.
        |
        */

        $checkEmail = $pdo->prepare(
            "SELECT id
             FROM users
             WHERE email = ?
             AND id != ?"
        );

        $checkEmail->execute([
            $email,
            $userId
        ]);


        if ($checkEmail->fetch()) {

            $profileError =
                "That email address is already being used by another account.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | Update User Profile
            |--------------------------------------------------------------------------
            */

            $updateProfile = $pdo->prepare(
                "UPDATE users
                 SET
                    full_name = ?,
                    email = ?,
                    university = ?,
                    course = ?,
                    student_number = ?,
                    year_of_study = ?
                 WHERE id = ?"
            );


            $updateProfile->execute([

                $fullName,

                $email,

                $university !== ""
                    ? $university
                    : null,

                $course !== ""
                    ? $course
                    : null,

                $studentNumber !== ""
                    ? $studentNumber
                    : null,

                $yearOfStudy !== ""
                    ? $yearOfStudy
                    : null,

                $userId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Update Session Name
            |--------------------------------------------------------------------------
            |
            | This allows the new name to immediately appear in places such as
            | the dashboard header without requiring another login.
            |
            */

            $_SESSION["full_name"] =
                $fullName;


            /*
            |--------------------------------------------------------------------------
            | Refresh User Information
            |--------------------------------------------------------------------------
            */

            $getUser->execute([
                $userId
            ]);

            $user =
                $getUser->fetch();


            $profileSuccess =
                "Your profile has been updated successfully.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Keep Submitted Values When Validation Fails
    |--------------------------------------------------------------------------
    */

    if ($profileError !== "") {

        $user["full_name"] =
            $fullName;

        $user["email"] =
            $email;

        $user["university"] =
            $university;

        $user["course"] =
            $course;

        $user["student_number"] =
            $studentNumber;

        $user["year_of_study"] =
            $yearOfStudy;
    }
}


/*
|--------------------------------------------------------------------------
| Change Password
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["change_password"])
) {

    /*
    |--------------------------------------------------------------------------
    | Read Password Fields
    |--------------------------------------------------------------------------
    */

    $currentPassword =
        $_POST["current_password"]
        ?? "";

    $newPassword =
        $_POST["new_password"]
        ?? "";

    $confirmPassword =
        $_POST["confirm_password"]
        ?? "";


    /*
    |--------------------------------------------------------------------------
    | Validate Password Fields
    |--------------------------------------------------------------------------
    */

    if (
        $currentPassword === ""
        ||
        $newPassword === ""
        ||
        $confirmPassword === ""
    ) {

        $passwordError =
            "Please complete all password fields.";

    } elseif (
        strlen($newPassword) < 8
    ) {

        $passwordError =
            "Your new password must contain at least 8 characters.";

    } elseif (
        $newPassword !==
        $confirmPassword
    ) {

        $passwordError =
            "The new passwords do not match.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Retrieve Current Password Hash
        |--------------------------------------------------------------------------
        */

        $getPassword = $pdo->prepare(
            "SELECT password
             FROM users
             WHERE id = ?"
        );

        $getPassword->execute([
            $userId
        ]);

        $passwordRecord =
            $getPassword->fetch();


        /*
        |--------------------------------------------------------------------------
        | Verify Current Password
        |--------------------------------------------------------------------------
        */

        if (
            !$passwordRecord
            ||
            !password_verify(
                $currentPassword,
                $passwordRecord["password"]
            )
        ) {

            $passwordError =
                "Your current password is incorrect.";

        } elseif (
            password_verify(
                $newPassword,
                $passwordRecord["password"]
            )
        ) {

            $passwordError =
                "Your new password must be different from your current password.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | Securely Hash New Password
            |--------------------------------------------------------------------------
            */

            $newPasswordHash =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


            /*
            |--------------------------------------------------------------------------
            | Update Password
            |--------------------------------------------------------------------------
            */

            $updatePassword =
                $pdo->prepare(
                    "UPDATE users
                     SET password = ?
                     WHERE id = ?"
                );


            $updatePassword->execute([
                $newPasswordHash,
                $userId
            ]);


            $passwordSuccess =
                "Your password has been changed successfully.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| Profile Completion
|--------------------------------------------------------------------------
|
| Calculates how much profile information the student has completed.
|
|--------------------------------------------------------------------------
*/

$profileFields = [

    $user["full_name"] ?? "",
    $user["email"] ?? "",
    $user["university"] ?? "",
    $user["course"] ?? "",
    $user["student_number"] ?? "",
    $user["year_of_study"] ?? ""

];


$completedProfileFields = 0;


foreach (
    $profileFields
    as
    $profileField
) {

    if (
        trim(
            (string) $profileField
        ) !== ""
    ) {

        $completedProfileFields++;
    }
}


$profileCompletion =
    count($profileFields) > 0
    ?
    round(
        (
            $completedProfileFields
            /
            count($profileFields)
        )
        *
        100
    )
    :
    0;


/*
|--------------------------------------------------------------------------
| Student Initials
|--------------------------------------------------------------------------
|
| Used for the profile avatar without requiring an uploaded image.
|
|--------------------------------------------------------------------------
*/

$initials = "";

$nameParts =
    preg_split(
        "/\s+/",
        trim(
            $user["full_name"]
        )
    );


if (!empty($nameParts)) {

    $initials .=
        strtoupper(
            substr(
                $nameParts[0],
                0,
                1
            )
        );


    if (
        count($nameParts) > 1
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
}


if ($initials === "") {

    $initials = "S";
}


/*
|--------------------------------------------------------------------------
| Account Membership Length
|--------------------------------------------------------------------------
*/

$memberSince =
    "Unknown";


if (
    !empty(
        $user["created_at"]
    )
) {

    $memberSince =
        date(
            "F Y",
            strtotime(
                $user["created_at"]
            )
        );
}


/*
|--------------------------------------------------------------------------
| Page Layout
|--------------------------------------------------------------------------
*/

$pageTitle =
    "My Profile";

include("../includes/header.php");
include("../includes/sidebar.php");

?>


<main class="main-content">


    <!-- ================================================================
         PAGE HEADING
         ================================================================ -->

    <div
        class="page-heading d-flex
               justify-content-between
               align-items-center
               flex-wrap gap-3"
    >

        <div>

            <p class="page-kicker">
                Account Management
            </p>

            <h1>
                My Profile
            </h1>

            <p class="text-muted mb-0">

                Manage your personal,
                academic and account information.

            </p>

        </div>


        <a
            href="../index.php"
            class="btn btn-outline-primary"
        >

            <i class="bi bi-grid"></i>

            Dashboard

        </a>

    </div>


    <!-- ================================================================
         PROFILE SUMMARY
         ================================================================ -->

    <div class="row g-4 mb-4">


        <!-- Profile identity -->

        <div class="col-lg-8">


            <div class="content-card h-100">


                <div
                    class="profile-summary
                           d-flex
                           align-items-center
                           gap-4
                           flex-wrap"
                >


                    <div class="profile-avatar">

                        <?php
                        echo htmlspecialchars(
                            $initials
                        );
                        ?>

                    </div>


                    <div class="flex-grow-1">


                        <p class="page-kicker mb-1">

                            Student Account

                        </p>


                        <h2 class="mb-1">

                            <?php
                            echo htmlspecialchars(
                                $user["full_name"]
                            );
                            ?>

                        </h2>


                        <p class="text-muted mb-2">

                            <i class="bi bi-envelope"></i>

                            <?php
                            echo htmlspecialchars(
                                $user["email"]
                            );
                            ?>

                        </p>


                        <?php if (
                            !empty(
                                $user["course"]
                            )
                        ): ?>

                            <p class="mb-0">

                                <i class="bi bi-mortarboard"></i>

                                <?php
                                echo htmlspecialchars(
                                    $user["course"]
                                );
                                ?>

                            </p>

                        <?php endif; ?>


                    </div>


                </div>


            </div>


        </div>


        <!-- Profile completion -->

        <div class="col-lg-4">


            <div class="content-card h-100">


                <p class="page-kicker">

                    Profile Status

                </p>


                <h3>

                    Profile Completion

                </h3>


                <div
                    class="d-flex
                           justify-content-between
                           mt-4 mb-2"
                >

                    <span>

                        Completed

                    </span>


                    <strong>

                        <?php
                        echo $profileCompletion;
                        ?>%

                    </strong>

                </div>


                <div
                    class="progress"
                    style="height: 12px;"
                >

                    <div
                        class="progress-bar"
                        style="width: <?php
                        echo $profileCompletion;
                        ?>%;"
                    ></div>

                </div>


                <div
                    class="d-flex
                           justify-content-between
                           mt-4"
                >

                    <span class="text-muted">

                        Member since

                    </span>


                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $memberSince
                        );
                        ?>

                    </strong>

                </div>


            </div>


        </div>


    </div>


    <!-- ================================================================
         PROFILE INFORMATION
         ================================================================ -->

    <div class="row g-4">


        <div class="col-xl-8">


            <div class="content-card">


                <div class="content-card-header">


                    <div>

                        <p class="page-kicker">

                            Personal Information

                        </p>

                        <h3>

                            Profile Details

                        </h3>

                    </div>


                    <i
                        class="bi bi-person-circle
                               fs-3"
                    ></i>


                </div>


                <!-- Profile success message -->

                <?php if (
                    $profileSuccess !== ""
                ): ?>

                    <div
                        class="alert
                               alert-success
                               alert-dismissible
                               fade show"
                    >

                        <?php
                        echo htmlspecialchars(
                            $profileSuccess
                        );
                        ?>


                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                        ></button>

                    </div>

                <?php endif; ?>


                <!-- Profile error message -->

                <?php if (
                    $profileError !== ""
                ): ?>

                    <div class="alert alert-danger">

                        <?php
                        echo htmlspecialchars(
                            $profileError
                        );
                        ?>

                    </div>

                <?php endif; ?>


                <form method="POST">
                    <?= csrf_field() ?>


                    <div class="row g-4">


                        <!-- Full name -->

                        <div class="col-md-6">


                            <label class="form-label">

                                Full Name *

                            </label>


                            <input
                                type="text"
                                name="full_name"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $user["full_name"]
                                );
                                ?>"
                                maxlength="150"
                                required
                            >


                        </div>


                        <!-- Email -->

                        <div class="col-md-6">


                            <label class="form-label">

                                Email Address *

                            </label>


                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $user["email"]
                                );
                                ?>"
                                maxlength="190"
                                required
                            >


                        </div>


                        <!-- University -->

                        <div class="col-md-6">


                            <label class="form-label">

                                University

                            </label>


                            <input
                                type="text"
                                name="university"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $user["university"]
                                    ?? ""
                                );
                                ?>"
                                maxlength="200"
                                placeholder="Example: University of Greenwich"
                            >


                        </div>


                        <!-- Course -->

                        <div class="col-md-6">


                            <label class="form-label">

                                Course / Programme

                            </label>


                            <input
                                type="text"
                                name="course"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $user["course"]
                                    ?? ""
                                );
                                ?>"
                                maxlength="200"
                                placeholder="Example: BSc Computer Science"
                            >


                        </div>


                        <!-- Student number -->

                        <div class="col-md-6">


                            <label class="form-label">

                                Student Number

                            </label>


                            <input
                                type="text"
                                name="student_number"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $user["student_number"]
                                    ?? ""
                                );
                                ?>"
                                maxlength="100"
                                placeholder="Enter your student number"
                            >


                        </div>


                        <!-- Year of study -->

                        <div class="col-md-6">


                            <label class="form-label">

                                Year of Study

                            </label>


                            <select
                                name="year_of_study"
                                class="form-select"
                            >


                                <option value="">

                                    Select year

                                </option>


                                <?php

                                $studyYears = [
                                    "Foundation",
                                    "Year 1",
                                    "Year 2",
                                    "Year 3",
                                    "Year 4",
                                    "Postgraduate",
                                    "Other"
                                ];


                                foreach (
                                    $studyYears
                                    as
                                    $studyYear
                                ):

                                ?>


                                    <option
                                        value="<?php
                                        echo htmlspecialchars(
                                            $studyYear
                                        );
                                        ?>"
                                        <?php
                                        echo
                                        (
                                            $user[
                                                "year_of_study"
                                            ]
                                            ??
                                            ""
                                        )
                                        ===
                                        $studyYear
                                        ?
                                        "selected"
                                        :
                                        "";
                                        ?>
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $studyYear
                                        );
                                        ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                        </div>


                    </div>


                    <div class="form-actions">


                        <button
                            type="submit"
                            name="update_profile"
                            class="btn btn-primary"
                        >

                            <i
                                class="bi
                                       bi-check-circle"
                            ></i>

                            Save Profile

                        </button>


                    </div>


                </form>


            </div>


        </div>


        <!-- ============================================================
             ACCOUNT INFORMATION
             ============================================================ -->

        <div class="col-xl-4">


            <div class="content-card mb-4">


                <p class="page-kicker">

                    Account

                </p>


                <h3>

                    Account Information

                </h3>


                <div class="profile-info-list mt-4">


                    <div
                        class="d-flex
                               justify-content-between
                               gap-3
                               py-3
                               border-bottom"
                    >

                        <span class="text-muted">

                            Account ID

                        </span>


                        <strong>

                            #<?php
                            echo (int)
                            $user["id"];
                            ?>

                        </strong>


                    </div>


                    <div
                        class="d-flex
                               justify-content-between
                               gap-3
                               py-3
                               border-bottom"
                    >

                        <span class="text-muted">

                            Account Type

                        </span>


                        <strong>

                            Student

                        </strong>


                    </div>


                    <div
                        class="d-flex
                               justify-content-between
                               gap-3
                               py-3
                               border-bottom"
                    >

                        <span class="text-muted">

                            Member Since

                        </span>


                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $memberSince
                            );
                            ?>

                        </strong>


                    </div>


                    <div
                        class="d-flex
                               justify-content-between
                               gap-3
                               py-3"
                    >

                        <span class="text-muted">

                            Profile

                        </span>


                        <strong>

                            <?php
                            echo $profileCompletion;
                            ?>%

                        </strong>


                    </div>


                </div>


            </div>


            <!-- ========================================================
                 QUICK LINKS
                 ======================================================== -->

            <div class="content-card">


                <p class="page-kicker">

                    Quick Access

                </p>


                <h3>

                    My SSLMS

                </h3>


                <div
                    class="d-grid
                           gap-2
                           mt-4"
                >


                    <a
                        href="../academics/index.php"
                        class="btn
                               btn-outline-primary
                               text-start"
                    >

                        <i
                            class="bi
                                   bi-mortarboard
                                   me-2"
                        ></i>

                        Academics

                    </a>


                    <a
                        href="../finance/index.php"
                        class="btn
                               btn-outline-primary
                               text-start"
                    >

                        <i
                            class="bi
                                   bi-wallet2
                                   me-2"
                        ></i>

                        Finance

                    </a>


                    <a
                        href="../work/index.php"
                        class="btn
                               btn-outline-primary
                               text-start"
                    >

                        <i
                            class="bi
                                   bi-briefcase
                                   me-2"
                        ></i>

                        Work Management

                    </a>


                    <a
                        href="../reminders/index.php"
                        class="btn
                               btn-outline-primary
                               text-start"
                    >

                        <i
                            class="bi
                                   bi-bell
                                   me-2"
                        ></i>

                        Reminders

                    </a>


                    <a
                        href="../calendar/index.php"
                        class="btn
                               btn-outline-primary
                               text-start"
                    >

                        <i
                            class="bi
                                   bi-calendar3
                                   me-2"
                        ></i>

                        Calendar

                    </a>


                </div>


            </div>


        </div>


    </div>


    <!-- ================================================================
         PASSWORD MANAGEMENT
         ================================================================ -->

    <div class="content-card mt-4">


        <div class="content-card-header">


            <div>

                <p class="page-kicker">

                    Security

                </p>

                <h3>

                    Change Password

                </h3>

                <p class="text-muted mb-0">

                    Use a strong password that you
                    do not use on other websites.

                </p>

            </div>


            <i
                class="bi bi-shield-lock
                       fs-3"
            ></i>


        </div>


        <!-- Password success -->

        <?php if (
            $passwordSuccess !== ""
        ): ?>

            <div
                class="alert
                       alert-success
                       alert-dismissible
                       fade show"
            >

                <?php
                echo htmlspecialchars(
                    $passwordSuccess
                );
                ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <!-- Password error -->

        <?php if (
            $passwordError !== ""
        ): ?>

            <div class="alert alert-danger">

                <?php
                echo htmlspecialchars(
                    $passwordError
                );
                ?>

            </div>

        <?php endif; ?>


        <form method="POST">
                    <?= csrf_field() ?>


            <div class="row g-4">


                <!-- Current password -->

                <div class="col-lg-4">


                    <label class="form-label">

                        Current Password *

                    </label>


                    <input
                        type="password"
                        name="current_password"
                        class="form-control"
                        autocomplete="current-password"
                        required
                    >


                </div>


                <!-- New password -->

                <div class="col-lg-4">


                    <label class="form-label">

                        New Password *

                    </label>


                    <input
                        type="password"
                        name="new_password"
                        class="form-control"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >


                    <small class="text-muted">

                        Minimum 8 characters.

                    </small>


                </div>


                <!-- Confirm password -->

                <div class="col-lg-4">


                    <label class="form-label">

                        Confirm New Password *

                    </label>


                    <input
                        type="password"
                        name="confirm_password"
                        class="form-control"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >


                </div>


            </div>


            <div class="form-actions">


                <button
                    type="submit"
                    name="change_password"
                    class="btn btn-primary"
                >

                    <i
                        class="bi
                               bi-shield-check"
                    ></i>

                    Change Password

                </button>


            </div>


        </form>


    </div>


</main>


<?php

include("../includes/footer.php");

?>