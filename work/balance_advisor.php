<?php

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Analysis period and weekly targets
|--------------------------------------------------------------------------
*/

$today = new DateTimeImmutable("today");
$periodEnd = $today->modify("+6 days");

$startDate = $today->format("Y-m-d");
$endDate = $periodEnd->format("Y-m-d");

$recommendedWorkHours = 20;
$highCombinedHours = 45;
$criticalCombinedHours = 55;

/*
|--------------------------------------------------------------------------
| Load work shifts for the next seven days
|--------------------------------------------------------------------------
*/

$workQuery = $pdo->prepare(
    "SELECT
        id,
        job_title,
        employer,
        shift_date,
        start_time,
        end_time,
        hourly_rate
     FROM work_shifts
     WHERE user_id = ?
       AND shift_date BETWEEN ? AND ?
     ORDER BY shift_date ASC, start_time ASC"
);

$workQuery->execute([
    $userId,
    $startDate,
    $endDate
]);

$workShifts = $workQuery->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Calculate working hours and estimated earnings
|--------------------------------------------------------------------------
*/

$totalWorkHours = 0;
$estimatedEarnings = 0;
$workDays = [];
$shiftDetails = [];

foreach ($workShifts as $shift) {

    $start = new DateTimeImmutable(
        $shift["shift_date"] . " " . $shift["start_time"]
    );

    $end = new DateTimeImmutable(
        $shift["shift_date"] . " " . $shift["end_time"]
    );

    // An end time earlier than the start means an overnight shift.
    if ($end < $start) {
        $end = $end->modify("+1 day");
    }

    $hours = (
        $end->getTimestamp() - $start->getTimestamp()
    ) / 3600;

    $totalWorkHours += $hours;

    $estimatedEarnings +=
        $hours * (float) ($shift["hourly_rate"] ?? 0);

    $workDays[$shift["shift_date"]] = true;

    $shift["calculated_hours"] = $hours;
    $shiftDetails[] = $shift;
}

/*
|--------------------------------------------------------------------------
| Load outstanding academic assignments
|--------------------------------------------------------------------------
|
| Overdue assignments are included because they still require
| attention. The workload estimate assumes estimated_hours
| represents the remaining work required.
|
*/

$assignmentQuery = $pdo->prepare(
    "SELECT
        id,
        title,
        module_name,
        due_date,
        priority,
        status,
        estimated_hours
     FROM assignments
     WHERE user_id = ?
       AND status <> 'completed'
       AND due_date <= ?
     ORDER BY due_date ASC"
);

$assignmentQuery->execute([
    $userId,
    $endDate
]);

$assignments = $assignmentQuery->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Calculate study workload and deadline pressure
|--------------------------------------------------------------------------
*/

$totalStudyHours = 0;
$urgentAssignments = 0;
$overdueAssignments = 0;
$deadlineDays = [];

foreach ($assignments as &$assignment) {

    $hours = max(
        0,
        (float) ($assignment["estimated_hours"] ?? 0)
    );

    $totalStudyHours += $hours;

    $dueDate = new DateTimeImmutable(
        $assignment["due_date"]
    );

    $daysUntilDue = (int) $today
        ->diff($dueDate)
        ->format("%r%a");

    $assignment["days_until_due"] = $daysUntilDue;

    if ($daysUntilDue < 0) {
        $overdueAssignments++;
        $urgentAssignments++;
    } elseif ($daysUntilDue <= 3) {
        $urgentAssignments++;
    }

    if ($daysUntilDue >= 0) {
        $deadlineDays[$assignment["due_date"]] = true;
    }
}

unset($assignment);

/*
|--------------------------------------------------------------------------
| Detect work and assignment deadline conflicts
|--------------------------------------------------------------------------
|
| A conflict means a shift and assignment deadline occur
| on the same date. This does not automatically mean the
| student cannot complete the assignment.
|
*/

$conflictDates = array_intersect_key(
    $workDays,
    $deadlineDays
);

$conflictCount = count($conflictDates);

/*
|--------------------------------------------------------------------------
| Calculate workload indicators
|--------------------------------------------------------------------------
*/

