
<?php

/*
|--------------------------------------------------------------------------
| Analytics & Insights
|--------------------------------------------------------------------------
| Combines finance, academic, work and reminder data into one
| integrated overview for the currently logged-in student.
|
| All insights are generated using transparent, rule-based logic.
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Display helpers
|--------------------------------------------------------------------------
*/

function analyticsEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function analyticsMoney($value): string
{
    return "£" . number_format((float) $value, 2);
}

function analyticsShiftHours(
    string $date,
    string $startTime,
    string $endTime
): float {

    $start = new DateTimeImmutable(
        $date . " " . $startTime
    );

    $end = new DateTimeImmutable(
        $date . " " . $endTime
    );

    if ($end <= $start) {
        $end = $end->modify("+1 day");
    }

    return max(
        0,
        ($end->getTimestamp() - $start->getTimestamp()) / 3600
    );
}

/*
|--------------------------------------------------------------------------
| Analysis dates
|--------------------------------------------------------------------------
*/

$today = new DateTimeImmutable("today");

$currentMonthStart = $today->format("Y-m-01");
$currentMonthEnd = $today->format("Y-m-t");

$sevenDaysEnd = $today->modify("+6 days")->format("Y-m-d");

$sixMonthsStart = $today
    ->modify("first day of this month")
    ->modify("-5 months")
    ->format("Y-m-01");

/*
|--------------------------------------------------------------------------
| Finance: Overall totals
|--------------------------------------------------------------------------
*/

$financeQuery = $pdo->prepare(
    "SELECT
        COALESCE(
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END),
            0
        ) AS total_income,

        COALESCE(
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END),
            0
        ) AS total_expenses

     FROM transactions
     WHERE user_id = ?"
);

$financeQuery->execute([$userId]);

$finance = $financeQuery->fetch(PDO::FETCH_ASSOC);

$totalIncome = (float) ($finance["total_income"] ?? 0);
$totalExpenses = (float) ($finance["total_expenses"] ?? 0);
$overallBalance = $totalIncome - $totalExpenses;

/*
|--------------------------------------------------------------------------
| Finance: Current month
|--------------------------------------------------------------------------
*/

$monthlyFinanceQuery = $pdo->prepare(
    "SELECT
        COALESCE(
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END),
            0
        ) AS income,

        COALESCE(
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END),
            0
        ) AS expenses

     FROM transactions
     WHERE user_id = ?
       AND transaction_date BETWEEN ? AND ?"
);

$monthlyFinanceQuery->execute([
    $userId,
    $currentMonthStart,
    $currentMonthEnd
]);

$monthlyFinance = $monthlyFinanceQuery->fetch(PDO::FETCH_ASSOC);

$monthIncome = (float) ($monthlyFinance["income"] ?? 0);
$monthExpenses = (float) ($monthlyFinance["expenses"] ?? 0);
$monthNet = $monthIncome - $monthExpenses;

/*
|--------------------------------------------------------------------------
| Finance: Monthly budget
|--------------------------------------------------------------------------
*/

$budgetQuery = $pdo->prepare(
    "SELECT budget_amount
     FROM monthly_budgets
     WHERE user_id = ?
       AND budget_month = ?
     LIMIT 1"
);

$budgetQuery->execute([
    $userId,
    $currentMonthStart
]);

$budgetAmount = (float) ($budgetQuery->fetchColumn() ?: 0);

$budgetUsage = $budgetAmount > 0
    ? ($monthExpenses / $budgetAmount) * 100
    : 0;

$budgetRemaining = $budgetAmount - $monthExpenses;

/*
|--------------------------------------------------------------------------
| Finance: Six-month income and expense trends
|--------------------------------------------------------------------------
*/

$trendQuery = $pdo->prepare(
    "SELECT
        DATE_FORMAT(transaction_date, '%Y-%m') AS month_key,
        type,
        SUM(amount) AS total

     FROM transactions
     WHERE user_id = ?
       AND transaction_date BETWEEN ? AND ?
     GROUP BY DATE_FORMAT(transaction_date, '%Y-%m'), type
     ORDER BY month_key ASC"
);

$trendQuery->execute([
    $userId,
    $sixMonthsStart,
    $currentMonthEnd
]);

$trendRows = $trendQuery->fetchAll(PDO::FETCH_ASSOC);

$monthlyTrends = [];

for ($i = 5; $i >= 0; $i--) {

    $monthDate = $today
        ->modify("first day of this month")
        ->modify("-{$i} months");

    $key = $monthDate->format("Y-m");

    $monthlyTrends[$key] = [
        "label" => $monthDate->format("M Y"),
        "income" => 0,
        "expenses" => 0
    ];
}

foreach ($trendRows as $row) {

    $key = $row["month_key"];

    if (!isset($monthlyTrends[$key])) {
        continue;
    }

    if ($row["type"] === "income") {
        $monthlyTrends[$key]["income"] =
            (float) $row["total"];
    } elseif ($row["type"] === "expense") {
        $monthlyTrends[$key]["expenses"] =
            (float) $row["total"];
    }
}

