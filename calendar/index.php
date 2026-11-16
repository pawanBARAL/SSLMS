<?php

/*
|--------------------------------------------------------------------------
| Integrated Student Calendar
|--------------------------------------------------------------------------
| Combines:
| - Academic deadlines
| - Work shifts
| - Reminders
| - Personal calendar events
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once __DIR__ . '/../includes/validation.php';
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Determine Selected Month
|--------------------------------------------------------------------------
*/

$selectedMonth =
    $_GET["month"] ?? date("Y-m");

if (
    !valid_month($selectedMonth)
) {
    $selectedMonth = date("Y-m");
}


$monthObject =
    DateTime::createFromFormat(
        "Y-m-d",
        $selectedMonth . "-01"
    );

if (!$monthObject) {

    $monthObject =
        new DateTime(
            date("Y-m-01")
        );
}


$year =
    (int) $monthObject->format("Y");

$month =
    (int) $monthObject->format("m");

$monthName =
    $monthObject->format("F Y");

$firstDay =
    $monthObject->format("Y-m-01");

$lastDay =
    $monthObject->format("Y-m-t");

$daysInMonth =
    (int) $monthObject->format("t");


/*
|--------------------------------------------------------------------------
| Previous / Next Month
|--------------------------------------------------------------------------
*/

$previousMonth =
    (clone $monthObject)
        ->modify("-1 month")
        ->format("Y-m");

$nextMonth =
    (clone $monthObject)
        ->modify("+1 month")
        ->format("Y-m");


/*
|--------------------------------------------------------------------------
| Calendar Position
|--------------------------------------------------------------------------
| PHP N:
| Monday = 1
| Sunday = 7
|--------------------------------------------------------------------------
*/

$firstWeekday =
    (int) $monthObject->format("N");


/*
|--------------------------------------------------------------------------
| Calendar Event Collection
|--------------------------------------------------------------------------
| Every module is converted into a common event structure.
|--------------------------------------------------------------------------
*/

$calendarItems = [];


/*
|--------------------------------------------------------------------------
| Academic Deadlines
|--------------------------------------------------------------------------
*/

$academicQuery =
    $pdo->prepare(
        "SELECT
            id,
            title,
            module_name,
            due_date,
            priority,
            status

         FROM assignments

         WHERE user_id = ?
         AND due_date BETWEEN ? AND ?"
    );

$academicQuery->execute([
    $userId,
    $firstDay,
    $lastDay
]);

$academicTasks =
    $academicQuery->fetchAll();


foreach ($academicTasks as $task) {

    $date = $task["due_date"];

    $calendarItems[$date][] = [

        "source" => "academic",

        "title" => $task["title"],

        "subtitle" =>
            $task["module_name"],

        "time" => null,

        "priority" =>
            $task["priority"],

        "status" =>
            $task["status"],

        "url" =>
            "../academics/edit_task.php?id="
            . (int) $task["id"]
    ];
}


/*
|--------------------------------------------------------------------------
| Work Shifts
|--------------------------------------------------------------------------
*/

$workQuery =
    $pdo->prepare(
        "SELECT
            id,
            employer,
            job_title,
            shift_date,
            start_time,
            end_time

         FROM work_shifts

         WHERE user_id = ?
         AND shift_date BETWEEN ? AND ?"
    );

$workQuery->execute([
    $userId,
    $firstDay,
    $lastDay
]);

$workShifts =
    $workQuery->fetchAll();


foreach ($workShifts as $shift) {

    $date =
        $shift["shift_date"];

    $calendarItems[$date][] = [

        "source" => "work",

        "title" =>
            $shift["job_title"],

        "subtitle" =>
            !empty($shift["employer"])
                ? $shift["employer"]
                : "Work Shift",

        "time" =>
            date(
                "H:i",
                strtotime(
                    $shift["start_time"]
                )
            )
            .
            " - "
            .
            date(
                "H:i",
                strtotime(
                    $shift["end_time"]
                )
            ),

        "priority" => null,

        "status" => null,

        "url" =>
            "../work/edit_shift.php?id="
            . (int) $shift["id"]
    ];
}


