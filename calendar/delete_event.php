<?php

/*
|--------------------------------------------------------------------------
| Delete Personal Calendar Event
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
| Delete Event
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedId =
        filter_input(
            INPUT_POST,
            "event_id",
            FILTER_VALIDATE_INT
        );


    if (
        !$postedId
        ||
        $postedId !== $eventId
    ) {

        header("Location: index.php");
        exit;
    }


    $deleteQuery =
        $pdo->prepare(
            "DELETE FROM calendar_events

             WHERE id = ?
             AND user_id = ?"
        );


    $deleteQuery->execute([
        $eventId,
        $userId
    ]);


    header(
        "Location: index.php?deleted=1&month="
        . date(
            "Y-m",
            strtotime(
                $event["event_date"]
            )
        )
    );

    exit;
}


$pageTitle = "Delete Calendar Event";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">


    <div class="page-heading">

        <p class="page-kicker">
            CALENDAR
        </p>

        <h1>
            Delete Event
        </h1>

        <p class="text-muted mb-0">
            Confirm removal of this personal calendar event.
        </p>

    </div>


    <div class="row justify-content-center">

        <div class="col-xl-6 col-lg-7">


            <div class="content-card">


                <div class="text-center py-3">


                    <div
                        class="
                            d-inline-flex
                            align-items-center
                            justify-content-center
                            rounded-circle
                            bg-danger-subtle
                            text-danger
                            mb-4
                        "
                        style="
                            width: 72px;
                            height: 72px;
                            font-size: 28px;
                        "
                    >
                        <i class="bi bi-calendar-x"></i>
                    </div>


                    <h3>
                        Delete this event?
                    </h3>

                    <p class="text-muted">
                        This action permanently removes
                        the personal event from your calendar.
                    </p>


                    <div
                        class="
                            text-start
                            border
                            rounded-3
                            p-3
                            my-4
                        "
                    >

                        <strong class="d-block">

                            <?php
                            echo htmlspecialchars(
                                $event["title"]
                            );
                            ?>

                        </strong>


                        <span class="text-muted d-block mb-3">

                            <?php
                            echo ucfirst(
                                htmlspecialchars(
                                    $event["event_type"]
                                )
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
                                            $event["event_date"]
                                        )
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="col-6">

                                <small class="text-muted d-block">
                                    Time
                                </small>

                                <strong>

                                    <?php if (!empty($event["start_time"])): ?>

                                        <?php
                                        echo date(
                                            "H:i",
                                            strtotime(
                                                $event["start_time"]
                                            )
                                        );
                                        ?>

                                        <?php if (!empty($event["end_time"])): ?>

                                            -

                                            <?php
                                            echo date(
                                                "H:i",
                                                strtotime(
                                                    $event["end_time"]
                                                )
                                            );
                                            ?>

                                        <?php endif; ?>

                                    <?php else: ?>

                                        Anytime

                                    <?php endif; ?>

                                </strong>

                            </div>


                        </div>


                        <?php if (!empty($event["description"])): ?>

                            <hr>

                            <small class="text-muted d-block mb-1">
                                Notes
                            </small>

                            <p class="mb-0">

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $event["description"]
                                    )
                                );
                                ?>

                            </p>

                        <?php endif; ?>


                    </div>


                    <form method="POST">
                    <?= csrf_field() ?>


                        <input
                            type="hidden"
                            name="event_id"
                            value="<?php
                            echo (int) $event["id"];
                            ?>"
                        >


                        <div
                            class="
                                d-flex
                                justify-content-center
                                gap-2
                                flex-wrap
                            "
                        >

                            <a
                                href="index.php?month=<?php
                                echo htmlspecialchars(
                                    substr(
                                        $event["event_date"],
                                        0,
                                        7
                                    )
                                );
                                ?>"
                                class="btn btn-outline-secondary"
                            >
                                Cancel
                            </a>


                            <button
                                type="submit"
                                class="btn btn-danger"
                            >
                                <i class="bi bi-trash me-1"></i>
                                Delete Event
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