$combinedHours = $totalWorkHours + $totalStudyHours;

$workPercentage = $combinedHours > 0
    ? ($totalWorkHours / $combinedHours) * 100
    : 0;

$studyPercentage = $combinedHours > 0
    ? ($totalStudyHours / $combinedHours) * 100
    : 0;

$workLimitPercentage =
    ($totalWorkHours / $recommendedWorkHours) * 100;

/*
|--------------------------------------------------------------------------
| Rule-based risk scoring
|--------------------------------------------------------------------------
|
| These thresholds are configurable project rules,
| not medical, academic or legal safety limits.
|
*/

$riskScore = 0;
$riskReasons = [];

if ($combinedHours >= $criticalCombinedHours) {

    $riskScore += 40;

    $riskReasons[] =
        "Recorded work hours plus outstanding estimated study effort are very high.";

} elseif ($combinedHours >= $highCombinedHours) {

    $riskScore += 25;

    $riskReasons[] =
        "Recorded work hours plus outstanding estimated study effort are relatively high.";
}

if ($totalWorkHours > $recommendedWorkHours) {

    $riskScore += 20;

    $riskReasons[] =
        "Your scheduled work exceeds the project's suggested "
        . "20-hour planning target.";
}

if ($urgentAssignments >= 2) {

    $riskScore += 20;

    $riskReasons[] =
        "Several assignments require attention within three days "
        . "or are already overdue.";

} elseif ($urgentAssignments === 1) {

    $riskScore += 10;
}

if ($overdueAssignments > 0) {

    $riskScore += 15;

    $riskReasons[] =
        "You have overdue academic work.";
}

if ($conflictCount > 0) {

    $riskScore += min(20, $conflictCount * 10);

    $riskReasons[] =
        "At least one assignment deadline falls on a scheduled workday.";
}

$riskScore = min(100, $riskScore);

/*
|--------------------------------------------------------------------------
| Determine balance status
|--------------------------------------------------------------------------
*/

if ($riskScore >= 70) {

    $balanceStatus = "Critical";
    $statusClass = "danger";
    $statusIcon = "bi-exclamation-octagon-fill";

} elseif ($riskScore >= 40) {

    $balanceStatus = "High Pressure";
    $statusClass = "warning";
    $statusIcon = "bi-exclamation-triangle-fill";

} elseif ($riskScore >= 20) {

    $balanceStatus = "Moderate";
    $statusClass = "info";
    $statusIcon = "bi-info-circle-fill";

} else {

    $balanceStatus = "Balanced";
    $statusClass = "success";
    $statusIcon = "bi-check-circle-fill";
}

/*
|--------------------------------------------------------------------------
| Generate personalised recommendations
|--------------------------------------------------------------------------
*/

$recommendations = [];

if ($totalWorkHours > $recommendedWorkHours) {

    $recommendations[] = [
        "title" => "Review your working hours",
        "icon" => "bi-clock-history",
        "message" =>
            "You have " . number_format($totalWorkHours, 1)
            . " working hours scheduled. Consider whether your "
            . "remaining study commitments allow enough preparation time."
    ];
}

if ($urgentAssignments > 0) {

    $recommendations[] = [
        "title" => "Prioritise urgent assignments",
        "icon" => "bi-journal-check",
        "message" =>
            "You have " . $urgentAssignments
            . " urgent or overdue assignment(s). Start with the "
            . "nearest deadline and review your Study Planner."
    ];
}

if ($conflictCount > 0) {

    $recommendations[] = [
        "title" => "Check deadline conflicts",
        "icon" => "bi-calendar-x",
        "message" =>
            $conflictCount
            . " date(s) contain both a work shift and an academic "
            . "deadline. Try to finish those assignments earlier."
    ];
}

if ($combinedHours >= $highCombinedHours) {

    $recommendations[] = [
        "title" => "Avoid overloading your week",
        "icon" => "bi-heart-pulse",
        "message" =>
            "Your recorded workload totals "
            . number_format($combinedHours, 1)
            . " hours. Reserve time for sleep, meals, travel "
            . "and personal activities, which are not included "
            . "in this calculation."
    ];
}

