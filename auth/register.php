<?php

/*
|--------------------------------------------------------------------------
| Student Registration
|--------------------------------------------------------------------------
|
| Creates a new SSLMS student account.
|
| Security features:
| - Server-side validation
| - Email validation
| - Duplicate email protection
| - Secure password hashing
| - Prepared SQL statements
|
|--------------------------------------------------------------------------
*/

session_start();
require_once __DIR__ . '/../includes/csrf.php';
csrf_require_valid_request();

require_once("../config/db.php");


/*
|--------------------------------------------------------------------------
| Redirect Already Logged-In Users
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
| Default Form Values
|--------------------------------------------------------------------------
*/

$fullName = "";
$email = "";

$error = "";


/*
|--------------------------------------------------------------------------
| Process Registration
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |--------------------------------------------------------------------------
    | Read Form Information
    |--------------------------------------------------------------------------
    */

    $fullName =
        trim(
            $_POST["full_name"]
            ?? ""
        );

    $email =
        trim(
            $_POST["email"]
            ?? ""
        );

    $password =
        $_POST["password"]
        ?? "";

    $confirmPassword =
        $_POST["confirm_password"]
        ?? "";


    /*
    |--------------------------------------------------------------------------
    | Validate Required Fields
    |--------------------------------------------------------------------------
    */

    if (
        $fullName === ""
        ||
        $email === ""
        ||
        $password === ""
        ||
        $confirmPassword === ""
    ) {

        $error =
            "Please complete all required fields.";

    } elseif (
        strlen($fullName) < 2
    ) {

        $error =
            "Please enter your full name.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email address.";

    } elseif (
        strlen($password) < 8
    ) {

        $error =
            "Your password must contain at least 8 characters.";

    } elseif (
        $password !==
        $confirmPassword
    ) {

        $error =
            "The passwords do not match.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Normalize Email
        |--------------------------------------------------------------------------
        */

        $email =
            strtolower(
                $email
            );


        /*
        |--------------------------------------------------------------------------
        | Check Existing Account
        |--------------------------------------------------------------------------
        */

        $checkUser = $pdo->prepare(
            "SELECT id
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $checkUser->execute([
            $email
        ]);


        if ($checkUser->fetch()) {

            $error =
                "An account already exists with that email address.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | Hash Password
            |--------------------------------------------------------------------------
            |
            | Never store plain-text passwords in the database.
            |
            */

            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            /*
            |--------------------------------------------------------------------------
            | Create Account
            |--------------------------------------------------------------------------
            */

            $createUser = $pdo->prepare(
                "INSERT INTO users
                (
                    full_name,
                    email,
                    password
                )
                VALUES
                (
                    ?,
                    ?,
                    ?
                )"
            );


            try {

                $createUser->execute([
                    $fullName,
                    $email,
                    $passwordHash
                ]);


                /*
                |--------------------------------------------------------------------------
                | Redirect to Login
                |--------------------------------------------------------------------------
                */

                header(
                    "Location: login.php?registered=1"
                );

                exit;

            } catch (PDOException $exception) {


                /*
                |--------------------------------------------------------------------------
                | Handle Duplicate Email Race Condition
                |--------------------------------------------------------------------------
                */

                if (
                    $exception->getCode()
                    ===
                    "23000"
                ) {

                    $error =
                        "An account already exists with that email address.";

                } else {

                    throw $exception;
                }
            }
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
        content="Create your SSLMS student account"
    >

    <title>
        Register | SSLMS
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
         LEFT INFORMATION PANEL
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

                Organise university life in one place.

            </h1>


            <p>

                Manage academic deadlines, finances,
                work shifts, reminders and your schedule
                through one integrated student dashboard.

            </p>


            <div class="auth-feature-list">


                <div class="auth-feature">

                    <i class="bi bi-check-circle-fill"></i>

                    <span>

                        Manage academic deadlines

                    </span>

                </div>


                <div class="auth-feature">

                    <i class="bi bi-check-circle-fill"></i>

                    <span>

                        Track income and expenses

                    </span>

                </div>


                <div class="auth-feature">

                    <i class="bi bi-check-circle-fill"></i>

                    <span>

                        Organise work and reminders

                    </span>

                </div>


                <div class="auth-feature">

                    <i class="bi bi-check-circle-fill"></i>

                    <span>

                        View everything in one calendar

                    </span>

                </div>


            </div>


        </div>


    </div>


    <!-- ================================================================
         REGISTRATION FORM
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

                GET STARTED

            </p>


            <h2>

                Create your account

            </h2>


            <p class="text-muted">

                Create an account to start managing
                your student life.

            </p>


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


                <!-- Full name -->

                <div class="mb-3">


                    <label
                        for="full_name"
                        class="form-label"
                    >

                        Full Name

                    </label>


                    <div class="input-group">


                        <span class="input-group-text">

                            <i class="bi bi-person"></i>

                        </span>


                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            class="form-control"
                            value="<?php
                            echo htmlspecialchars(
                                $fullName
                            );
                            ?>"
                            maxlength="150"
                            placeholder="Enter your full name"
                            autocomplete="name"
                            required
                        >


                    </div>


                </div>


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
                            required
                        >


                    </div>


                </div>


                <!-- Password -->

                <div class="mb-3">


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
                            minlength="8"
                            placeholder="Minimum 8 characters"
                            autocomplete="new-password"
                            required
                        >


                        <button
                            type="button"
                            class="btn
                                   btn-outline-secondary
                                   password-toggle"
                            data-password-target="password"
                            aria-label="Show password"
                        >

                            <i class="bi bi-eye"></i>

                        </button>


                    </div>


                </div>


                <!-- Confirm password -->

                <div class="mb-4">


                    <label
                        for="confirm_password"
                        class="form-label"
                    >

                        Confirm Password

                    </label>


                    <div class="input-group">


                        <span class="input-group-text">

                            <i class="bi bi-shield-check"></i>

                        </span>


                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-control"
                            minlength="8"
                            placeholder="Enter password again"
                            autocomplete="new-password"
                            required
                        >


                        <button
                            type="button"
                            class="btn
                                   btn-outline-secondary
                                   password-toggle"
                            data-password-target="confirm_password"
                            aria-label="Show password"
                        >

                            <i class="bi bi-eye"></i>

                        </button>


                    </div>


                </div>


                <!-- Register button -->

                <button
                    type="submit"
                    class="btn
                           btn-primary
                           w-100
                           auth-submit-button"
                >

                    <i class="bi bi-person-plus"></i>

                    Create Account

                </button>


            </form>


            <!-- Login -->

            <div class="auth-switch">


                <span>

                    Already have an account?

                </span>


                <a href="login.php">

                    Sign in

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

document.querySelectorAll(
    ".password-toggle"
).forEach(

    function (button) {

        button.addEventListener(
            "click",
            function () {

                const targetId =
                    this.getAttribute(
                        "data-password-target"
                    );

                const input =
                    document.getElementById(
                        targetId
                    );

                const icon =
                    this.querySelector("i");


                if (
                    input.type ===
                    "password"
                ) {

                    input.type = "text";

                    icon.classList.remove(
                        "bi-eye"
                    );

                    icon.classList.add(
                        "bi-eye-slash"
                    );

                    this.setAttribute(
                        "aria-label",
                        "Hide password"
                    );

                } else {

                    input.type = "password";

                    icon.classList.remove(
                        "bi-eye-slash"
                    );

                    icon.classList.add(
                        "bi-eye"
                    );

                    this.setAttribute(
                        "aria-label",
                        "Show password"
                    );
                }
            }
        );
    }
);

</script>


</body>

</html>