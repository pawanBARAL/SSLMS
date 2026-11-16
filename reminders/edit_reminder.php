<?php

/*
|--------------------------------------------------------------------------
| Edit Reminder
|--------------------------------------------------------------------------
|
| Allows the logged-in student to edit one of their own reminders.
|
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Available Options
|--------------------------------------------------------------------------
*/

$categories = [
    "Academic",
    "Work",
    "Finance",
    "Personal",
    "Appointment",
    "Other"
];

$priorities = [
    "low",
    "medium",
    "high"
];


/*
|--------------------------------------------------------------------------
| Validate Reminder ID
|--------------------------------------------------------------------------
*/

$reminderId =
    filter_input(
        INPUT_GET,
        "id",
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
| Retrieve Reminder
|--------------------------------------------------------------------------
|
| user_id is included to ensure the student can only edit their own
| reminder.
|
*/

$getReminder = $pdo->prepare(
    "SELECT *
     FROM reminders
     WHERE id = ?
     AND user_id = ?"
);


$getReminder->execute([
    $reminderId,
    $userId
]);


$reminder =
    $getReminder->fetch();


if (!$reminder) {

    header(
        "Location: index.php"
    );

    exit;
}


$error = "";


/*
|--------------------------------------------------------------------------
| Process Update
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title =
        trim(
            $_POST["title"] ?? ""
        );

    $description =
        trim(
            $_POST["description"] ?? ""
        );

    $reminderDate =
        trim(
            $_POST["reminder_date"] ?? ""
        );

    $reminderTime =
        trim(
            $_POST["reminder_time"] ?? ""
        );

    $category =
        trim(
            $_POST["category"] ?? ""
        );

    $priority =
        trim(
            $_POST["priority"] ?? ""
        );

    $status =
        trim(
            $_POST["status"] ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $title === "" ||
        $reminderDate === "" ||
        $category === "" ||
        $priority === "" ||
        $status === ""
    ) {

        $error =
            "Please complete all required fields.";

    } elseif (
        !in_array(
            $category,
            $categories,
            true
        )
    ) {

        $error =
            "Please select a valid category.";

    } elseif (
        !in_array(
            $priority,
            $priorities,
            true
        )
    ) {

        $error =
            "Please select a valid priority.";

    } elseif (
        !in_array(
            $status,
            ["pending", "completed"],
            true
        )
    ) {

        $error =
            "Please select a valid reminder status.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Update Reminder
        |--------------------------------------------------------------------------
        */

        $updateReminder = $pdo->prepare(
            "UPDATE reminders
             SET
                title = ?,
                description = ?,
                reminder_date = ?,
                reminder_time = ?,
                category = ?,
                priority = ?,
                status = ?
             WHERE id = ?
             AND user_id = ?"
        );


        $updateReminder->execute([

            $title,

            $description !== ""
                ? $description
                : null,

            $reminderDate,

            $reminderTime !== ""
                ? $reminderTime
                : null,

            $category,

            $priority,

            $status,

            $reminderId,

            $userId
        ]);


        header(
            "Location: index.php?updated=1"
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Keep Submitted Values
    |--------------------------------------------------------------------------
    */

    $reminder["title"] =
        $title;

    $reminder["description"] =
        $description;

    $reminder["reminder_date"] =
        $reminderDate;

    $reminder["reminder_time"] =
        $reminderTime;

    $reminder["category"] =
        $category;

    $reminder["priority"] =
        $priority;

    $reminder["status"] =
        $status;
}


$pageTitle = "Edit Reminder";

include("../includes/header.php");
include("../includes/sidebar.php");

?>


<main class="main-content">


    <div class="page-heading">

        <p class="page-kicker">
            Personal Organisation
        </p>

        <h1>
            Edit Reminder
        </h1>

        <p class="text-muted">
            Update the reminder information,
            priority or completion status.
        </p>

    </div>


    <div class="content-card form-card">


        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                <?php
                echo htmlspecialchars(
                    $error
                );
                ?>

            </div>

        <?php endif; ?>


        <form method="POST">
                    <?= csrf_field() ?>


            <div class="row g-4">


                <!-- Title -->

                <div class="col-md-8">

                    <label class="form-label">
                        Reminder Title *
                    </label>

                    <input
                        type="text"
                        name="title"
                        class="form-control"
                        maxlength="200"
                        value="<?php
                        echo htmlspecialchars(
                            $reminder["title"]
                        );
                        ?>"
                        required
                    >

                </div>


                <!-- Category -->

                <div class="col-md-4">

                    <label class="form-label">
                        Category *
                    </label>

                    <select
                        name="category"
                        class="form-select"
                        required
                    >

                        <?php foreach (
                            $categories
                            as
                            $categoryOption
                        ): ?>

                            <option
                                value="<?php
                                echo htmlspecialchars(
                                    $categoryOption
                                );
                                ?>"
                                <?php
                                echo
                                $reminder["category"]
                                ===
                                $categoryOption
                                ?
                                "selected"
                                :
                                "";
                                ?>
                            >

                                <?php
                                echo htmlspecialchars(
                                    $categoryOption
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Date -->

                <div class="col-md-4">

                    <label class="form-label">
                        Reminder Date *
                    </label>

                    <input
                        type="date"
                        name="reminder_date"
                        class="form-control"
                        value="<?php
                        echo htmlspecialchars(
                            $reminder[
                                "reminder_date"
                            ]
                        );
                        ?>"
                        required
                    >

                </div>


                <!-- Time -->

                <div class="col-md-4">

                    <label class="form-label">
                        Reminder Time
                    </label>

                    <input
                        type="time"
                        name="reminder_time"
                        class="form-control"
                        value="<?php

                        if (
                            !empty(
                                $reminder[
                                    "reminder_time"
                                ]
                            )
                        ) {

                            echo date(
                                "H:i",
                                strtotime(
                                    $reminder[
                                        "reminder_time"
                                    ]
                                )
                            );
                        }

                        ?>"
                    >

                </div>


                <!-- Priority -->

                <div class="col-md-4">

                    <label class="form-label">
                        Priority *
                    </label>

                    <select
                        name="priority"
                        class="form-select"
                        required
                    >

                        <?php foreach (
                            $priorities
                            as
                            $priorityOption
                        ): ?>

                            <option
                                value="<?php
                                echo $priorityOption;
                                ?>"
                                <?php
                                echo
                                $reminder["priority"]
                                ===
                                $priorityOption
                                ?
                                "selected"
                                :
                                "";
                                ?>
                            >

                                <?php
                                echo ucfirst(
                                    $priorityOption
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Status -->

                <div class="col-md-4">

                    <label class="form-label">
                        Status *
                    </label>

                    <select
                        name="status"
                        class="form-select"
                        required
                    >

                        <option
                            value="pending"
                            <?php
                            echo
                            $reminder["status"]
                            ===
                            "pending"
                            ?
                            "selected"
                            :
                            "";
                            ?>
                        >
                            Pending
                        </option>

                        <option
                            value="completed"
                            <?php
                            echo
                            $reminder["status"]
                            ===
                            "completed"
                            ?
                            "selected"
                            :
                            "";
                            ?>
                        >
                            Completed
                        </option>

                    </select>

                </div>


                <!-- Description -->

                <div class="col-12">

                    <label class="form-label">
                        Description
                    </label>

                    <textarea
                        name="description"
                        class="form-control"
                        rows="5"
                    ><?php
                    echo htmlspecialchars(
                        $reminder[
                            "description"
                        ]
                        ?? ""
                    );
                    ?></textarea>

                </div>


            </div>


            <div class="form-actions">

                <a
                    href="index.php"
                    class="btn btn-outline-secondary"
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="bi bi-check-circle"></i>

                    Save Changes

                </button>

            </div>


        </form>


    </div>


</main>


<?php
include("../includes/footer.php");
?>