$maxTrendValue = 1;

foreach ($monthlyTrends as $month) {

    $maxTrendValue = max(
        $maxTrendValue,
        $month["income"],
        $month["expenses"]
    );
}

/*
|--------------------------------------------------------------------------
| Finance: Current month spending categories
|--------------------------------------------------------------------------
*/

$categoryQuery = $pdo->prepare(
    "SELECT
        category,
        SUM(amount) AS total

     FROM transactions
     WHERE user_id = ?
       AND type = 'expense'
       AND transaction_date BETWEEN ? AND ?
     GROUP BY category
     ORDER BY total DESC"
);

$categoryQuery->execute([
    $userId,
    $currentMonthStart,
    $currentMonthEnd
]);

$spendingCategories = $categoryQuery->fetchAll(
    PDO::FETCH_ASSOC
);

$largestCategory = $spendingCategories[0] ?? null;

/*
|--------------------------------------------------------------------------
| Academics: Assignment statistics
|--------------------------------------------------------------------------
*/

$academicQuery = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_tasks,

        SUM(
            CASE WHEN status = 'completed' THEN 1 ELSE 0 END
        ) AS completed_tasks,

        SUM(
            CASE WHEN status <> 'completed' THEN 1 ELSE 0 END
        ) AS pending_tasks,

        SUM(
            CASE
                WHEN status <> 'completed'
                 AND due_date < CURDATE()
                THEN 1
                ELSE 0
            END
        ) AS overdue_tasks,

        SUM(
            CASE
                WHEN status <> 'completed'
                 AND due_date BETWEEN CURDATE()
                     AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                THEN 1
                ELSE 0
            END
        ) AS upcoming_tasks

     FROM assignments
     WHERE user_id = ?"
);

$academicQuery->execute([$userId]);

$academics = $academicQuery->fetch(PDO::FETCH_ASSOC);

$totalTasks = (int) ($academics["total_tasks"] ?? 0);
$completedTasks = (int) ($academics["completed_tasks"] ?? 0);
$pendingTasks = (int) ($academics["pending_tasks"] ?? 0);
$overdueTasks = (int) ($academics["overdue_tasks"] ?? 0);
$upcomingTasks = (int) ($academics["upcoming_tasks"] ?? 0);

$academicCompletion = $totalTasks > 0
    ? ($completedTasks / $totalTasks) * 100
    : 0;

/*
|--------------------------------------------------------------------------
| Academics: Outstanding assignment workload
|--------------------------------------------------------------------------
*/

$studyHoursQuery = $pdo->prepare(
    "SELECT
        COALESCE(SUM(estimated_hours), 0)
     FROM assignments
     WHERE user_id = ?
       AND status <> 'completed'
       AND due_date <= ?"
);

$studyHoursQuery->execute([
    $userId,
    $sevenDaysEnd
]);

$upcomingStudyHours = max(
    0,
    (float) $studyHoursQuery->fetchColumn()
);

/*
|--------------------------------------------------------------------------
| Academics: Nearest outstanding deadlines
|--------------------------------------------------------------------------
*/

$deadlineQuery = $pdo->prepare(
    "SELECT
        id,
        title,
        module_name,
        due_date,
        priority,
        estimated_hours

     FROM assignments
     WHERE user_id = ?
       AND status <> 'completed'
     ORDER BY due_date ASC
     LIMIT 5"
);

$deadlineQuery->execute([$userId]);

$nearestDeadlines = $deadlineQuery->fetchAll(
    PDO::FETCH_ASSOC
);

/*
|--------------------------------------------------------------------------
| Work: All recorded shifts
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
     ORDER BY shift_date ASC, start_time ASC"
);

$workQuery->execute([$userId]);

$workShifts = $workQuery->fetchAll(PDO::FETCH_ASSOC);

$totalShifts = count($workShifts);

$completedWorkHours = 0;
$completedEarnings = 0;

$nextSevenWorkHours = 0;
$nextSevenShiftCount = 0;

$workDays = [];

$now = new DateTimeImmutable();

foreach ($workShifts as $shift) {

    $hours = analyticsShiftHours(
        $shift["shift_date"],
        $shift["start_time"],
        $shift["end_time"]
    );

    $earnings = $hours *
        (float) ($shift["hourly_rate"] ?? 0);

    $shiftStart = new DateTimeImmutable(
        $shift["shift_date"] . " " . $shift["start_time"]
    );

    $shiftEnd = new DateTimeImmutable(
        $shift["shift_date"] . " " . $shift["end_time"]
    );

    if ($shiftEnd <= $shiftStart) {
        $shiftEnd = $shiftEnd->modify("+1 day");
    }

    if ($shiftEnd <= $now) {

        $completedWorkHours += $hours;
        $completedEarnings += $earnings;
    }

    if (
        $shift["shift_date"] >= $today->format("Y-m-d") &&
        $shift["shift_date"] <= $sevenDaysEnd
    ) {

        $nextSevenWorkHours += $hours;
        $nextSevenShiftCount++;

        $workDays[$shift["shift_date"]] = true;
    }
}

/*
|--------------------------------------------------------------------------
| Work: Identify deadline conflicts
|--------------------------------------------------------------------------
*/

