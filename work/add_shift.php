<?php

/*
|--------------------------------------------------------------------------
| Add Work Shift
|--------------------------------------------------------------------------
| Creates a new work shift for the logged-in student.
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];

$employer = "";
$jobTitle = "";
$shiftDate = "";
$startTime = "";
$endTime = "";
$hourlyRate = "";
$notes = "";

$error = "";


/*
|--------------------------------------------------------------------------
| Process Form
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
            "Please select the shift start time.";

    } elseif ($endTime === "") {

        $error =
            "Please select the shift end time.";

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
        | Insert Shift
        |--------------------------------------------------------------------------
        */

        $insertQuery =
            $pdo->prepare(
                "INSERT INTO work_shifts
                (
                    user_id,
                    employer,
                    job_title,
                    shift_date,
                    start_time,
                    end_time,
                    hourly_rate,
                    notes
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
                    ?
                )"
            );


        $insertQuery->execute([
            $userId,
            $employer,
            $jobTitle,
            $shiftDate,
            $startTime,
            $endTime,
            (float) $hourlyRate,
            $notes !== ""
                ? $notes
                : null
        ]);


        header(
            "Location: index.php?added=1"
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Add Work Shift";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">


    <div class="page-heading">

        <p class="page-kicker">
            WORK MANAGEMENT
        </p>

        <h1>
            Add Work Shift
        </h1>

        <p class="text-muted mb-0">
            Add a work shift and track your hours and estimated earnings.
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
                            Enter your employer, schedule and hourly rate.
                        </small>

                    </div>

                    <div class="stat-icon">
                        <i class="bi bi-briefcase"></i>
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


                        <!-- Employer -->

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
                                placeholder="e.g. Tesco"
                                required
                            >

                        </div>


                        <!-- Job title -->

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
                                placeholder="e.g. Customer Assistant"
                                required
                            >

                        </div>


                        <!-- Shift date -->

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


                        <!-- Start -->

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


                        <!-- End -->

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


                        <!-- Hourly rate -->

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
                                        $hourlyRate
                                    );
                                    ?>"
                                    min="0"
                                    step="0.01"
                                    placeholder="12.50"
                                    required
                                >

                            </div>

                        </div>


                        <!-- Notes -->

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
                                placeholder="Optional shift notes..."
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
                            Save Shift
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