<?php

/*

|--------------------------------------------------------------------------

| SSLMS Main Dashboard

|--------------------------------------------------------------------------

|

| Student Smart Life Management System

|

| This dashboard brings the main parts of the system together:

|

| - Academic deadlines

| - Financial information

| - Work schedule

| - Reminders

| - Today's schedule

| - Smart study recommendation

| - Quick actions

|

| Every query is restricted to the currently logged-in student.

|

|--------------------------------------------------------------------------

*/

require_once("includes/auth_check.php");

require_once("config/db.php");
require_once __DIR__ . "/includes/planning_rules.php";

$userId = $_SESSION["user_id"];

$today = date("Y-m-d");

$currentMonth = date("Y-m");

/*

|--------------------------------------------------------------------------

| Get Current User

|--------------------------------------------------------------------------

*/

$getUser = $pdo->prepare(

    "SELECT

        id,

        full_name,

        email,

        university,

        course,

        year_of_study

     FROM users

     WHERE id = ?"

);

$getUser->execute([

    $userId

]);

$user = $getUser->fetch();

/*

|--------------------------------------------------------------------------

| Protect Against Missing User

|--------------------------------------------------------------------------

*/

if (!$user) {

    session_destroy();

    header(

        "Location: auth/login.php"

    );

    exit;

}

/*

|--------------------------------------------------------------------------

| First Name

|--------------------------------------------------------------------------

|

| Used to create a friendly dashboard greeting.

|

*/

$nameParts =

    preg_split(

        "/\s+/",

        trim(

            $user["full_name"]

        )

    );

$firstName =

    !empty($nameParts[0])

    ?

    $nameParts[0]

    :

    "Student";

/*

|--------------------------------------------------------------------------

| Academic Statistics

|--------------------------------------------------------------------------

*/

$getAcademicStats = $pdo->prepare(

    "SELECT

        COUNT(*) AS total_tasks,

        SUM(

            CASE

                WHEN status = 'completed'

                THEN 1

                ELSE 0

            END

        ) AS completed_tasks,

        SUM(

            CASE

                WHEN status != 'completed'

                AND due_date >= ?

                THEN 1

                ELSE 0

            END

        ) AS pending_tasks,

        SUM(

            CASE

                WHEN status != 'completed'

                AND due_date < ?

                THEN 1

                ELSE 0

            END

        ) AS overdue_tasks

     FROM assignments

     WHERE user_id = ?"

);

$getAcademicStats->execute([

    $today,

    $today,

    $userId

]);

$academicStats =

    $getAcademicStats->fetch();

$totalAcademicTasks =

    (int) (

        $academicStats["total_tasks"]

        ?? 0

    );

$completedAcademicTasks =

    (int) (

        $academicStats["completed_tasks"]

        ?? 0

    );

$pendingAcademicTasks =

    (int) (

        $academicStats["pending_tasks"]

        ?? 0

    );

$overdueAcademicTasks =

    (int) (

        $academicStats["overdue_tasks"]

        ?? 0

    );

/*

|--------------------------------------------------------------------------

| Academic Completion Percentage

|--------------------------------------------------------------------------

*/

$academicCompletion = 0;

if ($totalAcademicTasks > 0) {

    $academicCompletion =

        round(

            (

                $completedAcademicTasks

                /

                $totalAcademicTasks

            )

            *

            100

        );

}

/*

|--------------------------------------------------------------------------

| Upcoming Academic Deadlines

|--------------------------------------------------------------------------

*/

$getUpcomingAcademic = $pdo->prepare(

    "SELECT

        id,

        title,

        due_date,

        priority,

        status

     FROM assignments

     WHERE user_id = ?

     AND status != 'completed'

     AND due_date >= ?

     ORDER BY

        due_date ASC,

        CASE priority

            WHEN 'high' THEN 1

            WHEN 'medium' THEN 2

            WHEN 'low' THEN 3

            ELSE 4

        END

     LIMIT 5"

);

$getUpcomingAcademic->execute([

    $userId,

    $today

]);

$upcomingAcademic =

    $getUpcomingAcademic->fetchAll();

/*

|--------------------------------------------------------------------------

| Smart Study Recommendation

|--------------------------------------------------------------------------

|

| The dashboard recommends the most urgent unfinished academic task.

|

| High priority is considered first when two tasks share the same

| deadline.

|

|--------------------------------------------------------------------------

*/

// The dashboard and Smart Study Planner now use the same, explainable ranking.
$getRecommendedTask = $pdo->prepare(
    "SELECT id, title, due_date, priority, status, estimated_hours
     FROM assignments WHERE user_id = ? AND status <> 'completed'"
);
$getRecommendedTask->execute([$userId]);
$activeRecommendations = [];
foreach ($getRecommendedTask->fetchAll(PDO::FETCH_ASSOC) as $task) {
    $activeRecommendations[] = sslms_assignment_recommendation($task);
}
sslms_sort_recommendations($activeRecommendations);
$recommendedTask = $activeRecommendations[0] ?? null;

/*

|--------------------------------------------------------------------------

| Finance Summary

|--------------------------------------------------------------------------

|

| Finance records are expected to contain:

|

| amount

| type = income / expense

| transaction_date

|

|--------------------------------------------------------------------------

*/