$conflictQuery = $pdo->prepare(
    "SELECT DISTINCT due_date
     FROM assignments
     WHERE user_id = ?
       AND status <> 'completed'
       AND due_date BETWEEN ? AND ?"
);

$conflictQuery->execute([
    $userId,
    $today->format("Y-m-d"),
    $sevenDaysEnd
]);

$deadlineDates = $conflictQuery->fetchAll(
    PDO::FETCH_COLUMN
);

$conflictCount = 0;

foreach ($deadlineDates as $date) {

    if (isset($workDays[$date])) {
        $conflictCount++;
    }
}

$combinedWorkload =
    $nextSevenWorkHours + $upcomingStudyHours;

/*
|--------------------------------------------------------------------------
| Reminders: Completion statistics
|--------------------------------------------------------------------------
*/

$reminderQuery = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_reminders,

        SUM(
            CASE WHEN status = 'completed' THEN 1 ELSE 0 END
        ) AS completed_reminders,

        SUM(
            CASE WHEN status = 'pending' THEN 1 ELSE 0 END
        ) AS pending_reminders,

        SUM(
            CASE
                WHEN status = 'pending'
                 AND reminder_date < CURDATE()
                THEN 1
                ELSE 0
            END
        ) AS overdue_reminders

     FROM reminders
     WHERE user_id = ?"
);

$reminderQuery->execute([$userId]);

$reminderStats = $reminderQuery->fetch(PDO::FETCH_ASSOC);

$totalReminders = (int) ($reminderStats["total_reminders"] ?? 0);
$completedReminders = (int) ($reminderStats["completed_reminders"] ?? 0);
$pendingReminders = (int) ($reminderStats["pending_reminders"] ?? 0);
$overdueReminders = (int) ($reminderStats["overdue_reminders"] ?? 0);

$reminderCompletion = $totalReminders > 0
    ? ($completedReminders / $totalReminders) * 100
    : 0;

/*
|--------------------------------------------------------------------------
| Rule-based intelligent insights
|--------------------------------------------------------------------------
|
| These are explainable planning recommendations rather than
| predictions produced by a machine-learning model.
|--------------------------------------------------------------------------
*/

$insights = [];

/*
 * Budget insight
 */

if ($budgetAmount <= 0) {

    $insights[] = [
        "title" => "Set a Monthly Budget",
        "message" =>
            "You have not set a budget for this month. Add one "
            . "to receive personalised spending guidance.",
        "icon" => "bi-wallet2",
        "class" => "secondary",
        "link" => "../finance/budget_advisor.php",
        "action" => "Set Budget"
    ];

} elseif ($monthExpenses > $budgetAmount) {

    $insights[] = [
        "title" => "Monthly Budget Exceeded",
        "message" =>
            "Your expenses exceed your monthly budget by "
            . analyticsMoney($monthExpenses - $budgetAmount)
            . ". Review your spending categories.",
        "icon" => "bi-exclamation-triangle",
        "class" => "danger",
        "link" => "../finance/budget_advisor.php",
        "action" => "Review Budget"
    ];

} elseif ($budgetUsage >= 80) {

    $insights[] = [
        "title" => "Budget Usage Is High",
        "message" =>
            "You have used "
            . number_format($budgetUsage, 1)
            . "% of your monthly budget. Check your "
            . "spending prediction before making large purchases.",
        "icon" => "bi-pie-chart",
        "class" => "warning",
        "link" => "../finance/spending_prediction.php",
        "action" => "View Prediction"
    ];
}

/*
 * Academic deadline insight
 */

if ($overdueTasks > 0) {

    $insights[] = [
        "title" => "Overdue Academic Tasks",
        "message" =>
            "You have " . $overdueTasks
            . " overdue assignment(s). Prioritise these "
            . "in your Smart Study Planner.",
        "icon" => "bi-journal-x",
        "class" => "danger",
        "link" => "../academics/study_planner.php",
        "action" => "Open Study Planner"
    ];

} elseif ($upcomingTasks >= 3) {

    $insights[] = [
        "title" => "Busy Academic Week",
        "message" =>
            "You have " . $upcomingTasks
            . " outstanding assignments due within "
            . "the next seven days.",
        "icon" => "bi-journal-bookmark",
        "class" => "warning",
        "link" => "../academics/study_planner.php",
        "action" => "Plan Study Time"
    ];
}

/*
 * Work-study insight
 */

if ($combinedWorkload >= 45) {

    $insights[] = [
        "title" => "High Combined Workload",
        "message" =>
            "Your next seven days contain approximately "
            . number_format($combinedWorkload, 1)
            . " hours of recorded work and estimated "
            . "academic tasks. Review your balance.",
        "icon" => "bi-activity",
        "class" => "warning",
        "link" => "../work/balance_advisor.php",
        "action" => "Review Balance"
    ];
}