/*
|--------------------------------------------------------------------------
| Reminders
|--------------------------------------------------------------------------
*/

$reminderQuery =
    $pdo->prepare(
        "SELECT
            id,
            title,
            reminder_date,
            reminder_time,
            priority,
            status

         FROM reminders

         WHERE user_id = ?
         AND reminder_date BETWEEN ? AND ?"
    );

$reminderQuery->execute([
    $userId,
    $firstDay,
    $lastDay
]);

$reminders =
    $reminderQuery->fetchAll();


foreach ($reminders as $reminder) {

    $date =
        $reminder["reminder_date"];

    $calendarItems[$date][] = [

        "source" => "reminder",

        "title" =>
            $reminder["title"],

        "subtitle" =>
            ucfirst(
                $reminder["priority"]
            )
            . " priority",

        "time" =>
            !empty(
                $reminder["reminder_time"]
            )
            ?
            date(
                "H:i",
                strtotime(
                    $reminder["reminder_time"]
                )
            )
            :
            null,

        "priority" =>
            $reminder["priority"],

        "status" =>
            $reminder["status"],

        "url" =>
            "../reminders/edit_reminder.php?id="
            . (int) $reminder["id"]
    ];
}


/*
|--------------------------------------------------------------------------
| Personal Calendar Events
|--------------------------------------------------------------------------
*/

$personalQuery =
    $pdo->prepare(
        "SELECT *
         FROM calendar_events

         WHERE user_id = ?
         AND event_date BETWEEN ? AND ?

         ORDER BY
            event_date ASC,
            start_time ASC"
    );

$personalQuery->execute([
    $userId,
    $firstDay,
    $lastDay
]);

$personalEvents =
    $personalQuery->fetchAll();


foreach ($personalEvents as $event) {

    $date =
        $event["event_date"];

    $eventTime = null;

    if (!empty($event["start_time"])) {

        $eventTime =
            date(
                "H:i",
                strtotime(
                    $event["start_time"]
                )
            );

        if (!empty($event["end_time"])) {

            $eventTime .=
                " - "
                .
                date(
                    "H:i",
                    strtotime(
                        $event["end_time"]
                    )
                );
        }
    }


    $calendarItems[$date][] = [

        "source" => "personal",

        "title" =>
            $event["title"],

        "subtitle" =>
            ucfirst(
                $event["event_type"]
            ),

        "time" =>
            $eventTime,

        "priority" => null,

        "status" => null,

        "url" =>
            "edit_event.php?id="
            . (int) $event["id"]
    ];
}


/*
|--------------------------------------------------------------------------
| Calendar Statistics
|--------------------------------------------------------------------------
*/

$academicCount =
    count($academicTasks);

$workCount =
    count($workShifts);

$reminderCount =
    count($reminders);

$personalCount =
    count($personalEvents);

$totalEvents =
    $academicCount
    +
    $workCount
    +
    $reminderCount
    +
    $personalCount;


/*
|--------------------------------------------------------------------------
| Today's Information
|--------------------------------------------------------------------------
*/

$today =
    date("Y-m-d");

$todayItems =
    $calendarItems[$today]
    ?? [];


/*
|--------------------------------------------------------------------------
| Upcoming Events
|--------------------------------------------------------------------------
| Create one flat list for the upcoming schedule panel.
|--------------------------------------------------------------------------
*/

$upcomingItems = [];

foreach ($calendarItems as $date => $items) {

    if ($date < $today) {
        continue;
    }

    foreach ($items as $item) {

        $item["date"] = $date;

        $upcomingItems[] = $item;
    }
}


/*
|--------------------------------------------------------------------------
| Sort Upcoming Events
|--------------------------------------------------------------------------
*/

usort(
    $upcomingItems,
    function ($a, $b) {

        $dateComparison =
            strcmp(
                $a["date"],
                $b["date"]
            );

        if ($dateComparison !== 0) {
            return $dateComparison;
        }

        return strcmp(
            $a["time"] ?? "",
            $b["time"] ?? ""
        );
    }
);