$getFinanceSummary = $pdo->prepare(

    "SELECT

        COALESCE(

            SUM(

                CASE

                    WHEN type = 'income'

                    THEN amount

                    ELSE 0

                END

            ),

            0

        ) AS total_income,

        COALESCE(

            SUM(

                CASE

                    WHEN type = 'expense'

                    THEN amount

                    ELSE 0

                END

            ),

            0

        ) AS total_expenses,

        COALESCE(

            SUM(

                CASE

                    WHEN type = 'income'

                    AND DATE_FORMAT(

                        transaction_date,

                        '%Y-%m'

                    ) = ?

                    THEN amount

                    ELSE 0

                END

            ),

            0

        ) AS monthly_income,

        COALESCE(

            SUM(

                CASE

                    WHEN type = 'expense'

                    AND DATE_FORMAT(

                        transaction_date,

                        '%Y-%m'

                    ) = ?

                    THEN amount

                    ELSE 0

                END

            ),

            0

        ) AS monthly_expenses

     FROM transactions

     WHERE user_id = ?"

);

$getFinanceSummary->execute([

    $currentMonth,

    $currentMonth,

    $userId

]);

$financeSummary =

    $getFinanceSummary->fetch();

$totalIncome =

    (float) (

        $financeSummary["total_income"]

        ?? 0

    );

$totalExpenses =

    (float) (

        $financeSummary["total_expenses"]

        ?? 0

    );

$monthlyIncome =

    (float) (

        $financeSummary["monthly_income"]

        ?? 0

    );

$monthlyExpenses =

    (float) (

        $financeSummary["monthly_expenses"]

        ?? 0

    );

$currentBalance =

    $totalIncome

    -

    $totalExpenses;

$monthlyBalance =

    $monthlyIncome

    -

    $monthlyExpenses;

/*

|--------------------------------------------------------------------------

| Upcoming Work Shifts

|--------------------------------------------------------------------------

*/

$getUpcomingWork = $pdo->prepare(

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

     AND shift_date >= ?

     ORDER BY

        shift_date ASC,

        start_time ASC

     LIMIT 5"

);

$getUpcomingWork->execute([

    $userId,

    $today

]);

$upcomingWork =

    $getUpcomingWork->fetchAll();

/*

|--------------------------------------------------------------------------

| Work Hours This Month

|--------------------------------------------------------------------------

*/

$getMonthlyWork = $pdo->prepare(

    "SELECT

        shift_date,

        start_time,

        end_time

     FROM work_shifts

     WHERE user_id = ?

     AND DATE_FORMAT(

            shift_date,

            '%Y-%m'

         ) = ?"

);

$getMonthlyWork->execute([

    $userId,

    $currentMonth

]);

$monthlyWorkShifts =

    $getMonthlyWork->fetchAll();

$monthlyWorkHours = 0;

foreach (

    $monthlyWorkShifts

    as

    $workShift

) {

    $start =

        new DateTime(

            $workShift["shift_date"]

            .

            " "

            .

            $workShift["start_time"]

        );

    $end =

        new DateTime(

            $workShift["shift_date"]

            .

            " "

            .

            $workShift["end_time"]

        );

    /*

    |--------------------------------------------------------------------------

    | Overnight Shift Support

    |--------------------------------------------------------------------------

    */

    if ($end <= $start) {

        $end->modify(

            "+1 day"

        );

    }

    $monthlyWorkHours +=

        (

            $end->getTimestamp()

            -

            $start->getTimestamp()

        )

        /

        3600;

}

/*

|--------------------------------------------------------------------------

| Reminder Statistics

|--------------------------------------------------------------------------

*/

$getReminderStats = $pdo->prepare(

    "SELECT

        SUM(

            CASE

                WHEN status = 'pending'

                THEN 1

                ELSE 0

            END

        ) AS pending_reminders,

        SUM(

            CASE

                WHEN status = 'pending'

                AND reminder_date = ?

                THEN 1

                ELSE 0

            END

        ) AS today_reminders

     FROM reminders

     WHERE user_id = ?"

);

$getReminderStats->execute([

    $today,

    $userId

]);

$reminderStats =

    $getReminderStats->fetch();

$pendingReminders =

    (int) (

        $reminderStats["pending_reminders"]

        ?? 0

    );

$todayReminders =

    (int) (

        $reminderStats["today_reminders"]

        ?? 0

    );

/*

|--------------------------------------------------------------------------

| Upcoming Reminders

|--------------------------------------------------------------------------

*/

$getUpcomingReminders = $pdo->prepare(

    "SELECT

        id,

        title,

        reminder_date,

        reminder_time,


        priority

     FROM reminders

     WHERE user_id = ?

     AND status = 'pending'

     AND reminder_date >= ?

     ORDER BY

        reminder_date ASC,

        CASE

            WHEN reminder_time IS NULL

            THEN 1

            ELSE 0

        END,

        reminder_time ASC

     LIMIT 5"

);

$getUpcomingReminders->execute([

    $userId,

    $today

]);

$upcomingReminders =

    $getUpcomingReminders->fetchAll();

/*

|--------------------------------------------------------------------------

| Today's Academic Events

|--------------------------------------------------------------------------

*/

$getTodayAcademic = $pdo->prepare(

    "SELECT

        title,

        priority

     FROM assignments

     WHERE user_id = ?

     AND due_date = ?

     AND status != 'completed'

     ORDER BY title ASC"

);

$getTodayAcademic->execute([

    $userId,

    $today

]);

$todayAcademic =

    $getTodayAcademic->fetchAll();

/*

|--------------------------------------------------------------------------

| Today's Work Shifts

|--------------------------------------------------------------------------

*/