if (
    empty($recommendations) &&
    ($totalWorkHours > 0 || $totalStudyHours > 0)
) {

    $recommendations[] = [
        "title" => "Maintain your current balance",
        "icon" => "bi-check2-circle",
        "message" =>
            "No major workload warning was detected using the "
            . "current planning rules. Continue updating your "
            . "work shifts and academic estimates."
    ];
}

if (
    $totalWorkHours == 0 &&
    $totalStudyHours == 0
) {

    $balanceStatus = "No Data";
    $statusClass = "secondary";
    $statusIcon = "bi-dash-circle";

    $recommendations[] = [
        "title" => "Add your schedule",
        "icon" => "bi-calendar-plus",
        "message" =>
            "Add work shifts and upcoming academic assignments "
            . "to generate a meaningful balance assessment."
    ];
}

/*
|--------------------------------------------------------------------------
| Prepare seven-day workload overview
|--------------------------------------------------------------------------
*/

$dailyWork = [];
$dailyDeadlines = [];

foreach ($shiftDetails as $shift) {

    $date = $shift["shift_date"];

    $dailyWork[$date] =
        ($dailyWork[$date] ?? 0)
        + $shift["calculated_hours"];
}

foreach ($assignments as $assignment) {

    $date = $assignment["due_date"];

    if ($date >= $startDate && $date <= $endDate) {

        $dailyDeadlines[$date] =
            ($dailyDeadlines[$date] ?? 0) + 1;
    }
}

?>

<?php require_once("../includes/header.php"); ?>
<?php require_once("../includes/sidebar.php"); ?>

