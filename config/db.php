<?php

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
|
| Creates the PDO connection used throughout SSLMS.
|
|--------------------------------------------------------------------------
*/


$host = getenv("SSLMS_DB_HOST") ?: "localhost";

$database = getenv("SSLMS_DB_NAME") ?: "sslms_db";

$username = getenv("SSLMS_DB_USER") !== false ? getenv("SSLMS_DB_USER") : "root";

$password = getenv("SSLMS_DB_PASSWORD") !== false ? getenv("SSLMS_DB_PASSWORD") : "";


/*
|--------------------------------------------------------------------------
| Data Source Name
|--------------------------------------------------------------------------
*/

$dsn =
    "mysql:host="
    . $host
    . ";dbname="
    . $database
    . ";charset=utf8mb4";


/*
|--------------------------------------------------------------------------
| PDO Configuration
|--------------------------------------------------------------------------
*/

$options = [

    /*
    |--------------------------------------------------------------------------
    | Throw Exceptions for Database Errors
    |--------------------------------------------------------------------------
    */

    PDO::ATTR_ERRMODE =>
        PDO::ERRMODE_EXCEPTION,


    /*
    |--------------------------------------------------------------------------
    | Return Query Results as Associative Arrays
    |--------------------------------------------------------------------------
    */

    PDO::ATTR_DEFAULT_FETCH_MODE =>
        PDO::FETCH_ASSOC,


    /*
    |--------------------------------------------------------------------------
    | Use Real Prepared Statements
    |--------------------------------------------------------------------------
    */

    PDO::ATTR_EMULATE_PREPARES =>
        false

];


/*
|--------------------------------------------------------------------------
| Connect to MySQL
|--------------------------------------------------------------------------
*/

try {

    $pdo =
        new PDO(
            $dsn,
            $username,
            $password,
            $options
        );

} catch (PDOException $exception) {

    /*
    |--------------------------------------------------------------------------
    | Development Error
    |--------------------------------------------------------------------------
    |
    | During development this tells us that the database connection failed.
    | A production application should log the detailed error rather than
    | display sensitive database information to the user.
    |
    */

    error_log("SSLMS database connection error: " . $exception->getMessage());
    http_response_code(503);
    exit("SSLMS could not connect to its database. Check that MySQL is running and review the database settings in config/db.php or your SSLMS_DB_* environment variables.");
}