$getTodayWork = $pdo->prepare(

    "SELECT

        job_title,

        employer,

        start_time,

        end_time

     FROM work_shifts

     WHERE user_id = ?

     AND shift_date = ?

     ORDER BY start_time ASC"

);

$getTodayWork->execute([

    $userId,

    $today

]);

$todayWork =

    $getTodayWork->fetchAll();

/*

|--------------------------------------------------------------------------

| Today's Reminders

|--------------------------------------------------------------------------

*/

$getTodayReminders = $pdo->prepare(

    "SELECT

        title,

        reminder_time,


        priority

     FROM reminders

     WHERE user_id = ?

     AND reminder_date = ?

     AND status = 'pending'

     ORDER BY

        reminder_time ASC"

);

$getTodayReminders->execute([

    $userId,

    $today

]);

$todayReminderList =

    $getTodayReminders->fetchAll();

/*

|--------------------------------------------------------------------------

| Today's Total Events

|--------------------------------------------------------------------------

*/

$todayEventCount =

    count($todayAcademic)

    +

    count($todayWork)

    +

    count($todayReminderList);

/*

|--------------------------------------------------------------------------

| Dashboard Message

|--------------------------------------------------------------------------

*/

if ($overdueAcademicTasks > 0) {

    $dashboardMessage =

        "You have "

        .

        $overdueAcademicTasks

        .

        " overdue academic "

        .

        (

            $overdueAcademicTasks === 1

            ?

            "task"

            :

            "tasks"

        )

        .

        ". Consider reviewing your study plan today.";

} elseif ($todayEventCount > 0) {

    $dashboardMessage =

        "You have "

        .

        $todayEventCount

        .

        " scheduled "

        .

        (

            $todayEventCount === 1

            ?

            "item"

            :

            "items"

        )

        .

        " today.";

} else {

    $dashboardMessage =

        "Your schedule is clear today. This could be a good opportunity to work ahead.";

}

/*

|--------------------------------------------------------------------------

| Page Layout

|--------------------------------------------------------------------------

*/

/* --------------------------------------------------------------------------
   Final Dashboard Integration - rule-based alerts and smart tool summaries
   -------------------------------------------------------------------------- */
$dashboardMonthStart = date('Y-m-01');
$dashboardWeekEnd = date('Y-m-d', strtotime('+6 days'));

$budgetQuery = $pdo->prepare("SELECT budget_amount FROM monthly_budgets WHERE user_id = ? AND budget_month = ? LIMIT 1");
$budgetQuery->execute([$userId, $dashboardMonthStart]);
$dashboardBudget = (float) ($budgetQuery->fetchColumn() ?: 0);
$dashboardBudgetPercent = $dashboardBudget > 0 ? ($monthlyExpenses / $dashboardBudget) * 100 : 0;

$weekShiftQuery = $pdo->prepare("SELECT shift_date, start_time, end_time FROM work_shifts WHERE user_id = ? AND shift_date BETWEEN ? AND ?");
$weekShiftQuery->execute([$userId, $today, $dashboardWeekEnd]);
$dashboardWeekWorkHours = 0.0;
$dashboardWorkDays = [];
foreach ($weekShiftQuery->fetchAll(PDO::FETCH_ASSOC) as $shift) {
    $start = new DateTimeImmutable($shift['shift_date'] . ' ' . $shift['start_time']);
    $end = new DateTimeImmutable($shift['shift_date'] . ' ' . $shift['end_time']);
    if ($end < $start) $end = $end->modify('+1 day');
    $dashboardWeekWorkHours += max(0, ($end->getTimestamp() - $start->getTimestamp()) / 3600);
    $dashboardWorkDays[$shift['shift_date']] = true;
}

$studyQuery = $pdo->prepare("SELECT due_date, estimated_hours FROM assignments WHERE user_id = ? AND status <> 'completed' AND due_date <= ?");
$studyQuery->execute([$userId, $dashboardWeekEnd]);
$dashboardStudyHours = 0.0;
$dashboardOverlapDays = [];
foreach ($studyQuery->fetchAll(PDO::FETCH_ASSOC) as $assignment) {
    $dashboardStudyHours += max(0, (float) ($assignment['estimated_hours'] ?? 0));
    if ($assignment['due_date'] >= $today && isset($dashboardWorkDays[$assignment['due_date']])) {
        $dashboardOverlapDays[$assignment['due_date']] = true;
    }
}
$dashboardCombinedHours = $dashboardWeekWorkHours + $dashboardStudyHours;
$dashboardOverlapCount = count($dashboardOverlapDays);

$overdueReminderQuery = $pdo->prepare("SELECT COUNT(*) FROM reminders WHERE user_id = ? AND status = 'pending' AND reminder_date < ?");
$overdueReminderQuery->execute([$userId, $today]);
$dashboardOverdueReminders = (int) $overdueReminderQuery->fetchColumn();