<main class="main-content">

    <div class="container-fluid">

        <div class="page-heading d-flex justify-content-between
                    align-items-start flex-wrap gap-3">

            <div>

                <div class="page-kicker">
                    INTELLIGENT STUDENT PLANNING
                </div>

                <h1 class="mb-2">
                    Work-Study Balance Advisor
                </h1>

                <p class="text-muted mb-0">
                    Analyse your upcoming work shifts and academic
                    workload to identify scheduling pressure.
                </p>

            </div>

            <div class="d-flex gap-2 flex-wrap">

                <a href="index.php"
                   class="btn btn-outline-secondary">

                    <i class="bi bi-briefcase me-2"></i>
                    Work Management

                </a>

                <a href="../academics/study_planner.php"
                   class="btn btn-outline-primary">

                    <i class="bi bi-journal-check me-2"></i>
                    Study Planner

                </a>

            </div>

        </div>

        <!-- Analysis status -->

        <div class="content-card mt-4">

            <div class="p-4">

                <div class="row align-items-center g-4">

                    <div class="col-lg-8">

                        <div class="page-kicker mb-2">
                            SEVEN-DAY BALANCE ASSESSMENT
                        </div>

                        <h3 class="mb-3">

                            <span class="badge bg-<?= $statusClass ?>">

                                <i class="bi <?= $statusIcon ?> me-2"></i>

                                <?= htmlspecialchars($balanceStatus) ?>

                            </span>

                        </h3>

                        <p class="text-muted mb-0">
                            <?= htmlspecialchars(
                                $today->format("d M Y")
                            ) ?>
                            —
                            <?= htmlspecialchars(
                                $periodEnd->format("d M Y")
                            ) ?>
                        </p>

                    </div>

                    <div class="col-lg-4 text-lg-end">

                        <div class="text-muted small">
                            Workload Risk Score
                        </div>

                        <h1 class="mb-0">
                            <?= $riskScore ?><small class="fs-5">/100</small>
                        </h1>

                    </div>

                </div>

                <div class="progress mt-4" style="height: 12px;">

                    <div class="progress-bar bg-<?= $statusClass ?>"
                         style="width: <?= $riskScore ?>%;"></div>

                </div>

                <div class="small text-muted mt-3">
                    This is a rule-based planning indicator, not a
                    measurement of personal health or wellbeing.
                    The 20-hour work target is a project planning
                    guideline, not a visa or legal limit. Study hours are estimates of unfinished work, not booked study sessions.
                </div>

            </div>

        </div>

        <!-- Summary statistics -->

        <div class="row g-4 mt-1">

            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">
                        <i class="bi bi-briefcase"></i>
                    </div>

                    <div>

                        <div class="text-muted small">
                            Scheduled Work
                        </div>

                        <h3 class="mb-0">
                            <?= number_format($totalWorkHours, 1) ?> hrs
                        </h3>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">
                        <i class="bi bi-book"></i>
                    </div>

                    <div>

                        <div class="text-muted small">
                            Estimated Study Workload
                        </div>

                        <h3 class="mb-0">
                            <?= number_format($totalStudyHours, 1) ?> hrs
                        </h3>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">
                        <i class="bi bi-hourglass-split"></i>
                    </div>

                    <div>

                        <div class="text-muted small">
                            Combined Workload
                        </div>

                        <h3 class="mb-0">
                            <?= number_format($combinedHours, 1) ?> hrs
                        </h3>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">
                        <i class="bi bi-calendar-x"></i>
                    </div>

                    <div>

                        <div class="text-muted small">
                            Deadline Conflict Days
                        </div>

                        <h3 class="mb-0">
                            <?= $conflictCount ?>
                        </h3>

                    </div>

                </div>

            </div>

        </div>

        <!-- Analysis and recommendations -->

        <div class="row g-4 mt-1">

            <div class="col-xl-7">

                <div class="content-card h-100">

                    <div class="content-card-header">

                        <div>

                            <h4 class="mb-1">
                                <i class="bi bi-lightbulb me-2"></i>
                                Smart Recommendations
                            </h4>

                            <p class="text-muted mb-0">
                                Suggestions based on your recorded schedule.
                            </p>

                        </div>

                    </div>

                    <div class="p-4">

                        <div class="row g-3">

                            <?php foreach (
                                $recommendations as $recommendation
                            ): ?>

                                <div class="col-12">

                                    <div class="border rounded p-3">

                                        <div class="d-flex gap-3">

                                            <div class="stat-icon">
                                                <i class="bi <?=
                                                    htmlspecialchars(
                                                        $recommendation["icon"]
                                                    )
                                                ?>"></i>
                                            </div>

                                            <div>

                                                <h6 class="mb-2">
                                                    <?= htmlspecialchars(
                                                        $recommendation["title"]
                                                    ) ?>
                                                </h6>

                                                <p class="text-muted mb-0">
                                                    <?= htmlspecialchars(
                                                        $recommendation["message"]
                                                    ) ?>
                                                </p>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-xl-5">

                <div class="content-card h-100">

                    <div class="content-card-header">

                        <h4 class="mb-0">
                            Workload Distribution
                        </h4>

                    </div>

                    <div class="p-4">

                        <div class="d-flex justify-content-between mb-2">

                            <span>Part-time work</span>

                            <strong>
                                <?= number_format($workPercentage, 1) ?>%
                            </strong>

                        </div>

                        <div class="progress mb-4" style="height: 12px;">

                            <div class="progress-bar bg-success"
                                 style="width: <?= $workPercentage ?>%;">
                            </div>

                        </div>

                        <div class="d-flex justify-content-between mb-2">

                            <span>Academic workload</span>

                            <strong>
                                <?= number_format($studyPercentage, 1) ?>%
                            </strong>

                        </div>

                        <div class="progress mb-4" style="height: 12px;">

                            <div class="progress-bar"
                                 style="width: <?= $studyPercentage ?>%;">
                            </div>

                        </div>

                        <hr>

                        <div class="d-flex justify-content-between mb-3">

                            <span class="text-muted">
                                Work shifts
                            </span>

                            <strong>
                                <?= count($workShifts) ?>
                            </strong>

                        </div>

                        <div class="d-flex justify-content-between mb-3">

                            <span class="text-muted">
                                Outstanding assignments
                            </span>

                            <strong>
                                <?= count($assignments) ?>
                            </strong>

                        </div>

                        <div class="d-flex justify-content-between mb-3">

                            <span class="text-muted">
                                Urgent assignments
                            </span>

                            <strong class="text-danger">
                                <?= $urgentAssignments ?>
                            </strong>

                        </div>

                        <div class="d-flex justify-content-between">

                            <span class="text-muted">
                                Estimated shift earnings
                            </span>

                            <strong class="text-success">
                                £<?= number_format($estimatedEarnings, 2) ?>
                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- Daily schedule overview -->

        <div class="content-card mt-4">

            <div class="content-card-header">

                <div>

                    <h4 class="mb-1">
                        Next Seven Days
                    </h4>

                    <p class="text-muted mb-0">
                        Scheduled working hours and assignment deadlines.
                    </p>

                </div>

                <i class="bi bi-calendar-week fs-4"></i>

            </div>

            <div class="p-4">

                <div class="row g-3">

                    <?php for ($day = 0; $day < 7; $day++): ?>

                        <?php

                        $date = $today->modify("+{$day} days");
                        $dateKey = $date->format("Y-m-d");

                        $hours = $dailyWork[$dateKey] ?? 0;
                        $deadlines = $dailyDeadlines[$dateKey] ?? 0;

                        $hasConflict =
                            $hours > 0 && $deadlines > 0;

                        ?>

                        <div class="col-lg col-md-4 col-sm-6">

                            <div class="border rounded p-3 h-100 <?=
                                $hasConflict
                                    ? "border-warning"
                                    : ""
                            ?>">

                                <div class="text-muted small">
                                    <?= $date->format("D") ?>
                                </div>

                                <h5 class="mb-3">
                                    <?= $date->format("d M") ?>
                                </h5>

                                <div class="small mb-2">

                                    <i class="bi bi-briefcase me-1"></i>

                                    <?= number_format($hours, 1) ?>h work

                                </div>

                                <div class="small mb-2">

                                    <i class="bi bi-journal-text me-1"></i>

                                    <?= $deadlines ?>
                                    deadline(s)

                                </div>

                                <?php if ($hasConflict): ?>

                                    <span class="badge bg-warning text-dark">
                                        Check conflict
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endfor; ?>

                </div>

            </div>

        </div>

        <!-- Upcoming assignments -->

        <div class="content-card mt-4">

            <div class="content-card-header">

                <div>

                    <h4 class="mb-1">
                        Academic Priorities
                    </h4>

                    <p class="text-muted mb-0">
                        Outstanding work due within this period
                        or already overdue.
                    </p>

                </div>

                <a href="../academics/study_planner.php"
                   class="btn btn-sm btn-outline-primary">
                    Open Study Planner
                </a>

            </div>

            <div class="p-0">

                <?php if (empty($assignments)): ?>

                    <div class="empty-state p-4">

                        <i class="bi bi-check-circle"></i>

                        <h5>No upcoming academic deadlines</h5>

                        <p>
                            There are no outstanding assignments
                            due during this analysis period.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead>

                                <tr>
                                    <th class="ps-4">Assignment</th>
                                    <th>Deadline</th>
                                    <th>Estimated Hours</th>
                                    <th>Priority</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach (
                                    $assignments as $assignment
                                ): ?>

                                    <tr>

                                        <td class="ps-4">

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $assignment["title"]
                                                ) ?>
                                            </strong>

                                            <div class="small text-muted">
                                                <?= htmlspecialchars(
                                                    $assignment["module_name"] ?? ""
                                                ) ?>
                                            </div>

                                        </td>

                                        <td>

                                            <?= date(
                                                "d M Y",
                                                strtotime(
                                                    $assignment["due_date"]
                                                )
                                            ) ?>

                                            <?php if (
                                                $assignment["days_until_due"] < 0
                                            ): ?>

                                                <span class="badge bg-danger">
                                                    Overdue
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?= number_format(
                                                (float) $assignment["estimated_hours"],
                                                1
                                            ) ?>h
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $assignment["priority"]
                                                )
                                            ) ?>
                                        </td>

                                        <td class="text-end pe-4">

                                            <a
                                                href="../academics/edit_task.php?id=<?=
                                                    (int) $assignment["id"]
                                                ?>"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                View
                                            </a>

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

</main>

<?php require_once("../includes/footer.php"); ?>