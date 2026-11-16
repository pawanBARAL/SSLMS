<?php

/*
|--------------------------------------------------------------------------
| Reminders Dashboard
|--------------------------------------------------------------------------
|
| Displays and manages reminders belonging to the logged-in student.
|
| Features:
| - Pending reminders
| - Completed reminders
| - Overdue reminders
| - Today's reminders
| - Priority levels
| - Categories
| - Mark complete / reopen
| - Edit and delete
|
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = $_SESSION["user_id"];

$currentDate = date("Y-m-d");
$currentDateTime = date("Y-m-d H:i:s");


/*
|--------------------------------------------------------------------------
| Mark Reminder Complete / Reopen
|--------------------------------------------------------------------------
|
| The status change is handled using POST rather than GET.
|
*/

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["toggle_status"])) {

    $reminderId =
        filter_input(
            INPUT_POST,
            "reminder_id",
            FILTER_VALIDATE_INT
        );

    if ($reminderId) {

        /*
        |--------------------------------------------------------------------------
        | Find Reminder
        |--------------------------------------------------------------------------
        */

        $getReminder = $pdo->prepare(
            "SELECT id, status
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


        /*
        |--------------------------------------------------------------------------
        | Change Status
        |--------------------------------------------------------------------------
        */

        if ($reminder) {

            $newStatus =
                $reminder["status"] === "completed"
                ? "pending"
                : "completed";

            $updateStatus = $pdo->prepare(
                "UPDATE reminders
                 SET status = ?
                 WHERE id = ?
                 AND user_id = ?"
            );

            $updateStatus->execute([
                $newStatus,
                $reminderId,
                $userId
            ]);

            header(
                "Location: index.php?status_updated=1"
            );

            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Retrieve Reminders
|--------------------------------------------------------------------------
|
| Pending reminders appear first.
| Within each status, reminders are ordered by date and time.
|
*/

$getReminders = $pdo->prepare(
    "SELECT
        id,
        title,
        description,
        reminder_date,
        reminder_time,
        category,
        priority,
        status
     FROM reminders
     WHERE user_id = ?
     ORDER BY
        CASE
            WHEN status = 'pending' THEN 0
            ELSE 1
        END,
        reminder_date ASC,
        reminder_time ASC,
        id DESC"
);

$getReminders->execute([
    $userId
]);

$reminders =
    $getReminders->fetchAll();


/*
|--------------------------------------------------------------------------
| Reminder Statistics
|--------------------------------------------------------------------------
*/

$totalReminders =
    count($reminders);

$pendingCount = 0;
$completedCount = 0;
$overdueCount = 0;
$todayCount = 0;

$upcomingReminders = [];


foreach ($reminders as $reminder) {

    /*
    |--------------------------------------------------------------------------
    | Pending / Completed
    |--------------------------------------------------------------------------
    */

    if ($reminder["status"] === "completed") {

        $completedCount++;

        continue;
    }

    $pendingCount++;


    /*
    |--------------------------------------------------------------------------
    | Build Reminder Date + Time
    |--------------------------------------------------------------------------
    */

    $reminderTime =
        !empty($reminder["reminder_time"])
        ? $reminder["reminder_time"]
        : "23:59:59";

    $reminderDateTime =
        $reminder["reminder_date"]
        . " "
        . $reminderTime;


    /*
    |--------------------------------------------------------------------------
    | Today's Reminders
    |--------------------------------------------------------------------------
    */

    if (
        $reminder["reminder_date"]
        ===
        $currentDate
    ) {

        $todayCount++;
    }


    /*
    |--------------------------------------------------------------------------
    | Overdue Reminders
    |--------------------------------------------------------------------------
    */

    if (
        $reminderDateTime
        <
        $currentDateTime
    ) {

        $overdueCount++;

    } else {

        $upcomingReminders[] =
            $reminder;
    }
}


/*
|--------------------------------------------------------------------------
| Next Reminder
|--------------------------------------------------------------------------
*/

$nextReminder = null;

if (!empty($upcomingReminders)) {

    $nextReminder =
        $upcomingReminders[0];
}


/*
|--------------------------------------------------------------------------
| Completion Percentage
|--------------------------------------------------------------------------
*/

$completionPercentage = 0;

if ($totalReminders > 0) {

    $completionPercentage =
        ($completedCount / $totalReminders)
        * 100;
}


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$successMessage = "";

if (isset($_GET["added"])) {

    $successMessage =
        "Reminder added successfully.";

} elseif (isset($_GET["updated"])) {

    $successMessage =
        "Reminder updated successfully.";

} elseif (isset($_GET["deleted"])) {

    $successMessage =
        "Reminder deleted successfully.";

} elseif (isset($_GET["status_updated"])) {

    $successMessage =
        "Reminder status updated successfully.";
}


$pageTitle = "Reminders";

include("../includes/header.php");
include("../includes/sidebar.php");

?>


<main class="main-content">


    <!-- Page heading -->

    <div
        class="page-heading d-flex
               justify-content-between
               align-items-center
               flex-wrap gap-3"
    >

        <div>

            <p class="page-kicker">
                Personal Organisation
            </p>

            <h1>
                Reminders
            </h1>

            <p class="text-muted mb-0">
                Keep track of important tasks,
                appointments and personal commitments.
            </p>

        </div>


        <a
            href="add_reminder.php"
            class="btn btn-primary"
        >

            <i class="bi bi-plus-lg"></i>

            Add Reminder

        </a>

    </div>


    <!-- Success message -->

    <?php if ($successMessage !== ""): ?>

        <div
            class="alert alert-success
                   alert-dismissible fade show"
        >

            <?php
            echo htmlspecialchars(
                $successMessage
            );
            ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- Reminder statistics -->

    <div class="row g-4 mb-4">


        <div class="col-md-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-list-check"></i>

                </div>

                <div>

                    <span>
                        Pending
                    </span>

                    <h3>
                        <?php echo $pendingCount; ?>
                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-calendar-day"></i>

                </div>

                <div>

                    <span>
                        Today
                    </span>

                    <h3>
                        <?php echo $todayCount; ?>
                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-exclamation-triangle"></i>

                </div>

                <div>

                    <span>
                        Overdue
                    </span>

                    <h3>
                        <?php echo $overdueCount; ?>
                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-check-circle"></i>

                </div>

                <div>

                    <span>
                        Completed
                    </span>

                    <h3>
                        <?php echo $completedCount; ?>
                    </h3>

                </div>

            </div>

        </div>


    </div>


    <!-- Next reminder / completion overview -->

    <div class="row g-4 mb-4">


        <div class="col-lg-7">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <p class="page-kicker">
                            Coming Up
                        </p>

                        <h3>
                            Next Reminder
                        </h3>

                    </div>

                </div>


                <?php if ($nextReminder): ?>


                    <div class="recommended-task">

                        <div
                            class="d-flex
                                   justify-content-between
                                   align-items-start
                                   flex-wrap gap-3"
                        >

                            <div>

                                <h4>

                                    <?php
                                    echo htmlspecialchars(
                                        $nextReminder["title"]
                                    );
                                    ?>

                                </h4>


                                <p class="text-muted mb-0">

                                    <?php

                                    echo htmlspecialchars(
                                        $nextReminder["description"]
                                        ?: "No description provided."
                                    );

                                    ?>

                                </p>

                            </div>


                            <?php

                            $nextPriorityClass =
                                "secondary";

                            if (
                                $nextReminder["priority"]
                                ===
                                "high"
                            ) {

                                $nextPriorityClass =
                                    "danger";

                            } elseif (
                                $nextReminder["priority"]
                                ===
                                "medium"
                            ) {

                                $nextPriorityClass =
                                    "warning";

                            } elseif (
                                $nextReminder["priority"]
                                ===
                                "low"
                            ) {

                                $nextPriorityClass =
                                    "success";
                            }

                            ?>


                            <span
                                class="badge bg-<?php
                                echo $nextPriorityClass;
                                ?>"
                            >

                                <?php
                                echo ucfirst(
                                    htmlspecialchars(
                                        $nextReminder["priority"]
                                    )
                                );
                                ?>

                            </span>

                        </div>


                        <hr>


                        <div class="row g-3">


                            <div class="col-md-4">

                                <small class="text-muted">
                                    Date
                                </small>

                                <div>

                                    <strong>

                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $nextReminder[
                                                    "reminder_date"
                                                ]
                                            )
                                        );
                                        ?>

                                    </strong>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <small class="text-muted">
                                    Time
                                </small>

                                <div>

                                    <strong>

                                        <?php

                                        if (
                                            !empty(
                                                $nextReminder[
                                                    "reminder_time"
                                                ]
                                            )
                                        ) {

                                            echo date(
                                                "H:i",
                                                strtotime(
                                                    $nextReminder[
                                                        "reminder_time"
                                                    ]
                                                )
                                            );

                                        } else {

                                            echo "Any time";
                                        }

                                        ?>

                                    </strong>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <small class="text-muted">
                                    Category
                                </small>

                                <div>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $nextReminder[
                                                "category"
                                            ]
                                        );
                                        ?>

                                    </strong>

                                </div>

                            </div>


                        </div>

                    </div>


                <?php else: ?>


                    <div class="empty-state">

                        <i class="bi bi-bell"></i>

                        <h4>
                            Nothing coming up
                        </h4>

                        <p>
                            You currently have no
                            upcoming pending reminders.
                        </p>

                    </div>


                <?php endif; ?>


            </div>

        </div>


        <div class="col-lg-5">

            <div class="content-card h-100">

                <p class="page-kicker">
                    Progress
                </p>

                <h3>
                    Reminder Completion
                </h3>


                <div
                    class="d-flex
                           justify-content-between
                           mt-4 mb-2"
                >

                    <span>
                        Completed
                    </span>

                    <strong>

                        <?php
                        echo number_format(
                            $completionPercentage,
                            0
                        );
                        ?>%

                    </strong>

                </div>


                <div
                    class="progress"
                    style="height: 12px;"
                >

                    <div
                        class="progress-bar"
                        style="width: <?php
                        echo min(
                            $completionPercentage,
                            100
                        );
                        ?>%;"
                    ></div>

                </div>


                <hr>


                <div
                    class="d-flex
                           justify-content-between"
                >

                    <span>
                        Total reminders
                    </span>

                    <strong>
                        <?php echo $totalReminders; ?>
                    </strong>

                </div>


                <div
                    class="d-flex
                           justify-content-between
                           mt-3"
                >

                    <span>
                        Pending
                    </span>

                    <strong>
                        <?php echo $pendingCount; ?>
                    </strong>

                </div>


                <div
                    class="d-flex
                           justify-content-between
                           mt-3"
                >

                    <span>
                        Completed
                    </span>

                    <strong>
                        <?php echo $completedCount; ?>
                    </strong>

                </div>

            </div>

        </div>


    </div>


    <!-- Reminder table -->

    <div class="content-card">


        <div class="content-card-header">

            <div>

                <p class="page-kicker">
                    Reminder Management
                </p>

                <h3>
                    All Reminders
                </h3>

            </div>


            <a
                href="add_reminder.php"
                class="btn btn-primary"
            >

                <i class="bi bi-plus-lg"></i>

                Add Reminder

            </a>

        </div>


        <?php if (count($reminders) > 0): ?>


            <div class="table-responsive">

                <table
                    class="table
                           custom-table
                           align-middle"
                >

                    <thead>

                        <tr>

                            <th>Reminder</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($reminders as $reminder): ?>


                        <?php

                        /*
                        |--------------------------------------------------------------------------
                        | Determine Display Status
                        |--------------------------------------------------------------------------
                        */

                        $displayStatus =
                            "Pending";

                        $statusClass =
                            "primary";


                        if (
                            $reminder["status"]
                            ===
                            "completed"
                        ) {

                            $displayStatus =
                                "Completed";

                            $statusClass =
                                "success";

                        } else {

                            $checkTime =
                                !empty(
                                    $reminder["reminder_time"]
                                )
                                ?
                                $reminder["reminder_time"]
                                :
                                "23:59:59";


                            $checkDateTime =
                                $reminder["reminder_date"]
                                . " "
                                . $checkTime;


                            if (
                                $checkDateTime
                                <
                                $currentDateTime
                            ) {

                                $displayStatus =
                                    "Overdue";

                                $statusClass =
                                    "danger";

                            } elseif (
                                $reminder["reminder_date"]
                                ===
                                $currentDate
                            ) {

                                $displayStatus =
                                    "Today";

                                $statusClass =
                                    "warning";
                            }
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Priority Colour
                        |--------------------------------------------------------------------------
                        */

                        $priorityClass =
                            "secondary";


                        if (
                            $reminder["priority"]
                            ===
                            "high"
                        ) {

                            $priorityClass =
                                "danger";

                        } elseif (
                            $reminder["priority"]
                            ===
                            "medium"
                        ) {

                            $priorityClass =
                                "warning";

                        } elseif (
                            $reminder["priority"]
                            ===
                            "low"
                        ) {

                            $priorityClass =
                                "success";
                        }

                        ?>


                        <tr>


                            <td>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $reminder["title"]
                                    );
                                    ?>

                                </strong>


                                <?php if (
                                    !empty(
                                        $reminder["description"]
                                    )
                                ): ?>

                                    <small
                                        class="d-block
                                               text-muted"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $reminder[
                                                "description"
                                            ]
                                        );
                                        ?>

                                    </small>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span
                                    class="badge bg-secondary"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $reminder["category"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $reminder[
                                            "reminder_date"
                                        ]
                                    )
                                );
                                ?>

                            </td>


                            <td>

                                <?php

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

                                } else {

                                    echo "-";
                                }

                                ?>

                            </td>


                            <td>

                                <span
                                    class="badge bg-<?php
                                    echo $priorityClass;
                                    ?>"
                                >

                                    <?php
                                    echo ucfirst(
                                        htmlspecialchars(
                                            $reminder[
                                                "priority"
                                            ]
                                        )
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="badge bg-<?php
                                    echo $statusClass;
                                    ?>"
                                >

                                    <?php
                                    echo $displayStatus;
                                    ?>

                                </span>

                            </td>


                            <td>

                                <div
                                    class="d-flex
                                           gap-2
                                           flex-wrap"
                                >


                                    <!-- Complete / reopen -->

                                    <form method="POST">
                    <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="reminder_id"
                                            value="<?php
                                            echo (int)
                                            $reminder["id"];
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="toggle_status"
                                            class="btn btn-sm
                                            <?php
                                            echo
                                            $reminder["status"]
                                            ===
                                            "completed"
                                            ?
                                            "btn-outline-secondary"
                                            :
                                            "btn-outline-success";
                                            ?>"
                                        >

                                            <?php if (
                                                $reminder["status"]
                                                ===
                                                "completed"
                                            ): ?>

                                                <i
                                                    class="bi bi-arrow-counterclockwise"
                                                ></i>

                                                Reopen

                                            <?php else: ?>

                                                <i
                                                    class="bi bi-check-lg"
                                                ></i>

                                                Complete

                                            <?php endif; ?>

                                        </button>

                                    </form>


                                    <!-- Edit -->

                                    <a
                                        href="edit_reminder.php?id=<?php
                                        echo (int)
                                        $reminder["id"];
                                        ?>"
                                        class="btn btn-sm
                                               btn-outline-primary"
                                    >

                                        <i class="bi bi-pencil"></i>

                                        Edit

                                    </a>


                                    <!-- Delete -->

                                    <form
                                        method="POST"
                                        action="delete_reminder.php"
                                        onsubmit="return confirm('Are you sure you want to delete this reminder?');"
                                    >
                    <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="reminder_id"
                                            value="<?php
                                            echo (int)
                                            $reminder["id"];
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm
                                                   btn-outline-danger"
                                        >

                                            <i class="bi bi-trash"></i>

                                            Delete

                                        </button>

                                    </form>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="empty-state">

                <i class="bi bi-bell"></i>

                <h4>
                    No reminders yet
                </h4>

                <p>
                    Add your first reminder to keep
                    track of important commitments.
                </p>

                <a
                    href="add_reminder.php"
                    class="btn btn-primary"
                >
                    Add Reminder
                </a>

            </div>


        <?php endif; ?>


    </div>


</main>


<?php
include("../includes/footer.php");
?>