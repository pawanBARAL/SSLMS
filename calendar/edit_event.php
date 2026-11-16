<?php

/*
|--------------------------------------------------------------------------
| Edit Personal Calendar Event
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];

$eventId =
    filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );

if (!$eventId) {

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Retrieve Event
|--------------------------------------------------------------------------
*/

$findQuery =
    $pdo->prepare(
        "SELECT *
         FROM calendar_events

         WHERE id = ?
         AND user_id = ?

         LIMIT 1"
    );

$findQuery->execute([
    $eventId,
    $userId
]);

$event =
    $findQuery->fetch();


if (!$event) {

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Current Values
|--------------------------------------------------------------------------
*/

$title =
    $event["title"];

$description =
    $event["description"]
    ?? "";

$eventDate =
    $event["event_date"];

$startTime =
    !empty($event["start_time"])
    ? substr(
        $event["start_time"],
        0,
        5
    )
    : "";

$endTime =
    !empty($event["end_time"])
    ? substr(
        $event["end_time"],
        0,
        5
    )
    : "";

$eventType =
    $event["event_type"];

$error = "";

$allowedTypes = [
    "personal",
    "appointment",
    "social",
    "other"
];


/*
|--------------------------------------------------------------------------
| Update Event
|--------------------------------------------------------------------------
*/

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
        trim($_POST["event_type"] ?? "");


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


        $updateQuery =
            $pdo->prepare(
                "UPDATE calendar_events

                 SET
                    title = ?,
                    description = ?,
                    event_date = ?,
                    start_time = ?,
                    end_time = ?,
                    event_type = ?

                 WHERE id = ?
                 AND user_id = ?"
            );


        $updateQuery->execute([
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
            $eventType,
            $eventId,
            $userId
        ]);


        header(
            "Location: index.php?updated=1&month="
            . date(
                "Y-m",
                strtotime($eventDate)
            )
        );

        exit;
    }
}


$pageTitle = "Edit Calendar Event";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">


    <div class="page-heading">

        <p class="page-kicker">
            CALENDAR
        </p>

        <h1>
            Edit Personal Event
        </h1>

        <p class="text-muted mb-0">
            Update your personal calendar event.
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
                            Modify the information below.
                        </small>

                    </div>

                    <div class="stat-icon">
                        <i class="bi bi-pencil-square"></i>
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

                                <?php

                                $typeLabels = [
                                    "personal" =>
                                        "Personal",

                                    "appointment" =>
                                        "Appointment",

                                    "social" =>
                                        "Social",

                                    "other" =>
                                        "Other"
                                ];

                                ?>

                                <?php foreach ($typeLabels as $value => $label): ?>

                                    <option
                                        value="<?php
                                        echo htmlspecialchars(
                                            $value
                                        );
                                        ?>"
                                        <?php
                                        echo
                                            $eventType === $value
                                            ? "selected"
                                            : "";
                                        ?>
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $label
                                        );
                                        ?>

                                    </option>

                                <?php endforeach; ?>

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
                            Save Changes
                        </button>

                        <a
                            href="index.php?month=<?php
                            echo htmlspecialchars(
                                substr(
                                    $eventDate,
                                    0,
                                    7
                                )
                            );
                            ?>"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </a>

                        <a
                            href="delete_event.php?id=<?php
                            echo (int) $eventId;
                            ?>"
                            class="btn btn-outline-danger ms-auto"
                        >
                            <i class="bi bi-trash me-1"></i>
                            Delete
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