$dashboardAlerts = [];
if ($overdueAcademicTasks > 0) {
    $dashboardAlerts[] = ['danger', 'bi-journal-x', 'Overdue assignments', "$overdueAcademicTasks academic task(s) are overdue.", 'academics/study_planner.php'];
}
if ($dashboardBudget <= 0) {
    $dashboardAlerts[] = ['secondary', 'bi-wallet2', 'Monthly budget missing', 'Set a monthly budget to enable spending warnings.', 'finance/budget_advisor.php'];
} elseif ($monthlyExpenses > $dashboardBudget) {
    $dashboardAlerts[] = ['danger', 'bi-exclamation-triangle', 'Budget exceeded', 'Recorded monthly expenses exceed your budget by £' . number_format($monthlyExpenses - $dashboardBudget, 2) . '.', 'finance/budget_advisor.php'];
} elseif ($dashboardBudgetPercent >= 90) {
    $dashboardAlerts[] = ['warning', 'bi-pie-chart', 'High budget usage', number_format($dashboardBudgetPercent, 1) . '% of your monthly budget has been used.', 'finance/spending_prediction.php'];
}
if ($dashboardOverlapCount > 0) {
    $dashboardAlerts[] = ['warning', 'bi-calendar-x', 'Work/deadline overlap', "$dashboardOverlapCount day(s) have both a shift and an assignment deadline.", 'work/balance_advisor.php'];
}
if ($dashboardCombinedHours >= 45) {
    $dashboardAlerts[] = ['warning', 'bi-activity', 'High estimated workload', number_format($dashboardCombinedHours, 1) . ' hours of work plus estimated outstanding study effort.', 'work/balance_advisor.php'];
}
if ($dashboardOverdueReminders > 0) {
    $dashboardAlerts[] = ['info', 'bi-bell', 'Overdue reminders', "$dashboardOverdueReminders pending reminder(s) are overdue.", 'reminders/index.php'];
}

$pageTitle =

    "Dashboard";

include("includes/header.php");

include("includes/sidebar.php");

?>

