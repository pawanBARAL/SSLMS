<?php

/*
|--------------------------------------------------------------------------
| Add Reminder
|--------------------------------------------------------------------------
|
| Allows the logged-in student to create a new reminder.
|
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = $_SESSION["user_id"];

$error = "";

$title = "";
$description = "";
$reminderDate = date("Y-m-d");
$reminderTime = "";
$category = "Personal";
$priority = "medium";


/*
|--------------------------------------------------------------------------
| Reminder Categories
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


/*
|--------------------------------------------------------------------------
| Priority Options
|--------------------------------------------------------------------------
*/

$priorities = [
    "low",
    "medium",
    "high"
];


/*
|--------------------------------------------------------------------------
| Process Form Submission
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


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $title === "" ||
        $reminderDate === "" ||
        $category === "" ||
        $priority === ""
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
            "Please select a valid reminder category.";

    } elseif (
        !in_array(
            $priority,
            $priorities,
            true
        )
    ) {

        $error =
            "Please select a valid priority level.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Insert Reminder
        |--------------------------------------------------------------------------
        */

        $insertReminder = $pdo->prepare(
            "INSERT INTO reminders
            (
                user_id,
                title,
                description,
                reminder_date,
                reminder_time,
                category,
                priority,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'pending'
            )"
        );


        $insertReminder->execute([

            $userId,

            $title,

            $description !== ""
                ? $description
                : null,

            $reminderDate,

            $reminderTime !== ""
                ? $reminderTime
                : null,

            $category,

            $priority
        ]);


        header(
            "Location: index.php?added=1"
        );

        exit;
    }
}


$pageTitle = "Add Reminder";

include("../includes/header.php");
include("../includes/sidebar.php");

?>


<main class="main-content">


    <div class="page-heading">

        <p class="page-kicker">
            Personal Organisation
        </p>

        <h1>
            Add Reminder
        </h1>

        <p class="text-muted">
            Create a reminder for an important
            task, appointment or commitment.
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
                        value="<?php
                        echo htmlspecialchars(
                            $title
                        );
                        ?>"
                        placeholder="Example: Submit coursework"
                        maxlength="200"
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
                                $category === $categoryOption
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
                            $reminderDate
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
                        echo htmlspecialchars(
                            $reminderTime
                        );
                        ?>"
                    >

                    <small class="text-muted">
                        Optional
                    </small>

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

                        <option
                            value="low"
                            <?php
                            echo $priority === "low"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Low
                        </option>

                        <option
                            value="medium"
                            <?php
                            echo $priority === "medium"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Medium
                        </option>

                        <option
                            value="high"
                            <?php
                            echo $priority === "high"
                                ? "selected"
                                : "";
                            ?>
                        >
                            High
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
                        placeholder="Add any useful details about this reminder..."
                    ><?php
                    echo htmlspecialchars(
                        $description
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

                    <i class="bi bi-plus-circle"></i>

                    Add Reminder

                </button>

            </div>


        </form>


    </div>


</main>


<?php
include("../includes/footer.php");
?>