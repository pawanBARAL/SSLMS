<?php

/*
|--------------------------------------------------------------------------
| Add Personal Calendar Event
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];

$title = "";
$description = "";
$eventDate = "";
$startTime = "";
$endTime = "";
$eventType = "personal";

$error = "";

$allowedTypes = [
    "personal",
    "appointment",
    "social",
    "other"
];


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title =
        trim($_POST["title"] ?? "");

    $description =
        trim($_POST["description"] ?? "");

    $eventDate =
        trim($_POST["event_date"] ?? "");

    $startTime =
        trim($_POST["start_time"] ?? "");

    $endTime =
        trim($_POST["end_time"] ?? "");

    $eventType =
        trim($_POST["event_type"] ?? "personal");


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($title === "") {

        $error =
            "Please enter an event title.";

    } elseif ($eventDate === "") {

        $error =
            "Please select an event date.";

    } elseif (
        !in_array(
            $eventType,
            $allowedTypes,
            true
        )
    ) {

        $error =
            "Please select a valid event type.";

    } elseif (
        $startTime === ""
        &&
        $endTime !== ""
    ) {

        $error =
            "Please select a start time if you use an end time.";

    } elseif (
        $startTime !== ""
        &&
        $endTime !== ""
        &&
        $endTime <= $startTime
    ) {

        $error =
            "The end time must be later than the start time.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Insert Event
        |--------------------------------------------------------------------------
        */

        $insertQuery =
            $pdo->prepare(
                "INSERT INTO calendar_events
                (
                    user_id,
                    title,
                    description,
                    event_date,
                    start_time,
                    end_time,
                    event_type
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )"
            );


        $insertQuery->execute([
            $userId,
            $title,
            $description !== ""
                ? $description
                : null,
            $eventDate,
            $startTime !== ""
                ? $startTime
                : null,
            $endTime !== ""
                ? $endTime
                : null,
            $eventType
        ]);


        header(
            "Location: index.php?added=1&month="
            . date(
                "Y-m",
                strtotime($eventDate)
            )
        );

        exit;
    }
}


$pageTitle = "Add Calendar Event";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">


    <div class="page-heading">

        <p class="page-kicker">
            CALENDAR
        </p>

        <h1>
            Add Personal Event
        </h1>

        <p class="text-muted mb-0">
            Add appointments, social activities
            or other personal plans.
        </p>

    </div>


    <div class="row">

        <div class="col-xl-8 col-lg-10">


            <div class="content-card">


                <div class="content-card-header">

                    <div>

                        <h3>
                            Event Details
                        </h3>

                        <small class="text-muted">
                            Add something to your personal schedule.
                        </small>

                    </div>

                    <div class="stat-icon">
                        <i class="bi bi-calendar-plus"></i>
                    </div>

                </div>


                <?php if ($error !== ""): ?>

                    <div class="alert alert-danger">

                        <i class="bi bi-exclamation-circle me-2"></i>

                        <?php
                        echo htmlspecialchars(
                            $error
                        );
                        ?>

                    </div>

                <?php endif; ?>


                <form method="POST">
                    <?= csrf_field() ?>


                    <div class="row g-3">


                        <div class="col-12">

                            <label
                                for="title"
                                class="form-label"
                            >
                                Event Title *
                            </label>

                            <input
                                type="text"
                                id="title"
                                name="title"
                                class="form-control"
                                maxlength="200"
                                value="<?php
                                echo htmlspecialchars(
                                    $title
                                );
                                ?>"
                                placeholder="e.g. Doctor appointment"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label
                                for="event_type"
                                class="form-label"
                            >
                                Event Type *
                            </label>

                            <select
                                id="event_type"
                                name="event_type"
                                class="form-select"
                                required
                            >

                                <option
                                    value="personal"
                                    <?php
                                    echo
                                        $eventType === "personal"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Personal
                                </option>

                                <option
                                    value="appointment"
                                    <?php
                                    echo
                                        $eventType === "appointment"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Appointment
                                </option>

                                <option
                                    value="social"
                                    <?php
                                    echo
                                        $eventType === "social"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Social
                                </option>

                                <option
                                    value="other"
                                    <?php
                                    echo
                                        $eventType === "other"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Other
                                </option>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label
                                for="event_date"
                                class="form-label"
                            >
                                Date *
                            </label>

                            <input
                                type="date"
                                id="event_date"
                                name="event_date"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $eventDate
                                );
                                ?>"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label
                                for="start_time"
                                class="form-label"
                            >
                                Start Time
                            </label>

                            <input
                                type="time"
                                id="start_time"
                                name="start_time"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $startTime
                                );
                                ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label
                                for="end_time"
                                class="form-label"
                            >
                                End Time
                            </label>

                            <input
                                type="time"
                                id="end_time"
                                name="end_time"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $endTime
                                );
                                ?>"
                            >

                        </div>


                        <div class="col-12">

                            <label
                                for="description"
                                class="form-label"
                            >
                                Description
                            </label>

                            <textarea
                                id="description"
                                name="description"
                                class="form-control"
                                rows="5"
                                placeholder="Optional notes..."
                            ><?php
                            echo htmlspecialchars(
                                $description
                            );
                            ?></textarea>

                        </div>


                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-check-lg me-1"></i>
                            Save Event
                        </button>

                        <a
                            href="index.php"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </a>

                    </div>


                </form>


            </div>


        </div>

    </div>


</main>


<?php
require_once("../includes/footer.php");
?>