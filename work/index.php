
<?php

/*
|--------------------------------------------------------------------------
| Work Management Dashboard
|--------------------------------------------------------------------------
| Displays work statistics, upcoming shifts, monthly activity and
| work history for the currently logged-in student.
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];

$search = trim($_GET["search"] ?? "");
$view = trim($_GET["view"] ?? "");

if (!in_array($view, ["", "upcoming", "past"], true)) {
    $view = "";
}

/*
|--------------------------------------------------------------------------
| Helper: Calculate Shift Hours
|--------------------------------------------------------------------------
| Supports overnight shifts where the ending time is on the next day.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/planning_rules.php';

function calculateShiftHours(string $startTime, string $endTime): float
{
    return sslms_shift_hours('2000-01-01', $startTime, $endTime);
}

/*
|--------------------------------------------------------------------------
| Retrieve All Shifts For Statistics
|--------------------------------------------------------------------------
*/

$allShiftQuery = $pdo->prepare(
    "SELECT *
     FROM work_shifts
     WHERE user_id = ?
     ORDER BY shift_date DESC, start_time DESC"
);

$allShiftQuery->execute([$userId]);

$allShifts = $allShiftQuery->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Work Statistics
|--------------------------------------------------------------------------
*/

$totalShifts = count($allShifts);

$upcomingShifts = 0;
$completedShifts = 0;

$totalWorkedHours = 0;
$totalEstimatedEarnings = 0;

$monthHours = 0;
$monthEarnings = 0;
$monthShifts = 0;

$today = date("Y-m-d");
$now = new DateTimeImmutable();

$currentMonth = date("Y-m");

foreach ($allShifts as $shift) {

    $hours = calculateShiftHours(
        $shift["start_time"],
        $shift["end_time"]
    );

    $earnings = $hours * (float) ($shift["hourly_rate"] ?? 0);

    /*
     * Use the actual end date/time so a shift ending earlier
     * today is correctly classified as completed.
     */

    $shiftStart = new DateTimeImmutable(
        $shift["shift_date"] . " " . $shift["start_time"]
    );

    $shiftEnd = new DateTimeImmutable(
        $shift["shift_date"] . " " . $shift["end_time"]
    );

    if ($shiftEnd < $shiftStart) {
        $shiftEnd = $shiftEnd->modify("+1 day");
    }

    if ($shiftEnd > $now) {
        $upcomingShifts++;
    } else {
        $completedShifts++;
        $totalWorkedHours += $hours;
        $totalEstimatedEarnings += $earnings;
    }

    /*
     * Current month summary.
     * Shifts are grouped by their starting date.
     */

    if (substr($shift["shift_date"], 0, 7) === $currentMonth) {
        $monthShifts++;
        $monthHours += $hours;
        $monthEarnings += $earnings;
    }
}

/*
|--------------------------------------------------------------------------
| Filtered Shift Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT *
    FROM work_shifts
    WHERE user_id = ?
";

$params = [$userId];

if ($view === "upcoming") {

    $sql .= "
        AND (
            TIMESTAMP(shift_date, end_time) > NOW()
            OR (
                end_time <= start_time
                AND TIMESTAMP(DATE_ADD(shift_date, INTERVAL 1 DAY), end_time)
                    > NOW()
            )
        )
    ";

} elseif ($view === "past") {

    $sql .= "
        AND (
            (
                end_time > start_time
                AND TIMESTAMP(shift_date, end_time) <= NOW()
            )
            OR (
                end_time <= start_time
                AND TIMESTAMP(DATE_ADD(shift_date, INTERVAL 1 DAY), end_time)
                    <= NOW()
            )
        )
    ";
}

/*
|--------------------------------------------------------------------------
| Search by employer, job title or notes
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            employer LIKE ?
            OR job_title LIKE ?
            OR notes LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

/*
|--------------------------------------------------------------------------
| Sort Shift Records
|--------------------------------------------------------------------------
*/

if ($view === "upcoming") {

    $sql .= "
        ORDER BY shift_date ASC, start_time ASC
    ";

} else {

    $sql .= "
        ORDER BY shift_date DESC, start_time DESC
    ";
}

$shiftQuery = $pdo->prepare($sql);
$shiftQuery->execute($params);

$shifts = $shiftQuery->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Next Upcoming Shifts
|--------------------------------------------------------------------------
*/