<main class="main-content">

    <!-- ================================================================

         WELCOME AREA

         ================================================================ -->

    <div

        class="dashboard-hero

               mb-4"

    >

        <div>

            <p class="page-kicker">

                Student Smart Life Management System

            </p>

            <h1>

                Welcome back,

                <?php

                echo htmlspecialchars(

                    $firstName

                );

                ?>.

            </h1>

            <p class="mb-0">

                <?php

                echo htmlspecialchars(

                    $dashboardMessage

                );

                ?>

            </p>

        </div>

        <div

            class="dashboard-hero-actions"

        >

            <a

                href="calendar/index.php"

                class="btn btn-light"

            >

                <i class="bi bi-calendar3"></i>

                View Calendar

            </a>

            <a

                href="academics/study_planner.php"

                class="btn btn-outline-light"

            >

                <i class="bi bi-lightbulb"></i>

                Study Planner

            </a>

        </div>

    </div>

    <!-- ================================================================

         MAIN STATISTICS

         ================================================================ -->

    <div class="row g-4 mb-4">

        <!-- Academic -->

        <div class="col-md-6 col-xl-3">

            <a

                href="academics/index.php"

                class="dashboard-stat-link"

            >

                <div class="stat-card h-100">

                    <div class="stat-icon">

                        <i

                            class="bi

                                   bi-mortarboard"

                        ></i>

                    </div>

                    <div>

                        <span>

                            Pending Tasks

                        </span>

                        <h3>

                            <?php

                            echo $pendingAcademicTasks;

                            ?>

                        </h3>

                        <small class="text-muted">

                            <?php

                            echo $academicCompletion;

                            ?>% completed

                        </small>

                    </div>

                </div>

            </a>

        </div>

        <!-- Finance -->

        <div class="col-md-6 col-xl-3">

            <a

                href="finance/index.php"

                class="dashboard-stat-link"

            >

                <div class="stat-card h-100">

                    <div class="stat-icon">

                        <i

                            class="bi

                                   bi-wallet2"

                        ></i>

                    </div>

                    <div>

                        <span>

                            Current Balance

                        </span>

                        <h3>

                            £<?php

                            echo number_format(

                                $currentBalance,

                                2

                            );

                            ?>

                        </h3>

                        <small class="text-muted">

                            £<?php

                            echo number_format(

                                $monthlyExpenses,

                                2

                            );

                            ?>

                            spent this month

                        </small>

                    </div>

                </div>

            </a>

        </div>

        <!-- Work -->

        <div class="col-md-6 col-xl-3">

            <a

                href="work/index.php"

                class="dashboard-stat-link"

            >

                <div class="stat-card h-100">

                    <div class="stat-icon">

                        <i

                            class="bi

                                   bi-briefcase"

                        ></i>

                    </div>

                    <div>

                        <span>

                            Work This Month

                        </span>

                        <h3>

                            <?php

                            echo number_format(

                                $monthlyWorkHours,

                                1

                            );

                            ?>

                            hrs

                        </h3>

                        <small class="text-muted">

                            <?php

                            echo count(

                                $upcomingWork

                            );

                            ?>

                            upcoming shown

                        </small>

                    </div>

                </div>

            </a>

        </div>

        <!-- Reminders -->

        <div class="col-md-6 col-xl-3">

            <a

                href="reminders/index.php"

                class="dashboard-stat-link"

            >

                <div class="stat-card h-100">

                    <div class="stat-icon">

                        <i

                            class="bi

                                   bi-bell"

                        ></i>

                    </div>

                    <div>

                        <span>

                            Pending Reminders

                        </span>

                        <h3>

                            <?php

                            echo $pendingReminders;

                            ?>

                        </h3>

                        <small class="text-muted">

                            <?php

                            echo $todayReminders;

                            ?>

                            today

                        </small>

                    </div>

                </div>

            </a>

        </div>

    </div>

    <!-- FINAL INTEGRATION: Smart Alerts -->
    <div class="content-card mb-4">
        <div class="content-card-header">
            <div><p class="page-kicker">Smart Insights</p><h3>Alerts & Recommendations</h3></div>
            <a class="btn btn-sm btn-outline-primary" href="analytics/index.php">Analytics & Insights <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php if (empty($dashboardAlerts)): ?>
            <div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i>No major warnings detected by the current planning rules.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($dashboardAlerts as $alert): ?>
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-start gap-3">
                                <i class="bi <?= htmlspecialchars($alert[1], ENT_QUOTES, 'UTF-8') ?> fs-4 text-<?= htmlspecialchars($alert[0], ENT_QUOTES, 'UTF-8') ?>"></i>
                                <div class="flex-grow-1">
                                    <h6><?= htmlspecialchars($alert[2], ENT_QUOTES, 'UTF-8') ?></h6>
                                    <p class="small text-muted mb-2"><?= htmlspecialchars($alert[3], ENT_QUOTES, 'UTF-8') ?></p>
                                    <a class="small" href="<?= htmlspecialchars($alert[4], ENT_QUOTES, 'UTF-8') ?>">Review <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="content-card h-100">
                <div class="content-card-header"><div><p class="page-kicker">Financial Wellbeing</p><h3>Monthly Budget Check</h3></div><i class="bi bi-wallet2 fs-3"></i></div>
                <?php if ($dashboardBudget > 0): ?>
                    <div class="d-flex justify-content-between mb-2"><span>Budget used</span><strong><?= number_format($dashboardBudgetPercent, 1) ?>%</strong></div>
                    <div class="progress mb-3" style="height: 10px;"><div class="progress-bar <?= $dashboardBudgetPercent > 100 ? 'bg-danger' : ($dashboardBudgetPercent >= 90 ? 'bg-warning' : 'bg-success') ?>" style="width: <?= min(100, max(0, $dashboardBudgetPercent)) ?>%"></div></div>
                    <p class="text-muted">£<?= number_format($monthlyExpenses, 2) ?> spent out of £<?= number_format($dashboardBudget, 2) ?> budget.</p>
                <?php else: ?>
                    <p class="text-muted">No budget is set for this month.</p>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary" href="finance/budget_advisor.php">Budget Advisor</a>
                    <a class="btn btn-outline-primary" href="finance/spending_prediction.php">Spending Prediction</a>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="content-card h-100">
                <div class="content-card-header"><div><p class="page-kicker">Next Seven Days</p><h3>Work-Study Balance</h3></div><i class="bi bi-activity fs-3"></i></div>
                <div class="row g-3 mb-3">
                    <div class="col-4"><div class="dashboard-finance-box"><span>Work</span><h4><?= number_format($dashboardWeekWorkHours, 1) ?>h</h4></div></div>
                    <div class="col-4"><div class="dashboard-finance-box"><span>Study*</span><h4><?= number_format($dashboardStudyHours, 1) ?>h</h4></div></div>
                    <div class="col-4"><div class="dashboard-finance-box"><span>Total</span><h4><?= number_format($dashboardCombinedHours, 1) ?>h</h4></div></div>
                </div>
                <p class="text-muted small"><?= $dashboardOverlapCount ?> date(s) have both a shift and an assignment deadline. *Study hours are estimates for outstanding assignments, including overdue tasks, not scheduled study time.</p>
                <a class="btn btn-outline-primary" href="work/balance_advisor.php">Work-Study Balance Advisor</a>
            </div>
        </div>
    </div>

    <!-- ================================================================
         SMART STUDY + TODAY

         ================================================================ -->

    <div class="row g-4 mb-4">

        <!-- Smart recommendation -->

        <div class="col-xl-6">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <p class="page-kicker">

                            Smart Study Planner

                        </p>

                        <h3>

                            Recommended Focus

                        </h3>

                    </div>

                    <i

                        class="bi

                               bi-lightbulb

                               fs-3"

                    ></i>

                </div>

                <?php if (

                    $recommendedTask

                ): ?>

                    <?php

                    $recommendedPriorityClass =

                        "secondary";

                    if (

                        $recommendedTask["priority"]

                        ===

                        "high"

                    ) {

                        $recommendedPriorityClass =

                            "danger";

                    } elseif (

                        $recommendedTask["priority"]

                        ===

                        "medium"

                    ) {

                        $recommendedPriorityClass =

                            "warning";

                    } elseif (

                        $recommendedTask["priority"]

                        ===

                        "low"

                    ) {

                        $recommendedPriorityClass =

                            "success";

                    }

                    $recommendedDueTimestamp =

                        strtotime(

                            $recommendedTask["due_date"]

                        );

                    $todayTimestamp =

                        strtotime($today);

                    $daysUntilDue =

                        (int) floor(

                            (

                                $recommendedDueTimestamp

                                -

                                $todayTimestamp

                            )

                            /

                            86400

                        );

                    ?>

                    <div class="recommended-task">

                        <div

                            class="d-flex

                                   justify-content-between

                                   align-items-start

                                   flex-wrap

                                   gap-3"

                        >

                            <div>

                                <h4>

                                    <?php

                                    echo htmlspecialchars(

                                        $recommendedTask[

                                            "title"

                                        ]

                                    );

                                    ?>

                                </h4>

                                <p

                                    class="text-muted

                                           mb-0"

                                >

                                    <?php if (

                                        $daysUntilDue < 0

                                    ): ?>

                                        This task is overdue

                                        by

                                        <?php

                                        echo abs(

                                            $daysUntilDue

                                        );

                                        ?>

                                        <?php

                                        echo

                                        abs(

                                            $daysUntilDue

                                        ) === 1

                                        ?

                                        "day"

                                        :

                                        "days";

                                        ?>.

                                    <?php elseif (

                                        $daysUntilDue === 0

                                    ): ?>

                                        This task is due today.

                                    <?php elseif (

                                        $daysUntilDue === 1

                                    ): ?>

                                        This task is due tomorrow.

                                    <?php else: ?>

                                        Due in

                                        <?php

                                        echo $daysUntilDue;

                                        ?>

                                        days.

                                    <?php endif; ?>

                                </p>

                            </div>

                            <span

                                class="badge bg-<?php

                                echo

                                $recommendedPriorityClass;

                                ?>"

                            >

                                <?php

                                echo ucfirst(

                                    htmlspecialchars(

                                        $recommendedTask[

                                            "priority"

                                        ]

                                    )

                                );

                                ?>

                            </span>

                        </div>

                        <hr>

                        <div

                            class="d-flex

                                   justify-content-between

                                   align-items-center

                                   flex-wrap

                                   gap-3"

                        >

                            <div>

                                <small class="text-muted">

                                    Deadline

                                </small>

                                <div>

                                    <strong>

                                        <?php

                                        echo date(

                                            "d F Y",

                                            strtotime(

                                                $recommendedTask[

                                                    "due_date"

                                                ]

                                            )

                                        );

                                        ?>

                                    </strong>

                                </div>

                            </div>

                            <a

                                href="academics/study_planner.php"

                                class="btn

                                       btn-primary"

                            >

                                Open Study Planner

                                <i

                                    class="bi

                                           bi-arrow-right"

                                ></i>

                            </a>

                        </div>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <i

                            class="bi

                                   bi-check-circle"

                        ></i>

                        <h4>

                            You're caught up

                        </h4>

                        <p>

                            There are no unfinished

                            academic tasks to recommend.

                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

        <!-- Today's schedule -->

        <div class="col-xl-6">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <p class="page-kicker">

                            Daily Overview

                        </p>

                        <h3>

                            Today's Schedule

                        </h3>

                    </div>

                    <span class="badge bg-primary">

                        <?php

                        echo $todayEventCount;

                        ?>

                        <?php

                        echo

                        $todayEventCount === 1

                        ?

                        "item"

                        :

                        "items";

                        ?>

                    </span>

                </div>

                <?php if (

                    $todayEventCount > 0

                ): ?>

                    <div

                        class="dashboard-schedule-list"

                    >

                        <!-- Academic events -->

                        <?php foreach (

                            $todayAcademic

                            as

                            $academicItem

                        ): ?>

                            <div

                                class="dashboard-schedule-item"

                            >

                                <div

                                    class="schedule-icon"

                                >

                                    <i

                                        class="bi

                                               bi-book"

                                    ></i>

                                </div>

                                <div

                                    class="flex-grow-1"

                                >

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(

                                            $academicItem[

                                                "title"

                                            ]

                                        );

                                        ?>

                                    </strong>

                                    <small

                                        class="d-block

                                               text-muted"

                                    >

                                        Academic deadline

                                    </small>

                                </div>

                                <span

                                    class="badge

                                           bg-primary"

                                >

                                    Academic

                                </span>

                            </div>

                        <?php endforeach; ?>

                        <!-- Work events -->

                        <?php foreach (

                            $todayWork

                            as

                            $workItem

                        ): ?>

                            <div

                                class="dashboard-schedule-item"

                            >

                                <div

                                    class="schedule-icon"

                                >

                                    <i

                                        class="bi

                                               bi-briefcase"

                                    ></i>

                                </div>

                                <div

                                    class="flex-grow-1"

                                >

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(

                                            $workItem[

                                                "job_title"

                                            ]

                                        );

                                        ?>

                                    </strong>

                                    <small

                                        class="d-block

                                               text-muted"

                                    >

                                        <?php

                                        echo date(

                                            "H:i",

                                            strtotime(

                                                $workItem[

                                                    "start_time"

                                                ]

                                            )

                                        );

                                        ?>

                                        -

                                        <?php

                                        echo date(

                                            "H:i",

                                            strtotime(

                                                $workItem[

                                                    "end_time"

                                                ]

                                            )

                                        );

                                        ?>

                                        <?php if (

                                            !empty(

                                                $workItem[

                                                    "employer"

                                                ]

                                            )

                                        ): ?>

                                            ·

                                            <?php

                                            echo htmlspecialchars(

                                                $workItem[

                                                    "employer"

                                                ]

                                            );

                                            ?>

                                        <?php endif; ?>

                                    </small>

                                </div>

                                <span

                                    class="badge

                                           bg-success"

                                >

                                    Work

                                </span>

                            </div>

                        <?php endforeach; ?>

                        <!-- Reminder events -->

                        <?php foreach (

                            $todayReminderList

                            as

                            $reminderItem

                        ): ?>

                            <div

                                class="dashboard-schedule-item"

                            >

                                <div

                                    class="schedule-icon"

                                >

                                    <i

                                        class="bi

                                               bi-bell"

                                    ></i>

                                </div>

                                <div

                                    class="flex-grow-1"

                                >

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(

                                            $reminderItem[

                                                "title"

                                            ]

                                        );

                                        ?>

                                    </strong>

                                    <small

                                        class="d-block

                                               text-muted"

                                    >

                                        <?php

                                        if (

                                            !empty(

                                                $reminderItem[

                                                    "reminder_time"

                                                ]

                                            )

                                        ) {

                                            echo date(

                                                "H:i",

                                                strtotime(

                                                    $reminderItem[

                                                        "reminder_time"

                                                    ]

                                                )

                                            );

                                            echo " · ";

                                        }

                                        echo htmlspecialchars(

                                            "Reminder"

                                        );

                                        ?>

                                    </small>

                                </div>

                                <span

                                    class="badge

                                           bg-warning"

                                >

                                    Reminder

                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <div class="mt-4">

                        <a

                            href="calendar/index.php?month=<?php

                            echo date("Y-m");

                            ?>&date=<?php

                            echo $today;

                            ?>"

                            class="btn

                                   btn-outline-primary"

                        >

                            View Today in Calendar

                        </a>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <i

                            class="bi

                                   bi-calendar-check"

                        ></i>

                        <h4>

                            Clear schedule

                        </h4>

                        <p>

                            Nothing is currently

                            scheduled for today.

                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- ================================================================

         UPCOMING INFORMATION

         ================================================================ -->

    <div class="row g-4 mb-4">

        <!-- Academic deadlines -->

        <div class="col-xl-4">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <p class="page-kicker">

                            Academics

                        </p>

                        <h3>

                            Upcoming Deadlines

                        </h3>

                    </div>

                    <a

                        href="academics/index.php"

                        class="small"

                    >

                        View all

                    </a>

                </div>

                <?php if (

                    count(

                        $upcomingAcademic

                    ) > 0

                ): ?>

                    <div

                        class="dashboard-list"

                    >

                        <?php foreach (

                            $upcomingAcademic

                            as

                            $academicItem

                        ): ?>

                            <div

                                class="dashboard-list-item"

                            >

                                <div>

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(

                                            $academicItem[

                                                "title"

                                            ]

                                        );

                                        ?>

                                    </strong>

                                    <small

                                        class="d-block

                                               text-muted"

                                    >

                                        Due

                                        <?php

                                        echo date(

                                            "d M Y",

                                            strtotime(

                                                $academicItem[

                                                    "due_date"

                                                ]

                                            )

                                        );

                                        ?>

                                    </small>

                                </div>

                                <span

                                    class="badge

                                    <?php

                                    echo

                                    $academicItem["priority"]

                                    ===

                                    "high"

                                    ?

                                    "bg-danger"

                                    :

                                    (

                                        $academicItem["priority"]

                                        ===

                                        "medium"

                                        ?

                                        "bg-warning"

                                        :

                                        "bg-success"

                                    );

                                    ?>"

                                >

                                    <?php

                                    echo ucfirst(

                                        htmlspecialchars(

                                            $academicItem[

                                                "priority"

                                            ]

                                        )

                                    );

                                    ?>

                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <p class="text-muted mb-0">

                        No upcoming academic deadlines.

                    </p>

                <?php endif; ?>

            </div>

        </div>

        <!-- Work -->

        <div class="col-xl-4">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <p class="page-kicker">

                            Employment

                        </p>

                        <h3>

                            Upcoming Work

                        </h3>

                    </div>

                    <a

                        href="work/index.php"

                        class="small"

                    >

                        View all

                    </a>

                </div>

                <?php if (

                    count(

                        $upcomingWork

                    ) > 0

                ): ?>

                    <div

                        class="dashboard-list"

                    >

                        <?php foreach (

                            $upcomingWork

                            as

                            $workItem

                        ): ?>

                            <div

                                class="dashboard-list-item"

                            >

                                <div>

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(

                                            $workItem[

                                                "job_title"

                                            ]

                                        );

                                        ?>

                                    </strong>

                                    <small

                                        class="d-block

                                               text-muted"

                                    >

                                        <?php

                                        echo date(

                                            "d M",

                                            strtotime(

                                                $workItem[

                                                    "shift_date"

                                                ]

                                            )

                                        );

                                        ?>

                                        ·

                                        <?php

                                        echo date(

                                            "H:i",

                                            strtotime(

                                                $workItem[

                                                    "start_time"

                                                ]

                                            )

                                        );

                                        ?>

                                    </small>

                                </div>

                                <i

                                    class="bi

                                           bi-chevron-right"

                                ></i>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <p class="text-muted mb-0">

                        No upcoming work shifts.

                    </p>

                <?php endif; ?>

            </div>

        </div>

        <!-- Reminders -->

        <div class="col-xl-4">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <p class="page-kicker">

                            Organisation

                        </p>

                        <h3>

                            Upcoming Reminders

                        </h3>

                    </div>

                    <a

                        href="reminders/index.php"

                        class="small"

                    >

                        View all

                    </a>

                </div>

                <?php if (

                    count(

                        $upcomingReminders

                    ) > 0

                ): ?>

                    <div

                        class="dashboard-list"

                    >

                        <?php foreach (

                            $upcomingReminders

                            as

                            $reminderItem

                        ): ?>

                            <div

                                class="dashboard-list-item"

                            >

                                <div>

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(

                                            $reminderItem[

                                                "title"

                                            ]

                                        );

                                        ?>

                                    </strong>

                                    <small

                                        class="d-block

                                               text-muted"

                                    >

                                        <?php

                                        echo date(

                                            "d M",

                                            strtotime(

                                                $reminderItem[

                                                    "reminder_date"

                                                ]

                                            )

                                        );

                                        ?>

                                        <?php if (

                                            !empty(

                                                $reminderItem[

                                                    "reminder_time"

                                                ]

                                            )

                                        ): ?>

                                            ·

                                            <?php

                                            echo date(

                                                "H:i",

                                                strtotime(

                                                    $reminderItem[

                                                        "reminder_time"

                                                    ]

                                                )

                                            );

                                            ?>

                                        <?php endif; ?>

                                    </small>

                                </div>

                                <span

                                    class="badge

                                    <?php

                                    echo

                                    $reminderItem["priority"]

                                    ===

                                    "high"

                                    ?

                                    "bg-danger"

                                    :

                                    (

                                        $reminderItem["priority"]

                                        ===

                                        "medium"

                                        ?

                                        "bg-warning"

                                        :

                                        "bg-success"

                                    );

                                    ?>"

                                >

                                    <?php

                                    echo ucfirst(

                                        htmlspecialchars(

                                            $reminderItem[

                                                "priority"

                                            ]

                                        )

                                    );

                                    ?>

                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <p class="text-muted mb-0">

                        No upcoming reminders.

                    </p>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- ================================================================

         FINANCIAL OVERVIEW

         ================================================================ -->

    <div class="row g-4 mb-4">

        <div class="col-xl-8">

            <div class="content-card h-100">

                <div class="content-card-header">

                    <div>

                        <p class="page-kicker">

                            Financial Wellbeing

                        </p>

                        <h3>

                            This Month

                        </h3>

                    </div>

                    <a

                        href="finance/index.php"

                        class="btn

                               btn-sm

                               btn-outline-primary"

                    >

                        Open Finance

                    </a>

                </div>

                <div class="row g-4">

                    <div class="col-md-4">

                        <div

                            class="dashboard-finance-box"

                        >

                            <span>

                                Income

                            </span>

                            <h4>

                                £<?php

                                echo number_format(

                                    $monthlyIncome,

                                    2

                                );

                                ?>

                            </h4>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div

                            class="dashboard-finance-box"

                        >

                            <span>

                                Expenses

                            </span>

                            <h4>

                                £<?php

                                echo number_format(

                                    $monthlyExpenses,

                                    2

                                );

                                ?>

                            </h4>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div

                            class="dashboard-finance-box"

                        >

                            <span>

                                Monthly Balance

                            </span>

                            <h4>

                                £<?php

                                echo number_format(

                                    $monthlyBalance,

                                    2

                                );

                                ?>

                            </h4>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- Quick actions -->

        <div class="col-xl-4">

            <div class="content-card h-100">

                <p class="page-kicker">

                    Shortcuts

                </p>

                <h3>

                    Quick Actions

                </h3>

                <div

                    class="d-grid

                           gap-2

                           mt-4"

                >

                    <a

                        href="academics/add_task.php"

                        class="btn

                               btn-outline-primary

                               text-start"

                    >

                        <i

                            class="bi

                                   bi-plus-circle

                                   me-2"

                        ></i>

                        Add Academic Task

                    </a>

                    <a

                        href="finance/add_expense.php"

                        class="btn

                               btn-outline-primary

                               text-start"

                    >

                        <i

                            class="bi

                                   bi-wallet2

                                   me-2"

                        ></i>

                        Add Expense

                    </a>

                    <a

                        href="work/add_shift.php"

                        class="btn

                               btn-outline-primary

                               text-start"

                    >

                        <i

                            class="bi

                                   bi-briefcase

                                   me-2"

                        ></i>

                        Add Work Shift

                    </a>

                    <a

                        href="reminders/add_reminder.php"

                        class="btn

                               btn-outline-primary

                               text-start"

                    >

                        <i

                            class="bi

                                   bi-bell

                                   me-2"

                        ></i>

                        Add Reminder

                    </a>

                    <a

                        href="profile/index.php"

                        class="btn

                               btn-outline-primary

                               text-start"

                    >

                        <i

                            class="bi

                                   bi-person

                                   me-2"

                        ></i>

                        My Profile

                    </a>

                </div>

            </div>

        </div>

    </div>

<!-- FINAL INTEGRATION: Five smart features -->
    <div class="content-card mb-4">
        <div class="content-card-header"><div><p class="page-kicker">Intelligent Features</p><h3>Smart Planning Tools</h3></div><a href="analytics/index.php" class="btn btn-sm btn-primary">Analytics & Insights</a></div>
        <div class="row g-3">
            <?php foreach ([
                ['Budget Advisor', 'finance/budget_advisor.php', 'bi-wallet2'],
                ['Spending Prediction', 'finance/spending_prediction.php', 'bi-graph-up-arrow'],
                ['Deadline Priority', 'academics/study_planner.php', 'bi-exclamation-circle'],
                ['Smart Study Planner', 'academics/study_planner.php', 'bi-journal-check'],
                ['Work-Study Balance', 'work/balance_advisor.php', 'bi-activity']
            ] as $smartTool): ?>
                <div class="col-md-6 col-xl-4">
                    <a class="btn btn-outline-primary w-100 text-start py-3" href="<?= htmlspecialchars($smartTool[1], ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi <?= htmlspecialchars($smartTool[2], ENT_QUOTES, 'UTF-8') ?> me-2"></i><?= htmlspecialchars($smartTool[0], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<?php

include("includes/footer.php");

?>