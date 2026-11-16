<?php

/*
|--------------------------------------------------------------------------
| Student Login
|--------------------------------------------------------------------------
|
| Authenticates an SSLMS student using their email address and password.
|
| Security features:
| - Prepared statements
| - password_verify()
| - Session ID regeneration
| - Generic invalid-login message
|
|--------------------------------------------------------------------------
*/

session_start();
require_once __DIR__ . '/../includes/csrf.php';
csrf_require_valid_request();

require_once("../config/db.php");


/*
|--------------------------------------------------------------------------
| Redirect Logged-In Users
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["user_id"])) {

    header(
        "Location: ../index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Default Values
|--------------------------------------------------------------------------
*/

$email = "";

$error = "";

$successMessage = "";


/*
|--------------------------------------------------------------------------
| Registration Success Message
|--------------------------------------------------------------------------
*/

if (
    isset($_GET["registered"])
    &&
    $_GET["registered"] === "1"
) {

    $successMessage =
        "Your account has been created successfully. You can now sign in.";
}


/*
|--------------------------------------------------------------------------
| Logged-Out Message
|--------------------------------------------------------------------------
*/

if (
    isset($_GET["logged_out"])
    &&
    $_GET["logged_out"] === "1"
) {

    $successMessage =
        "You have been signed out successfully.";
}