$upcomingQuery = $pdo->prepare(
    "SELECT *
     FROM work_shifts
     WHERE user_id = ?
       AND (
            TIMESTAMP(shift_date, end_time) > NOW()
            OR (
                end_time <= start_time
                AND TIMESTAMP(DATE_ADD(shift_date, INTERVAL 1 DAY), end_time)
                    > NOW()
            )
       )
     ORDER BY shift_date ASC, start_time ASC
     LIMIT 5"
);

$upcomingQuery->execute([$userId]);

$nextShifts = $upcomingQuery->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Success Messages
|--------------------------------------------------------------------------
*/

$successMessage = "";

if (($_GET["added"] ?? "") === "1") {

    $successMessage = "Work shift added successfully.";

} elseif (($_GET["updated"] ?? "") === "1") {

    $successMessage = "Work shift updated successfully.";

} elseif (($_GET["deleted"] ?? "") === "1") {

    $successMessage = "Work shift deleted successfully.";
}

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Work Management";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>

<main class="main-content">

    <!-- Page heading -->

    <div class="page-heading">

        <div class="d-flex justify-content-between
                    align-items-start flex-wrap gap-3">

            <div>

                <p class="page-kicker">
                    WORK & EMPLOYMENT
                </p>

                <h1>
                    Work Management
                </h1>

                <p class="text-muted mb-0">
                    Manage your shifts, working hours and estimated earnings.
                </p>

            </div>

            <!-- Work management actions -->

            <div class="d-flex flex-wrap gap-2">

                <a href="balance_advisor.php"
                   class="btn btn-outline-primary">

                    <i class="bi bi-activity me-2"></i>
                    Work-Study Balance

                </a>

                <a href="add_shift.php"
                   class="btn btn-primary">

                    <i class="bi bi-plus-lg me-1"></i>
                    Add Shift

                </a>

            </div>

        </div>

    </div>

    <!-- Success message -->

    <?php if ($successMessage !== ""): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle me-2"></i>

            <?= htmlspecialchars($successMessage) ?>

        </div>

    <?php endif; ?>

    <!-- Statistics -->

    <div class="row g-3 mb-4">

        <!-- Total shifts -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-calendar-check"></i>
                </div>

                <div>

                    <span>
                        Total Shifts
                    </span>

                    <h3>
                        <?= $totalShifts ?>
                    </h3>

                    <small class="text-muted">
                        All recorded shifts
                    </small>

                </div>

            </div>

        </div>

        <!-- Upcoming shifts -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-calendar-event"></i>
                </div>

                <div>

                    <span>
                        Upcoming
                    </span>

                    <h3>
                        <?= $upcomingShifts ?>
                    </h3>

                    <small class="text-muted">
                        Scheduled or ongoing shifts
                    </small>

                </div>

            </div>

        </div>

        <!-- Hours worked -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-clock-history"></i>
                </div>

                <div>

                    <span>
                        Hours Worked
                    </span>

                    <h3>
                        <?= number_format($totalWorkedHours, 1) ?>h
                    </h3>

                    <small class="text-muted">
                        From completed shifts
                    </small>

                </div>

            </div>

        </div>

        <!-- Estimated earnings -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-cash-stack"></i>
                </div>

                <div>

                    <span>
                        Est. Earnings
                    </span>

                    <h3>
                        £<?= number_format($totalEstimatedEarnings, 2) ?>
                    </h3>

                    <small class="text-muted">
                        Completed shifts
                    </small>

                </div>

            </div>

        </div>

    </div>

    <!-- Monthly summary and upcoming schedule -->

    <div class="row g-4 mb-4">

        <!-- This month -->

        <div class="col-xl-5">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <h3>
                            This Month
                        </h3>

                        <small class="text-muted">
                            <?= date("F Y") ?>
                        </small>

                    </div>

                    <div class="stat-icon">
                        <i class="bi bi-calendar3"></i>
                    </div>

                </div>

                <div class="row g-3">

                    <div class="col-4">

                        <div class="dashboard-finance-box">

                            <span>
                                Shifts
                            </span>

                            <h4>
                                <?= $monthShifts ?>
                            </h4>

                        </div>

                    </div>

                    <div class="col-4">

                        <div class="dashboard-finance-box">

                            <span>
                                Hours
                            </span>

                            <h4>
                                <?= number_format($monthHours, 1) ?>
                            </h4>

                        </div>

                    </div>

                    <div class="col-4">

                        <div class="dashboard-finance-box">

                            <span>
                                Est. Pay
                            </span>

                            <h4>
                                £<?= number_format($monthEarnings, 2) ?>
                            </h4>

                        </div>

                    </div>

                </div>

                <div class="mt-4">

                    <p class="text-muted mb-2">
                        Work activity
                    </p>

                    <h4 class="mb-1">
                        <?= $completedShifts ?>
                        completed shift<?= $completedShifts === 1 ? "" : "s" ?>
                    </h4>

                    <small class="text-muted">
                        Across your recorded work history
                    </small>

                </div>

            </div>

        </div>

        <!-- Upcoming schedule -->

        <div class="col-xl-7">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <h3>
                            Upcoming Schedule
                        </h3>

                        <small class="text-muted">
                            Your next scheduled work shifts
                        </small>

                    </div>

                    <a href="index.php?view=upcoming"
                       class="btn btn-sm btn-outline-primary">

                        View All

                    </a>

                </div>

                <?php if (empty($nextShifts)): ?>

                    <div class="empty-state py-4">

                        <i class="bi bi-calendar2"></i>

                        <h4>
                            No upcoming shifts
                        </h4>

                        <p>
                            Add your next work shift to build your schedule.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table align-middle mb-0">

                            <thead>

                                <tr>
                                    <th>Job</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Pay</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($nextShifts as $shift): ?>

                                    <?php

                                    $hours = calculateShiftHours(
                                        $shift["start_time"],
                                        $shift["end_time"]
                                    );

                                    $earnings =
                                        $hours *
                                        (float) ($shift["hourly_rate"] ?? 0);

                                    ?>

                                    <tr>

                                        <td>

                                            <strong class="d-block">

                                                <?= htmlspecialchars(
                                                    $shift["job_title"]
                                                ) ?>

                                            </strong>

                                            <small class="text-muted">

                                                <?= htmlspecialchars(
                                                    $shift["employer"] ?? ""
                                                ) ?>

                                            </small>

                                        </td>

                                        <td>

                                            <?= date(
                                                "d M",
                                                strtotime($shift["shift_date"])
                                            ) ?>

                                        </td>

                                        <td>

                                            <?= date(
                                                "H:i",
                                                strtotime($shift["start_time"])
                                            ) ?>

                                            -

                                            <?= date(
                                                "H:i",
                                                strtotime($shift["end_time"])
                                            ) ?>

                                        </td>

                                        <td>

                                            <strong>

                                                £<?= number_format(
                                                    $earnings,
                                                    2
                                                ) ?>

                                            </strong>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- Work-Study Balance feature card -->

    <div class="content-card mb-4">

        <div class="content-card-header">

            <div>

                <p class="page-kicker mb-1">
                    SMART INSIGHT
                </p>

                <h3 class="mb-1">
                    Work-Study Balance Advisor
                </h3>

                <small class="text-muted">
                    Connect your work schedule with academic deadlines.
                </small>

            </div>

            <div class="stat-icon">
                <i class="bi bi-activity"></i>
            </div>

        </div>

        <div class="row g-3 align-items-center">

            <div class="col-lg-8">

                <h5 class="mb-2">
                    Is your schedule balanced?
                </h5>

                <p class="text-muted mb-0">
                    Analyse upcoming work hours, outstanding assignments
                    and deadline conflicts. Get rule-based recommendations
                    to help organise your week.
                </p>

            </div>

            <div class="col-lg-4 text-lg-end">

                <a href="balance_advisor.php"
                   class="btn btn-primary">

                    <i class="bi bi-stars me-2"></i>
                    Analyse My Balance

                </a>

            </div>

        </div>

    </div>

    <!-- Search and filters -->

    <div class="content-card mb-4">

        <form method="GET">

            <div class="row g-3 align-items-end">

                <div class="col-lg-6">

                    <label for="search" class="form-label">
                        Search Work Records
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="text"
                            id="search"
                            name="search"
                            class="form-control"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search employer, job title or notes"
                        >

                    </div>

                </div>

                <div class="col-lg-3">

                    <label for="view" class="form-label">
                        Shift Type
                    </label>

                    <select
                        id="view"
                        name="view"
                        class="form-select"
                    >

                        <option value="">
                            All Shifts
                        </option>

                        <option
                            value="upcoming"
                            <?= $view === "upcoming" ? "selected" : "" ?>
                        >
                            Upcoming
                        </option>

                        <option
                            value="past"
                            <?= $view === "past" ? "selected" : "" ?>
                        >
                            Completed
                        </option>

                    </select>

                </div>

                <div class="col-lg-3">

                    <div class="d-flex gap-2">

                        <button
                            type="submit"
                            class="btn btn-primary flex-grow-1"
                        >

                            <i class="bi bi-funnel me-1"></i>
                            Filter

                        </button>

                        <a href="index.php"
                           class="btn btn-outline-secondary">

                            Reset

                        </a>

                    </div>

                </div>

            </div>

        </form>

    </div>

    <!-- Work history -->

    <div class="content-card">

        <div class="content-card-header">

            <div>

                <h3>
                    Work Records
                </h3>

                <small class="text-muted">
                    <?= count($shifts) ?> shift(s) shown
                </small>

            </div>

            <a href="add_shift.php"
               class="btn btn-sm btn-primary">

                <i class="bi bi-plus-lg me-1"></i>
                New Shift

            </a>

        </div>

        <?php if (empty($shifts)): ?>

            <div class="empty-state">

                <i class="bi bi-briefcase"></i>

                <h4>
                    No work shifts found
                </h4>

                <p>
                    Add a shift or change your filters to see work records.
                </p>

                <a href="add_shift.php"
                   class="btn btn-primary mt-3">

                    Add Shift

                </a>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table align-middle">

                    <thead>

                        <tr>
                            <th>Job</th>
                            <th>Date</th>
                            <th>Shift</th>
                            <th>Hours</th>
                            <th>Rate</th>
                            <th>Est. Earnings</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($shifts as $shift): ?>

                            <?php

                            $hours = calculateShiftHours(
                                $shift["start_time"],
                                $shift["end_time"]
                            );

                            $earnings =
                                $hours *
                                (float) ($shift["hourly_rate"] ?? 0);

                            $shiftStart = new DateTimeImmutable(
                                $shift["shift_date"] . " " .
                                $shift["start_time"]
                            );

                            $shiftEnd = new DateTimeImmutable(
                                $shift["shift_date"] . " " .
                                $shift["end_time"]
                            );

                            if ($shiftEnd < $shiftStart) {
                                $shiftEnd = $shiftEnd->modify("+1 day");
                            }

                            $isUpcoming = $shiftEnd > $now;

                            ?>

                            <tr>

                                <td>

                                    <strong class="d-block">

                                        <?= htmlspecialchars(
                                            $shift["job_title"]
                                        ) ?>

                                    </strong>

                                    <small class="text-muted">

                                        <?= htmlspecialchars(
                                            $shift["employer"] ?? ""
                                        ) ?>

                                    </small>

                                </td>

                                <td>

                                    <?= date(
                                        "d M Y",
                                        strtotime($shift["shift_date"])
                                    ) ?>

                                </td>

                                <td>

                                    <?= date(
                                        "H:i",
                                        strtotime($shift["start_time"])
                                    ) ?>

                                    -

                                    <?= date(
                                        "H:i",
                                        strtotime($shift["end_time"])
                                    ) ?>

                                </td>

                                <td>
                                    <?= number_format($hours, 1) ?>h
                                </td>

                                <td>

                                    £<?= number_format(
                                        (float) ($shift["hourly_rate"] ?? 0),
                                        2
                                    ) ?>

                                </td>

                                <td>

                                    <strong>

                                        £<?= number_format(
                                            $earnings,
                                            2
                                        ) ?>

                                    </strong>

                                </td>

                                <td>

                                    <?php if ($isUpcoming): ?>

                                        <span class="badge bg-primary">
                                            Upcoming
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-success">
                                            Completed
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="text-end">

                                    <div class="d-flex
                                                justify-content-end gap-2">

                                        <a
                                            href="edit_shift.php?id=<?=
                                                (int) $shift["id"]
                                            ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit shift"
                                        >

                                            <i class="bi bi-pencil"></i>

                                        </a>

                                        <a
                                            href="delete_shift.php?id=<?=
                                                (int) $shift["id"]
                                            ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Delete shift"
                                        >

                                            <i class="bi bi-trash"></i>

                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</main>

<?php require_once("../includes/footer.php"); ?>