if ($conflictCount > 0) {

    $insights[] = [
        "title" => "Work and Deadline Conflicts",
        "message" =>
            $conflictCount
            . " date(s) have both a scheduled work shift "
            . "and an academic deadline.",
        "icon" => "bi-calendar-x",
        "class" => "warning",
        "link" => "../work/balance_advisor.php",
        "action" => "Check Conflicts"
    ];
}

/*
 * Reminder insight
 */

if ($overdueReminders > 0) {

    $insights[] = [
        "title" => "Overdue Reminders",
        "message" =>
            "You have " . $overdueReminders
            . " overdue reminder(s). Review them to "
            . "keep your personal tasks organised.",
        "icon" => "bi-bell",
        "class" => "info",
        "link" => "../reminders/index.php",
        "action" => "View Reminders"
    ];
}

/*
 * Spending concentration insight
 */

if ($largestCategory && $monthExpenses > 0) {

    $largestShare =
        ((float) $largestCategory["total"] / $monthExpenses) * 100;

    if ($largestShare >= 40) {

        $insights[] = [
            "title" => "Spending Concentration",
            "message" =>
                ucfirst($largestCategory["category"])
                . " accounts for "
                . number_format($largestShare, 1)
                . "% of this month's expenses. "
                . "Review whether this matches your priorities.",
            "icon" => "bi-bar-chart",
            "class" => "info",
            "link" => "../finance/index.php",
            "action" => "View Spending"
        ];
    }
}

/*
 * Positive insight when no warnings are detected
 */

if (empty($insights)) {

    $insights[] = [
        "title" => "No Major Warnings Detected",
        "message" =>
            "Your recorded data has not triggered any of "
            . "the current warning rules. Continue keeping "
            . "your transactions, assignments and shifts updated.",
        "icon" => "bi-check-circle",
        "class" => "success",
        "link" => "../index.php",
        "action" => "Go to Dashboard"
    ];
}

/*
|--------------------------------------------------------------------------
| Overall planning indicator
|--------------------------------------------------------------------------
|
| The score measures the number and severity of warning rules.
| It is not a grade or a measure of student wellbeing.
|--------------------------------------------------------------------------
*/

$attentionScore = 0;

foreach ($insights as $insight) {

    if ($insight["class"] === "danger") {
        $attentionScore += 25;
    } elseif ($insight["class"] === "warning") {
        $attentionScore += 15;
    } elseif ($insight["class"] === "info") {
        $attentionScore += 8;
    } elseif ($insight["class"] === "secondary") {
        $attentionScore += 5;
    }
}

$attentionScore = min(100, $attentionScore);

if ($attentionScore >= 60) {

    $attentionLabel = "High Attention";
    $attentionClass = "danger";

} elseif ($attentionScore >= 30) {

    $attentionLabel = "Needs Attention";
    $attentionClass = "warning";

} elseif ($attentionScore > 0) {

    $attentionLabel = "Monitor";
    $attentionClass = "info";

} else {

    $attentionLabel = "No Alerts";
    $attentionClass = "success";
}

$pageTitle = "Analytics & Insights";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>