/*
|--------------------------------------------------------------------------
| Process Login
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |--------------------------------------------------------------------------
    | Read Login Information
    |--------------------------------------------------------------------------
    */

    $email =
        strtolower(
            trim(
                $_POST["email"]
                ?? ""
            )
        );

    $password =
        $_POST["password"]
        ?? "";


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $email === ""
        ||
        $password === ""
    ) {

        $error =
            "Please enter your email address and password.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email address.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Find User
        |--------------------------------------------------------------------------
        */

        $findUser = $pdo->prepare(
            "SELECT
                id,
                full_name,
                email,
                password

             FROM users

             WHERE email = ?

             LIMIT 1"
        );


        $findUser->execute([
            $email
        ]);


        $user =
            $findUser->fetch();


        /*
        |--------------------------------------------------------------------------
        | Verify Password
        |--------------------------------------------------------------------------
        */

        if (
            $user
            &&
            password_verify(
                $password,
                $user["password"]
            )
        ) {


            /*
            |--------------------------------------------------------------------------
            | Prevent Session Fixation
            |--------------------------------------------------------------------------
            */

            session_regenerate_id(
                true
            );


            /*
            |--------------------------------------------------------------------------
            | Store Login Session
            |--------------------------------------------------------------------------
            */

            $_SESSION["user_id"] =
                (int) $user["id"];

            $_SESSION["full_name"] =
                $user["full_name"];

            $_SESSION["email"] =
                $user["email"];


            /*
            |--------------------------------------------------------------------------
            | Redirect to Main Dashboard
            |--------------------------------------------------------------------------
            */

            header(
                "Location: ../index.php"
            );

            exit;

        } else {


            /*
            |--------------------------------------------------------------------------
            | Generic Login Error
            |--------------------------------------------------------------------------
            |
            | We deliberately do not tell the visitor whether the email or
            | password was incorrect.
            |
            */

            $error =
                "The email address or password is incorrect.";
        }
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
        content="Sign in to the Student Smart Life Management System"
    >

    <title>
        Login | SSLMS
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


    <!-- SSLMS CSS -->

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body class="auth-body">


<div class="auth-wrapper">


    <!-- ================================================================
         BRAND PANEL
         ================================================================ -->

    <div class="auth-brand-panel">


        <div class="auth-brand-content">


            <div class="auth-logo">

                <i class="bi bi-mortarboard-fill"></i>

            </div>


            <p class="page-kicker">

                STUDENT SMART LIFE MANAGEMENT SYSTEM

            </p>


            <h1>

                One dashboard for your student life.

            </h1>


            <p>

                Stay organised across university,
                money, work and personal commitments
                without switching between multiple tools.

            </p>


            <div class="auth-feature-list">


                <div class="auth-feature">

                    <i class="bi bi-mortarboard"></i>

                    <span>

                        Academic planning

                    </span>

                </div>


                <div class="auth-feature">

                    <i class="bi bi-wallet2"></i>

                    <span>

                        Financial management

                    </span>

                </div>


                <div class="auth-feature">

                    <i class="bi bi-briefcase"></i>

                    <span>

                        Work scheduling

                    </span>

                </div>


                <div class="auth-feature">

                    <i class="bi bi-calendar3"></i>

                    <span>

                        Integrated calendar

                    </span>

                </div>


            </div>


        </div>


    </div>


    <!-- ================================================================
         LOGIN FORM
         ================================================================ -->

    <div class="auth-form-panel">


        <div class="auth-form-card">


            <div class="auth-mobile-logo">

                <i class="bi bi-mortarboard-fill"></i>

                <strong>
                    SSLMS
                </strong>

            </div>


            <p class="page-kicker">

                WELCOME BACK

            </p>


            <h2>

                Sign in to SSLMS

            </h2>


            <p class="text-muted">

                Enter your account details to
                continue to your dashboard.

            </p>


            <!-- Success message -->

            <?php if (
                $successMessage !== ""
            ): ?>

                <div
                    class="alert alert-success"
                    role="alert"
                >

                    <i
                        class="bi
                               bi-check-circle
                               me-2"
                    ></i>

                    <?php
                    echo htmlspecialchars(
                        $successMessage
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- Error message -->

            <?php if ($error !== ""): ?>

                <div
                    class="alert alert-danger"
                    role="alert"
                >

                    <i
                        class="bi
                               bi-exclamation-circle
                               me-2"
                    ></i>

                    <?php
                    echo htmlspecialchars(
                        $error
                    );
                    ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
            >
                    <?= csrf_field() ?>


                <!-- Email -->

                <div class="mb-3">


                    <label
                        for="email"
                        class="form-label"
                    >

                        Email Address

                    </label>


                    <div class="input-group">


                        <span class="input-group-text">

                            <i class="bi bi-envelope"></i>

                        </span>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="<?php
                            echo htmlspecialchars(
                                $email
                            );
                            ?>"
                            maxlength="190"
                            placeholder="student@example.com"
                            autocomplete="email"
                            autofocus
                            required
                        >


                    </div>


                </div>


                <!-- Password -->

                <div class="mb-4">


                    <label
                        for="password"
                        class="form-label"
                    >

                        Password

                    </label>


                    <div class="input-group">


                        <span class="input-group-text">

                            <i class="bi bi-lock"></i>

                        </span>


                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="btn
                                   btn-outline-secondary
                                   password-toggle"
                            id="passwordToggle"
                            aria-label="Show password"
                        >

                            <i
                                class="bi bi-eye"
                                id="passwordIcon"
                            ></i>

                        </button>


                    </div>


                </div>


                <!-- Login button -->

                <button
                    type="submit"
                    class="btn
                           btn-primary
                           w-100
                           auth-submit-button"
                >

                    <i
                        class="bi
                               bi-box-arrow-in-right"
                    ></i>

                    Sign In

                </button>


            </form>


            <!-- Register -->

            <div class="auth-switch">


                <span>

                    Don't have an account?

                </span>


                <a href="register.php">

                    Create account

                </a>


            </div>


        </div>


    </div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| Password Visibility
|--------------------------------------------------------------------------
*/

const passwordToggle =
    document.getElementById(
        "passwordToggle"
    );

const passwordInput =
    document.getElementById(
        "password"
    );

const passwordIcon =
    document.getElementById(
        "passwordIcon"
    );


if (
    passwordToggle &&
    passwordInput &&
    passwordIcon
) {

    passwordToggle.addEventListener(
        "click",
        function () {

            if (
                passwordInput.type
                ===
                "password"
            ) {

                passwordInput.type =
                    "text";

                passwordIcon.classList.remove(
                    "bi-eye"
                );

                passwordIcon.classList.add(
                    "bi-eye-slash"
                );

                passwordToggle.setAttribute(
                    "aria-label",
                    "Hide password"
                );

            } else {

                passwordInput.type =
                    "password";

                passwordIcon.classList.remove(
                    "bi-eye-slash"
                );

                passwordIcon.classList.add(
                    "bi-eye"
                );

                passwordToggle.setAttribute(
                    "aria-label",
                    "Show password"
                );
            }
        }
    );
}

</script>


</body>

</html>