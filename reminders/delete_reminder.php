<?php

/*
|--------------------------------------------------------------------------
| Delete Reminder
|--------------------------------------------------------------------------
|
| Deletes a reminder belonging to the currently logged-in student.
|
| The action only accepts POST requests.
| user_id is included in the DELETE query to prevent one user from
| deleting another user's reminders.
|
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Only Accept POST Requests
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Reminder ID
|--------------------------------------------------------------------------
*/

$reminderId =
    filter_input(
        INPUT_POST,
        "reminder_id",
        FILTER_VALIDATE_INT
    );


if (!$reminderId) {

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Delete Reminder
|--------------------------------------------------------------------------
*/

$deleteReminder = $pdo->prepare(
    "DELETE FROM reminders
     WHERE id = ?
     AND user_id = ?"
);


$deleteReminder->execute([
    $reminderId,
    $userId
]);


/*
|--------------------------------------------------------------------------
| Return to Reminder Dashboard
|--------------------------------------------------------------------------
*/

header(
    "Location: index.php?deleted=1"
);

exit;