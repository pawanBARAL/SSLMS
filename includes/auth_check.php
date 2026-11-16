<?php

/*
|--------------------------------------------------------------------------
| Authentication Check
|--------------------------------------------------------------------------
|
| Include this file at the beginning of every page that requires the
| student to be logged in.
|
| Example:
|
| require_once("../includes/auth_check.php");
|
| or from the project root:
|
| require_once("includes/auth_check.php");
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Start Session
|--------------------------------------------------------------------------
*/

if (
    session_status()
    ===
    PHP_SESSION_NONE
) {

    session_start();
}


/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/

if (
    !isset(
        $_SESSION["user_id"]
    )
) {


    /*
    |--------------------------------------------------------------------------
    | Determine Correct Login Path
    |--------------------------------------------------------------------------
    |
    | The root dashboard needs:
    |
    | auth/login.php
    |
    | Module pages such as finance/index.php need:
    |
    | ../auth/login.php
    |
    |--------------------------------------------------------------------------
    */

    $currentScript =
        $_SERVER["SCRIPT_NAME"]
        ?? "";


    $projectRoot =
        "/sslms/";


    if (
        $currentScript
        ===
        $projectRoot
        .
        "index.php"
    ) {

        header(
            "Location: auth/login.php"
        );

    } else {

        header(
            "Location: ../auth/login.php"
        );
    }


    exit;
}


/*
|--------------------------------------------------------------------------
| Validate User ID
|--------------------------------------------------------------------------
*/

$userId =
    filter_var(
        $_SESSION["user_id"],
        FILTER_VALIDATE_INT
    );


if (!$userId) {

    $_SESSION = [];

    session_destroy();


    $currentScript =
        $_SERVER["SCRIPT_NAME"]
        ?? "";


    if (
        $currentScript
        ===
        "/sslms/index.php"
    ) {

        header(
            "Location: auth/login.php"
        );

    } else {

        header(
            "Location: ../auth/login.php"
        );
    }


    exit;
}


/*
|--------------------------------------------------------------------------
| Keep User ID Consistent
|--------------------------------------------------------------------------
*/

$_SESSION["user_id"] =
    (int) $userId;
require_once __DIR__ . '/csrf.php';
csrf_require_valid_request();