<main class="main-content">

    <!-- Page heading -->

    <div class="page-heading d-flex
                justify-content-between align-items-start
                flex-wrap gap-3">

        <div>

            <p class="page-kicker">
                STUDENT PERFORMANCE
            </p>

            <h1 class="mb-2">
                Analytics & Insights
            </h1>

            <p class="text-muted mb-0">
                Understand your finances, academic progress,
                work commitments and personal productivity.
            </p>

        </div>

        <a href="../index.php"
           class="btn btn-outline-primary">

            <i class="bi bi-grid me-2"></i>
            Dashboard

        </a>

    </div>

    <!-- Overall analysis banner -->

    <div class="content-card mb-4">

        <div class="row g-4 align-items-center">

            <div class="col-lg-8">

                <p class="page-kicker mb-2">
                    INTEGRATED STUDENT INSIGHTS
                </p>

                <h3 class="mb-3">
                    Your Student Life Overview
                </h3>

                <p class="text-muted mb-3">
                    This analysis combines information from
                    Finance, Academics, Work Management and Reminders.
                </p>

                <span class="badge bg-<?= $attentionClass ?> p-2">

                    <i class="bi bi-activity me-1"></i>

                    <?= analyticsEscape($attentionLabel) ?>

                </span>

            </div>

            <div class="col-lg-4">

                <div class="text-lg-end">

                    <div class="text-muted small mb-1">
                        Attention Indicator
                    </div>

                    <h1 class="mb-2">
                        <?= $attentionScore ?>
                        <small class="fs-5">/100</small>
                    </h1>

                </div>

                <div class="progress" style="height: 10px;">

                    <div class="progress-bar bg-<?= $attentionClass ?>"
                         style="width: <?= $attentionScore ?>%;">
                    </div>

                </div>

                <small class="text-muted d-block mt-2">
                    Higher scores indicate more triggered
                    planning warnings, not poorer personal performance.
                </small>

            </div>

        </div>

    </div>

    <!-- Main statistics -->

    <div class="row g-3 mb-4">

        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-wallet2"></i>
                </div>

                <div>

                    <span class="text-muted small">
                        Overall Financial Balance
                    </span>

                    <h3 class="mb-1 <?= $overallBalance < 0
                        ? "text-danger"
                        : "text-success" ?>">

                        <?= analyticsMoney($overallBalance) ?>

                    </h3>

                    <small class="text-muted">
                        Recorded income minus expenses
                    </small>

                </div>

            </div>

        </div>

        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-mortarboard"></i>
                </div>

                <div>

                    <span class="text-muted small">
                        Academic Completion
                    </span>

                    <h3 class="mb-1">
                        <?= number_format($academicCompletion, 1) ?>%
                    </h3>

                    <small class="text-muted">
                        <?= $completedTasks ?> of <?= $totalTasks ?>
                        assignments completed
                    </small>

                </div>

            </div>

        </div>

        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-clock-history"></i>
                </div>

                <div>

                    <span class="text-muted small">
                        Completed Work Hours
                    </span>

                    <h3 class="mb-1">
                        <?= number_format($completedWorkHours, 1) ?>h
                    </h3>

                    <small class="text-muted">
                        <?= $totalShifts ?> total recorded shifts
                    </small>

                </div>

            </div>

        </div>

        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-bell"></i>
                </div>

                <div>

                    <span class="text-muted small">
                        Reminder Completion
                    </span>

                    <h3 class="mb-1">
                        <?= number_format($reminderCompletion, 1) ?>%
                    </h3>

                    <small class="text-muted">
                        <?= $completedReminders ?> of
                        <?= $totalReminders ?> completed
                    </small>

                </div>

            </div>

        </div>

    </div>

    <!-- Finance analysis -->

    <div class="row g-4 mb-4">

        <div class="col-xl-8">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <h3 class="mb-1">
                            Financial Trends
                        </h3>

                        <small class="text-muted">
                            Income and expenses over the last six months
                        </small>

                    </div>

                    <i class="bi bi-graph-up-arrow fs-4"></i>

                </div>

                <div class="mb-4 d-flex gap-3 flex-wrap">

                    <span class="small">
                        <i class="bi bi-circle-fill text-success me-1"></i>
                        Income
                    </span>

                    <span class="small">
                        <i class="bi bi-circle-fill text-danger me-1"></i>
                        Expenses
                    </span>

                </div>

                <?php foreach ($monthlyTrends as $trend): ?>

                    <?php

                    $incomeWidth =
                        ($trend["income"] / $maxTrendValue) * 100;

                    $expenseWidth =
                        ($trend["expenses"] / $maxTrendValue) * 100;

                    ?>

                    <div class="mb-4">

                        <div class="d-flex justify-content-between
                                    flex-wrap gap-2 mb-2">

                            <strong>
                                <?= analyticsEscape($trend["label"]) ?>
                            </strong>

                            <span class="small text-muted">

                                <span class="text-success">
                                    <?= analyticsMoney($trend["income"]) ?>
                                </span>

                                /

                                <span class="text-danger">
                                    <?= analyticsMoney($trend["expenses"]) ?>
                                </span>

                            </span>

                        </div>

                        <div class="small text-muted mb-1">
                            Income
                        </div>

                        <div class="progress mb-2" style="height: 9px;">

                            <div class="progress-bar bg-success"
                                 style="width: <?= $incomeWidth ?>%;">
                            </div>

                        </div>

                        <div class="small text-muted mb-1">
                            Expenses
                        </div>

                        <div class="progress" style="height: 9px;">

                            <div class="progress-bar bg-danger"
                                 style="width: <?= $expenseWidth ?>%;">
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

                <div class="border-top pt-3">

                    <a href="../finance/index.php"
                       class="btn btn-sm btn-outline-primary">

                        View Finance Details
                        <i class="bi bi-arrow-right ms-1"></i>

                    </a>

                </div>

            </div>

        </div>

        <!-- Monthly budget -->

        <div class="col-xl-4">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <h3 class="mb-1">
                            Monthly Budget
                        </h3>

                        <small class="text-muted">
                            <?= date("F Y") ?>
                        </small>

                    </div>

                    <i class="bi bi-pie-chart fs-4"></i>

                </div>

                <?php if ($budgetAmount > 0): ?>

                    <div class="mb-4">

                        <span class="text-muted small">
                            Budget Used
                        </span>

                        <h2 class="mb-2">
                            <?= number_format($budgetUsage, 1) ?>%
                        </h2>

                        <div class="progress" style="height: 12px;">

                            <div class="progress-bar <?=
                                $budgetUsage > 100
                                    ? "bg-danger"
                                    : ($budgetUsage >= 80
                                        ? "bg-warning"
                                        : "bg-success")
                            ?>"
                                 style="width: <?=
                                    min(100, max(0, $budgetUsage))
                                 ?>%;">
                            </div>

                        </div>

                    </div>

                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Budget
                        </span>

                        <strong>
                            <?= analyticsMoney($budgetAmount) ?>
                        </strong>

                    </div>

                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Spent
                        </span>

                        <strong>
                            <?= analyticsMoney($monthExpenses) ?>
                        </strong>

                    </div>

                    <div class="d-flex justify-content-between mb-4">

                        <span class="text-muted">
                            Remaining
                        </span>

                        <strong class="<?= $budgetRemaining < 0
                            ? "text-danger"
                            : "text-success" ?>">

                            <?= analyticsMoney($budgetRemaining) ?>

                        </strong>

                    </div>

                <?php else: ?>

                    <div class="empty-state py-4">

                        <i class="bi bi-wallet2"></i>

                        <h5>No budget set</h5>

                        <p>
                            Set a monthly budget to activate
                            budget usage analysis.
                        </p>

                    </div>

                <?php endif; ?>

                <div class="d-grid gap-2">

                    <a href="../finance/budget_advisor.php"
                       class="btn btn-primary">

                        <i class="bi bi-stars me-2"></i>
                        Budget Advisor

                    </a>

                    <a href="../finance/spending_prediction.php"
                       class="btn btn-outline-primary">

                        <i class="bi bi-graph-up-arrow me-2"></i>
                        Spending Prediction

                    </a>

                </div>

            </div>

        </div>

    </div>

    <!-- Academic and reminder analysis -->

    <div class="row g-4 mb-4">

        <div class="col-xl-6">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <h3 class="mb-1">
                            Academic Performance
                        </h3>

                        <small class="text-muted">
                            Assignment progress and deadlines
                        </small>

                    </div>

                    <i class="bi bi-mortarboard fs-4"></i>

                </div>

                <div class="d-flex justify-content-between mb-2">

                    <span>
                        Assignment Completion
                    </span>

                    <strong>
                        <?= number_format($academicCompletion, 1) ?>%
                    </strong>

                </div>

                <div class="progress mb-4" style="height: 11px;">

                    <div class="progress-bar bg-primary"
                         style="width: <?= $academicCompletion ?>%;">
                    </div>

                </div>

                <div class="row g-3 mb-4">

                    <div class="col-6">

                        <div class="dashboard-finance-box">

                            <span>
                                Completed
                            </span>

                            <h4 class="text-success">
                                <?= $completedTasks ?>
                            </h4>

                        </div>

                    </div>

                    <div class="col-6">

                        <div class="dashboard-finance-box">

                            <span>
                                Outstanding
                            </span>

                            <h4>
                                <?= $pendingTasks ?>
                            </h4>

                        </div>

                    </div>

                    <div class="col-6">

                        <div class="dashboard-finance-box">

                            <span>
                                Overdue
                            </span>

                            <h4 class="text-danger">
                                <?= $overdueTasks ?>
                            </h4>

                        </div>

                    </div>

                    <div class="col-6">

                        <div class="dashboard-finance-box">

                            <span>
                                Due Within 7 Days
                            </span>

                            <h4>
                                <?= $upcomingTasks ?>
                            </h4>

                        </div>

                    </div>

                </div>

                <a href="../academics/study_planner.php"
                   class="btn btn-outline-primary">

                    <i class="bi bi-journal-check me-2"></i>
                    Smart Study Planner

                </a>

            </div>

        </div>

        <!-- Reminder progress -->

        <div class="col-xl-6">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <h3 class="mb-1">
                            Personal Productivity
                        </h3>

                        <small class="text-muted">
                            Reminder completion and outstanding tasks
                        </small>

                    </div>

                    <i class="bi bi-check2-square fs-4"></i>

                </div>

                <div class="d-flex justify-content-between mb-2">

                    <span>
                        Reminder Completion
                    </span>

                    <strong>
                        <?= number_format($reminderCompletion, 1) ?>%
                    </strong>

                </div>

                <div class="progress mb-4" style="height: 11px;">

                    <div class="progress-bar bg-success"
                         style="width: <?= $reminderCompletion ?>%;">
                    </div>

                </div>

                <div class="row g-3 mb-4">

                    <div class="col-6">

                        <div class="dashboard-finance-box">

                            <span>
                                Total
                            </span>

                            <h4>
                                <?= $totalReminders ?>
                            </h4>

                        </div>

                    </div>

                    <div class="col-6">

                        <div class="dashboard-finance-box">

                            <span>
                                Completed
                            </span>

                            <h4 class="text-success">
                                <?= $completedReminders ?>
                            </h4>

                        </div>

                    </div>

                    <div class="col-6">

                        <div class="dashboard-finance-box">

                            <span>
                                Pending
                            </span>

                            <h4>
                                <?= $pendingReminders ?>
                            </h4>

                        </div>

                    </div>

                    <div class="col-6">

                        <div class="dashboard-finance-box">

                            <span>
                                Overdue
                            </span>

                            <h4 class="text-danger">
                                <?= $overdueReminders ?>
                            </h4>

                        </div>

                    </div>

                </div>

                <a href="../reminders/index.php"
                   class="btn btn-outline-primary">

                    <i class="bi bi-bell me-2"></i>
                    Manage Reminders

                </a>

            </div>

        </div>

    </div>

    <!-- Work-study overview -->

    <div class="content-card mb-4">

        <div class="content-card-header">

            <div>

                <p class="page-kicker mb-1">
                    CROSS-MODULE ANALYSIS
                </p>

                <h3 class="mb-1">
                    Work-Study Balance
                </h3>

                <small class="text-muted">
                    Estimated workload for the next seven days
                </small>

            </div>

            <i class="bi bi-activity fs-4"></i>

        </div>

        <div class="row g-4 align-items-center">

            <div class="col-lg-8">

                <div class="row g-3">

                    <div class="col-md-4">

                        <div class="dashboard-finance-box">

                            <span>
                                Scheduled Work
                            </span>

                            <h4>
                                <?= number_format($nextSevenWorkHours, 1) ?>h
                            </h4>

                            <small class="text-muted">
                                <?= $nextSevenShiftCount ?> shifts
                            </small>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="dashboard-finance-box">

                            <span>
                                Estimated Study
                            </span>

                            <h4>
                                <?= number_format($upcomingStudyHours, 1) ?>h
                            </h4>

                            <small class="text-muted">
                                Outstanding assignments
                            </small>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="dashboard-finance-box">

                            <span>
                                Combined Workload
                            </span>

                            <h4>
                                <?= number_format($combinedWorkload, 1) ?>h
                            </h4>

                            <small class="text-muted">
                                Excludes classes and travel
                            </small>

                        </div>

                    </div>

                </div>

                <?php if ($conflictCount > 0): ?>

                    <div class="alert alert-warning mt-3 mb-0">

                        <i class="bi bi-exclamation-triangle me-2"></i>

                        <?= $conflictCount ?>
                        work and deadline conflict day(s) detected.

                    </div>

                <?php endif; ?>

            </div>

            <div class="col-lg-4 text-lg-end">

                <a href="../work/balance_advisor.php"
                   class="btn btn-primary">

                    <i class="bi bi-stars me-2"></i>
                    Analyse Work-Study Balance

                </a>

            </div>

        </div>

    </div>

    <!-- Spending categories and academic deadlines -->

    <div class="row g-4 mb-4">

        <div class="col-xl-6">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <h3 class="mb-1">
                            Spending by Category
                        </h3>

                        <small class="text-muted">
                            Current month
                        </small>

                    </div>

                    <i class="bi bi-bar-chart fs-4"></i>

                </div>

                <?php if (empty($spendingCategories)): ?>

                    <div class="empty-state">

                        <i class="bi bi-pie-chart"></i>

                        <h5>No expenses recorded</h5>

                        <p>
                            Add expenses to see your spending patterns.
                        </p>

                    </div>

                <?php else: ?>

                    <?php foreach ($spendingCategories as $category): ?>

                        <?php

                        $amount = (float) $category["total"];

                        $percentage = $monthExpenses > 0
                            ? ($amount / $monthExpenses) * 100
                            : 0;

                        ?>

                        <div class="mb-4">

                            <div class="d-flex justify-content-between
                                        align-items-center mb-2">

                                <strong>
                                    <?= analyticsEscape(
                                        ucfirst($category["category"])
                                    ) ?>
                                </strong>

                                <span>
                                    <?= analyticsMoney($amount) ?>
                                </span>

                            </div>

                            <div class="progress" style="height: 9px;">

                                <div class="progress-bar"
                                     style="width: <?=
                                        min(100, $percentage)
                                     ?>%;">
                                </div>

                            </div>

                            <small class="text-muted">
                                <?= number_format($percentage, 1) ?>%
                                of monthly expenses
                            </small>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

        <!-- Upcoming academic deadlines -->

        <div class="col-xl-6">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <h3 class="mb-1">
                            Academic Deadlines
                        </h3>

                        <small class="text-muted">
                            Nearest outstanding assignments
                        </small>

                    </div>

                    <i class="bi bi-calendar-check fs-4"></i>

                </div>

                <?php if (empty($nearestDeadlines)): ?>

                    <div class="empty-state">

                        <i class="bi bi-check-circle"></i>

                        <h5>No outstanding assignments</h5>

                        <p>
                            Your academic deadline list is clear.
                        </p>

                    </div>

                <?php else: ?>

                    <?php foreach ($nearestDeadlines as $task): ?>

                        <?php

                        $isOverdue =
                            $task["due_date"] < $today->format("Y-m-d");

                        ?>

                        <div class="border rounded p-3 mb-3">

                            <div class="d-flex justify-content-between
                                        gap-2 flex-wrap">

                                <div>

                                    <strong class="d-block mb-1">

                                        <?= analyticsEscape($task["title"]) ?>

                                    </strong>

                                    <small class="text-muted">

                                        <?= analyticsEscape(
                                            $task["module_name"] ?? ""
                                        ) ?>

                                    </small>

                                </div>

                                <div class="text-end">

                                    <span class="badge bg-<?=
                                        $isOverdue ? "danger" : "primary"
                                    ?>">

                                        <?= $isOverdue
                                            ? "Overdue"
                                            : "Upcoming" ?>

                                    </span>

                                    <div class="small text-muted mt-2">

                                        <?= date(
                                            "d M Y",
                                            strtotime($task["due_date"])
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

                <a href="../academics/study_planner.php"
                   class="btn btn-sm btn-outline-primary">

                    View All Academic Priorities

                </a>

            </div>

        </div>

    </div>

    <!-- Intelligent recommendations -->

    <div class="content-card mb-4">

        <div class="content-card-header">

            <div>

                <p class="page-kicker mb-1">
                    SMART INSIGHTS
                </p>

                <h3 class="mb-1">
                    Personalised Recommendations
                </h3>

                <small class="text-muted">
                    Explainable recommendations based on
                    your recorded student-life data
                </small>

            </div>

            <i class="bi bi-stars fs-4"></i>

        </div>

        <div class="row g-3">

            <?php foreach ($insights as $insight): ?>

                <div class="col-lg-6">

                    <div class="border rounded p-4 h-100">

                        <div class="d-flex gap-3">

                            <div class="stat-icon">

                                <i class="bi <?=
                                    analyticsEscape($insight["icon"])
                                ?>"></i>

                            </div>

                            <div class="flex-grow-1">

                                <span class="badge bg-<?=
                                    analyticsEscape($insight["class"])
                                ?> mb-2">

                                    <?= $insight["class"] === "danger"
                                        ? "IMPORTANT"
                                        : ($insight["class"] === "warning"
                                            ? "REVIEW"
                                            : "INSIGHT") ?>

                                </span>

                                <h5 class="mb-2">

                                    <?= analyticsEscape(
                                        $insight["title"]
                                    ) ?>

                                </h5>

                                <p class="text-muted">

                                    <?= analyticsEscape(
                                        $insight["message"]
                                    ) ?>

                                </p>

                                <a href="<?= analyticsEscape(
                                    $insight["link"]
                                ) ?>"
                                   class="btn btn-sm btn-outline-primary">

                                    <?= analyticsEscape(
                                        $insight["action"]
                                    ) ?>

                                    <i class="bi bi-arrow-right ms-1"></i>

                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

    <!-- Method explanation -->

    <div class="content-card">

        <div class="content-card-header">

            <h3 class="mb-0">
                How These Insights Are Calculated
            </h3>

        </div>

        <p class="text-muted mb-3">
            SSLMS uses transparent rules rather than a trained
            machine-learning model. Each insight is linked to
            recorded data and a defined condition.
        </p>

        <div class="table-responsive">

            <table class="table align-middle mb-0">

                <thead>

                    <tr>
                        <th>Analysis</th>
                        <th>Data Used</th>
                        <th>Method</th>
                    </tr>

                </thead>

                <tbody>

                    <tr>

                        <td>
                            Budget Usage
                        </td>

                        <td>
                            Transactions and monthly budget
                        </td>

                        <td>
                            Expenses ÷ budget × 100
                        </td>

                    </tr>

                    <tr>

                        <td>
                            Academic Completion
                        </td>

                        <td>
                            Assignment statuses
                        </td>

                        <td>
                            Completed assignments ÷ total × 100
                        </td>

                    </tr>

                    <tr>

                        <td>
                            Workload Analysis
                        </td>

                        <td>
                            Work shifts and estimated assignment hours
                        </td>

                        <td>
                            Scheduled work hours + estimated study hours
                        </td>

                    </tr>

                    <tr>

                        <td>
                            Deadline Conflicts
                        </td>

                        <td>
                            Shift dates and assignment due dates
                        </td>

                        <td>
                            Identify dates appearing in both datasets
                        </td>

                    </tr>

                    <tr>

                        <td>
                            Reminder Completion
                        </td>

                        <td>
                            Reminder statuses
                        </td>

                        <td>
                            Completed reminders ÷ total × 100
                        </td>

                    </tr>

                    <tr>

                        <td>
                            Attention Indicator
                        </td>

                        <td>
                            Generated warning rules
                        </td>

                        <td>
                            Danger +25, warning +15,
                            information +8, setup notice +5;
                            capped at 100
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

        <p class="small text-muted mt-3 mb-0">
            Workload estimates do not include scheduled lectures,
            travel, breaks or personal commitments. Assignment
            estimates are assumed to represent remaining study
            hours. Financial trends depend on the completeness
            of recorded transactions.
        </p>

    </div>

</main>

<?php require_once("../includes/footer.php"); ?>