$upcomingItems =
    array_slice(
        $upcomingItems,
        0,
        6
    );


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$successMessage = "";

if (($_GET["added"] ?? "") === "1") {

    $successMessage =
        "Calendar event added successfully.";
}

if (($_GET["updated"] ?? "") === "1") {

    $successMessage =
        "Calendar event updated successfully.";
}

if (($_GET["deleted"] ?? "") === "1") {

    $successMessage =
        "Calendar event deleted successfully.";
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Calendar";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">


    <!-- Page Heading -->

    <div class="page-heading">

        <div
            class="d-flex
                   justify-content-between
                   align-items-start
                   flex-wrap
                   gap-3"
        >

            <div>

                <p class="page-kicker">
                    STUDENT PLANNER
                </p>

                <h1>
                    Integrated Calendar
                </h1>

                <p class="text-muted mb-0">
                    See your academic deadlines,
                    work shifts, reminders and
                    personal plans in one place.
                </p>

            </div>


            <a
                href="add_event.php"
                class="btn btn-primary"
            >
                <i class="bi bi-calendar-plus me-1"></i>
                Add Personal Event
            </a>

        </div>

    </div>


    <?php if ($successMessage !== ""): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle me-2"></i>

            <?php
            echo htmlspecialchars(
                $successMessage
            );
            ?>

        </div>

    <?php endif; ?>


    <!-- Monthly Statistics -->

    <div class="row g-3 mb-4">


        <div class="col-xl col-md-4 col-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-calendar3"></i>
                </div>

                <div>

                    <span>
                        Total Events
                    </span>

                    <h3>
                        <?php echo $totalEvents; ?>
                    </h3>

                    <small class="text-muted">
                        This month
                    </small>

                </div>

            </div>

        </div>


        <div class="col-xl col-md-4 col-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-mortarboard"></i>
                </div>

                <div>

                    <span>
                        Academic
                    </span>

                    <h3>
                        <?php echo $academicCount; ?>
                    </h3>

                    <small class="text-muted">
                        Deadlines
                    </small>

                </div>

            </div>

        </div>


        <div class="col-xl col-md-4 col-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-briefcase"></i>
                </div>

                <div>

                    <span>
                        Work
                    </span>

                    <h3>
                        <?php echo $workCount; ?>
                    </h3>

                    <small class="text-muted">
                        Shifts
                    </small>

                </div>

            </div>

        </div>


        <div class="col-xl col-md-6 col-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-bell"></i>
                </div>

                <div>

                    <span>
                        Reminders
                    </span>

                    <h3>
                        <?php echo $reminderCount; ?>
                    </h3>

                    <small class="text-muted">
                        Scheduled
                    </small>

                </div>

            </div>

        </div>


        <div class="col-xl col-md-6 col-12">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-person"></i>
                </div>

                <div>

                    <span>
                        Personal
                    </span>

                    <h3>
                        <?php echo $personalCount; ?>
                    </h3>

                    <small class="text-muted">
                        Events
                    </small>

                </div>

            </div>

        </div>


    </div>


    <div class="row g-4">


        <!-- Main Calendar -->

        <div class="col-xl-9">


            <div class="content-card">


                <!-- Calendar Header -->

                <div class="content-card-header">

                    <a
                        href="?month=<?php
                        echo htmlspecialchars(
                            $previousMonth
                        );
                        ?>"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-chevron-left"></i>
                    </a>


                    <div class="text-center">

                        <h3 class="mb-0">

                            <?php
                            echo htmlspecialchars(
                                $monthName
                            );
                            ?>

                        </h3>

                        <small class="text-muted">
                            Monthly Schedule
                        </small>

                    </div>


                    <div class="d-flex gap-2">

                        <a
                            href="index.php"
                            class="btn btn-outline-primary"
                        >
                            Today
                        </a>

                        <a
                            href="?month=<?php
                            echo htmlspecialchars(
                                $nextMonth
                            );
                            ?>"
                            class="btn btn-outline-secondary"
                        >
                            <i class="bi bi-chevron-right"></i>
                        </a>

                    </div>

                </div>


                <!-- Calendar Legend -->

                <div
                    class="d-flex
                           flex-wrap
                           gap-3
                           mb-3"
                >

                    <small>
                        <span
                            class="badge bg-primary me-1"
                        >
                            &nbsp;
                        </span>
                        Academic
                    </small>

                    <small>
                        <span
                            class="badge bg-success me-1"
                        >
                            &nbsp;
                        </span>
                        Work
                    </small>

                    <small>
                        <span
                            class="badge bg-warning me-1"
                        >
                            &nbsp;
                        </span>
                        Reminder
                    </small>

                    <small>
                        <span
                            class="badge bg-info me-1"
                        >
                            &nbsp;
                        </span>
                        Personal
                    </small>

                </div>


                <!-- Calendar -->

                <div class="table-responsive">

                    <table
                        class="table
                               table-bordered
                               calendar-table"
                    >

                        <thead>

                            <tr class="text-center">

                                <th>Mon</th>
                                <th>Tue</th>
                                <th>Wed</th>
                                <th>Thu</th>
                                <th>Fri</th>
                                <th>Sat</th>
                                <th>Sun</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php

                        $currentDay = 1;

                        $calendarStarted = false;

                        for ($week = 0; $week < 6; $week++):

                            if ($currentDay > $daysInMonth) {
                                break;
                            }

                        ?>

                            <tr>


                            <?php for ($weekday = 1; $weekday <= 7; $weekday++): ?>


                                <?php

                                $showDay =
                                    (
                                        $week > 0
                                        ||
                                        $weekday >= $firstWeekday
                                    )
                                    &&
                                    $currentDay <= $daysInMonth;

                                ?>


                                <?php if (!$showDay): ?>


                                    <td
                                        class="calendar-empty"
                                        style="
                                            min-width: 135px;
                                            height: 145px;
                                        "
                                    >
                                    </td>


                                <?php else: ?>


                                    <?php

                                    $dateString =
                                        sprintf(
                                            "%04d-%02d-%02d",
                                            $year,
                                            $month,
                                            $currentDay
                                        );

                                    $dayItems =
                                        $calendarItems[$dateString]
                                        ?? [];

                                    $isToday =
                                        $dateString === $today;

                                    ?>


                                    <td
                                        class="<?php
                                        echo
                                            $isToday
                                            ? "bg-primary-subtle"
                                            : "";
                                        ?>"
                                        style="
                                            min-width: 135px;
                                            height: 145px;
                                            vertical-align: top;
                                        "
                                    >


                                        <!-- Date -->

                                        <div
                                            class="d-flex
                                                   justify-content-between
                                                   align-items-center
                                                   mb-2"
                                        >

                                            <strong>

                                                <?php
                                                echo $currentDay;
                                                ?>

                                            </strong>


                                            <?php if ($isToday): ?>

                                                <span
                                                    class="badge bg-primary"
                                                >
                                                    Today
                                                </span>

                                            <?php endif; ?>

                                        </div>


                                        <!-- Events -->

                                        <?php

                                        $visibleItems =
                                            array_slice(
                                                $dayItems,
                                                0,
                                                3
                                            );

                                        ?>


                                        <?php foreach ($visibleItems as $item): ?>


                                            <?php

                                            $eventClass =
                                                match (
                                                    $item["source"]
                                                ) {

                                                    "academic" =>
                                                        "bg-primary",

                                                    "work" =>
                                                        "bg-success",

                                                    "reminder" =>
                                                        "bg-warning text-dark",

                                                    default =>
                                                        "bg-info text-dark"
                                                };

                                            ?>


                                            <a
                                                href="<?php
                                                echo htmlspecialchars(
                                                    $item["url"]
                                                );
                                                ?>"
                                                class="
                                                    d-block
                                                    text-decoration-none
                                                    mb-1
                                                "
                                            >

                                                <span
                                                    class="
                                                        badge
                                                        <?php
                                                        echo $eventClass;
                                                        ?>
                                                        text-wrap
                                                        text-start
                                                        w-100
                                                    "
                                                    style="
                                                        font-size: 0.72rem;
                                                        line-height: 1.25;
                                                    "
                                                >

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $item["title"]
                                                    );
                                                    ?>

                                                    <?php if ($item["time"]): ?>

                                                        <br>

                                                        <small>
                                                            <?php
                                                            echo htmlspecialchars(
                                                                $item["time"]
                                                            );
                                                            ?>
                                                        </small>

                                                    <?php endif; ?>

                                                </span>

                                            </a>


                                        <?php endforeach; ?>


                                        <?php if (count($dayItems) > 3): ?>

                                            <small class="text-muted">

                                                +<?php
                                                echo
                                                    count($dayItems)
                                                    - 3;
                                                ?>
                                                more

                                            </small>

                                        <?php endif; ?>


                                    </td>


                                    <?php $currentDay++; ?>


                                <?php endif; ?>


                            <?php endfor; ?>


                            </tr>


                        <?php endfor; ?>

                        </tbody>

                    </table>

                </div>


            </div>


        </div>


        <!-- Right Panel -->

        <div class="col-xl-3">


            <!-- Today -->

            <div class="content-card mb-4">

                <div class="content-card-header">

                    <div>

                        <h3>
                            Today
                        </h3>

                        <small class="text-muted">

                            <?php
                            echo date(
                                "d F Y"
                            );
                            ?>

                        </small>

                    </div>

                    <div class="stat-icon">
                        <i class="bi bi-calendar-day"></i>
                    </div>

                </div>


                <?php if (empty($todayItems)): ?>

                    <p class="text-muted mb-0">
                        Nothing scheduled today.
                    </p>

                <?php else: ?>


                    <?php foreach ($todayItems as $item): ?>


                        <div
                            class="
                                border
                                rounded-3
                                p-2
                                mb-2
                            "
                        >

                            <strong class="d-block">

                                <?php
                                echo htmlspecialchars(
                                    $item["title"]
                                );
                                ?>

                            </strong>

                            <small class="text-muted">

                                <?php
                                echo ucfirst(
                                    htmlspecialchars(
                                        $item["source"]
                                    )
                                );
                                ?>

                                <?php if ($item["time"]): ?>

                                    ·

                                    <?php
                                    echo htmlspecialchars(
                                        $item["time"]
                                    );
                                    ?>

                                <?php endif; ?>

                            </small>

                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>

            </div>


            <!-- Upcoming -->

            <div class="content-card">

                <div class="content-card-header">

                    <div>

                        <h3>
                            Coming Up
                        </h3>

                        <small class="text-muted">
                            Next scheduled items
                        </small>

                    </div>

                </div>


                <?php if (empty($upcomingItems)): ?>

                    <p class="text-muted mb-0">
                        No upcoming events this month.
                    </p>

                <?php else: ?>


                    <?php foreach ($upcomingItems as $item): ?>


                        <a
                            href="<?php
                            echo htmlspecialchars(
                                $item["url"]
                            );
                            ?>"
                            class="
                                d-block
                                text-decoration-none
                                text-body
                                border-bottom
                                py-3
                            "
                        >

                            <small
                                class="text-muted d-block"
                            >

                                <?php
                                echo date(
                                    "D, d M",
                                    strtotime(
                                        $item["date"]
                                    )
                                );
                                ?>

                            </small>

                            <strong class="d-block">

                                <?php
                                echo htmlspecialchars(
                                    $item["title"]
                                );
                                ?>

                            </strong>

                            <small class="text-muted">

                                <?php
                                echo ucfirst(
                                    htmlspecialchars(
                                        $item["source"]
                                    )
                                );
                                ?>

                                <?php if ($item["time"]): ?>

                                    ·

                                    <?php
                                    echo htmlspecialchars(
                                        $item["time"]
                                    );
                                    ?>

                                <?php endif; ?>

                            </small>

                        </a>


                    <?php endforeach; ?>


                <?php endif; ?>


            </div>


        </div>


    </div>


</main>


<?php
require_once("../includes/footer.php");
?>