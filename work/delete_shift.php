<?php

/*
|--------------------------------------------------------------------------
| Delete Work Shift
|--------------------------------------------------------------------------
| Displays a confirmation screen before permanently deleting a work shift.
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
        "SELECT
            id,
            employer,
            job_title,
            shift_date,
            start_time,
            end_time,
            hourly_rate

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
| Delete Shift
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedId =
        filter_input(
            INPUT_POST,
            "shift_id",
            FILTER_VALIDATE_INT
        );


    if (
        !$postedId
        ||
        $postedId !== $shiftId
    ) {

        header(
            "Location: index.php"
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Ownership Protected Delete
    |--------------------------------------------------------------------------
    */

    $deleteQuery =
        $pdo->prepare(
            "DELETE FROM work_shifts

             WHERE id = ?
             AND user_id = ?"
        );


    $deleteQuery->execute([
        $shiftId,
        $userId
    ]);


    header(
        "Location: index.php?deleted=1"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Calculate Shift Information
|--------------------------------------------------------------------------
*/

$start =
    new DateTime(
        $shift["start_time"]
    );

$end =
    new DateTime(
        $shift["end_time"]
    );

if ($end <= $start) {
    $end->modify("+1 day");
}

$hours =
    (
        $end->getTimestamp()
        -
        $start->getTimestamp()
    )
    /
    3600;

$estimatedEarnings =
    $hours
    *
    (float) $shift["hourly_rate"];


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Delete Work Shift";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">


    <div class="page-heading">

        <p class="page-kicker">
            WORK MANAGEMENT
        </p>

        <h1>
            Delete Work Shift
        </h1>

        <p class="text-muted mb-0">
            Confirm removal of this work record.
        </p>

    </div>


    <div class="row justify-content-center">

        <div class="col-xl-6 col-lg-7">


            <div class="content-card">


                <div class="text-center py-3">


                    <div
                        class="d-inline-flex
                               align-items-center
                               justify-content-center
                               rounded-circle
                               bg-danger-subtle
                               text-danger
                               mb-4"
                        style="
                            width: 72px;
                            height: 72px;
                            font-size: 28px;
                        "
                    >

                        <i class="bi bi-trash3"></i>

                    </div>


                    <h3>
                        Delete this work shift?
                    </h3>

                    <p class="text-muted">
                        This action permanently removes the shift
                        from your work records.
                    </p>


                    <div
                        class="text-start
                               border
                               rounded-3
                               p-3
                               my-4"
                    >


                        <strong class="d-block">

                            <?php
                            echo htmlspecialchars(
                                $shift["job_title"]
                            );
                            ?>

                        </strong>


                        <span class="text-muted d-block mb-3">

                            <?php
                            echo htmlspecialchars(
                                $shift["employer"]
                            );
                            ?>

                        </span>


                        <div class="row g-3">


                            <div class="col-6">

                                <small class="text-muted d-block">
                                    Date
                                </small>

                                <strong>

                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $shift["shift_date"]
                                        )
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="col-6">

                                <small class="text-muted d-block">
                                    Shift
                                </small>

                                <strong>

                                    <?php
                                    echo date(
                                        "H:i",
                                        strtotime(
                                            $shift["start_time"]
                                        )
                                    );
                                    ?>

                                    -

                                    <?php
                                    echo date(
                                        "H:i",
                                        strtotime(
                                            $shift["end_time"]
                                        )
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="col-6">

                                <small class="text-muted d-block">
                                    Hours
                                </small>

                                <strong>
                                    <?php
                                    echo number_format(
                                        $hours,
                                        1
                                    );
                                    ?>h
                                </strong>

                            </div>


                            <div class="col-6">

                                <small class="text-muted d-block">
                                    Estimated Pay
                                </small>

                                <strong>
                                    £<?php
                                    echo number_format(
                                        $estimatedEarnings,
                                        2
                                    );
                                    ?>
                                </strong>

                            </div>


                        </div>


                    </div>


                    <form method="POST">
                    <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="shift_id"
                            value="<?php
                            echo (int)
                                $shift["id"];
                            ?>"
                        >


                        <div
                            class="d-flex
                                   justify-content-center
                                   gap-2
                                   flex-wrap"
                        >

                            <a
                                href="index.php"
                                class="btn btn-outline-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-danger"
                            >
                                <i class="bi bi-trash me-1"></i>
                                Delete Shift
                            </button>

                        </div>


                    </form>


                </div>


            </div>


        </div>

    </div>


</main>


<?php
require_once("../includes/footer.php");
?>