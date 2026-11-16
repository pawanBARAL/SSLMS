<?php

/*
|--------------------------------------------------------------------------
| Edit Work Shift
|--------------------------------------------------------------------------
| Allows the logged-in student to update one of their own work shifts.
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Validate Shift ID
|--------------------------------------------------------------------------
*/

$shiftId =
    filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );

if (!$shiftId) {

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Retrieve Shift
|--------------------------------------------------------------------------
*/

$findQuery =
    $pdo->prepare(
        "SELECT *
         FROM work_shifts

         WHERE id = ?
         AND user_id = ?

         LIMIT 1"
    );

$findQuery->execute([
    $shiftId,
    $userId
]);

$shift =
    $findQuery->fetch();


if (!$shift) {

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Current Values
|--------------------------------------------------------------------------
*/

$employer =
    $shift["employer"];

$jobTitle =
    $shift["job_title"];

$shiftDate =
    $shift["shift_date"];

$startTime =
    substr(
        $shift["start_time"],
        0,
        5
    );

$endTime =
    substr(
        $shift["end_time"],
        0,
        5
    );

$hourlyRate =
    $shift["hourly_rate"];

$notes =
    $shift["notes"]
    ?? "";

$error = "";


/*
|--------------------------------------------------------------------------
| Process Update
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $employer =
        trim($_POST["employer"] ?? "");

    $jobTitle =
        trim($_POST["job_title"] ?? "");

    $shiftDate =
        trim($_POST["shift_date"] ?? "");

    $startTime =
        trim($_POST["start_time"] ?? "");

    $endTime =
        trim($_POST["end_time"] ?? "");

    $hourlyRate =
        trim($_POST["hourly_rate"] ?? "");

    $notes =
        trim($_POST["notes"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($employer === "") {

        $error =
            "Please enter the employer name.";

    } elseif ($jobTitle === "") {

        $error =
            "Please enter your job title.";

    } elseif ($shiftDate === "") {

        $error =
            "Please select the shift date.";

    } elseif ($startTime === "") {

        $error =
            "Please select the start time.";

    } elseif ($endTime === "") {

        $error =
            "Please select the end time.";

    } elseif ($startTime === $endTime) {
        $error = "Start and end time cannot be the same. Use an earlier end time for an overnight shift.";
    } elseif (
        $hourlyRate === ""
        ||
        !is_numeric($hourlyRate)
        ||
        (float) $hourlyRate < 0
    ) {

        $error =
            "Please enter a valid hourly rate.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Update Database
        |--------------------------------------------------------------------------
        */

        $updateQuery =
            $pdo->prepare(
                "UPDATE work_shifts

                 SET
                    employer = ?,
                    job_title = ?,
                    shift_date = ?,
                    start_time = ?,
                    end_time = ?,
                    hourly_rate = ?,
                    notes = ?

                 WHERE id = ?
                 AND user_id = ?"
            );


        $updateQuery->execute([
            $employer,
            $jobTitle,
            $shiftDate,
            $startTime,
            $endTime,
            (float) $hourlyRate,
            $notes !== ""
                ? $notes
                : null,
            $shiftId,
            $userId
        ]);


        header(
            "Location: index.php?updated=1"
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Edit Work Shift";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">


    <div class="page-heading">

        <p class="page-kicker">
            WORK MANAGEMENT
        </p>

        <h1>
            Edit Work Shift
        </h1>

        <p class="text-muted mb-0">
            Update your work schedule, role or hourly rate.
        </p>

    </div>


    <div class="row">

        <div class="col-xl-8 col-lg-10">


            <div class="content-card">


                <div class="content-card-header">

                    <div>

                        <h3>
                            Shift Details
                        </h3>

                        <small class="text-muted">
                            Modify the work information below.
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


                        <div class="col-md-6">

                            <label
                                for="employer"
                                class="form-label"
                            >
                                Employer *
                            </label>

                            <input
                                type="text"
                                id="employer"
                                name="employer"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $employer
                                );
                                ?>"
                                maxlength="150"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label
                                for="job_title"
                                class="form-label"
                            >
                                Job Title *
                            </label>

                            <input
                                type="text"
                                id="job_title"
                                name="job_title"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $jobTitle
                                );
                                ?>"
                                maxlength="150"
                                required
                            >

                        </div>


                        <div class="col-md-4">

                            <label
                                for="shift_date"
                                class="form-label"
                            >
                                Shift Date *
                            </label>

                            <input
                                type="date"
                                id="shift_date"
                                name="shift_date"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $shiftDate
                                );
                                ?>"
                                required
                            >

                        </div>


                        <div class="col-md-4">

                            <label
                                for="start_time"
                                class="form-label"
                            >
                                Start Time *
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
                                required
                            >

                        </div>


                        <div class="col-md-4">

                            <label
                                for="end_time"
                                class="form-label"
                            >
                                End Time *
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
                                required
                            >

                        </div>


                        <div class="col-md-5">

                            <label
                                for="hourly_rate"
                                class="form-label"
                            >
                                Hourly Rate *
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    £
                                </span>

                                <input
                                    type="number"
                                    id="hourly_rate"
                                    name="hourly_rate"
                                    class="form-control"
                                    value="<?php
                                    echo htmlspecialchars(
                                        (string) $hourlyRate
                                    );
                                    ?>"
                                    min="0"
                                    step="0.01"
                                    required
                                >

                            </div>

                        </div>


                        <div class="col-12">

                            <label
                                for="notes"
                                class="form-label"
                            >
                                Notes
                            </label>

                            <textarea
                                id="notes"
                                name="notes"
                                class="form-control"
                                rows="5"
                            ><?php
                            echo htmlspecialchars(
                                $notes
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