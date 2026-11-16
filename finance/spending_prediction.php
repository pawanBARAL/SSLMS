<?php

require_once("../includes/auth_check.php");
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/finance_rules.php';
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Selected month
|--------------------------------------------------------------------------
*/

$selectedMonth = $_GET["month"] ?? date("Y-m");

if (!valid_month($selectedMonth)) {
    $selectedMonth = date("Y-m");
}

$monthStart = $selectedMonth . "-01";

$monthEnd = date(
    "Y-m-t",
    strtotime($monthStart)
);

$monthLabel = date(
    "F Y",
    strtotime($monthStart)
);

$currentMonth = date("Y-m");
// Do not treat future-dated entries in the current month as money already spent.
$recordedThrough = sslms_spending_recorded_through($selectedMonth);


$daysInMonth = (int) date(
    "t",
    strtotime($monthStart)
);


/*
|--------------------------------------------------------------------------
| Work out how much of the month has passed
|--------------------------------------------------------------------------
|
| Current month:
| use today's day number.
|
| Past month:
| the entire month has already passed.
|
| Future month:
| no spending prediction can be calculated yet.
|
*/

if ($selectedMonth === $currentMonth) {

    $daysPassed = (int) date("j");

    $daysRemaining = max(
        0,
        $daysInMonth - $daysPassed
    );

} elseif ($selectedMonth < $currentMonth) {

    $daysPassed = $daysInMonth;
    $daysRemaining = 0;

} else {

    $daysPassed = 0;
    $daysRemaining = $daysInMonth;
}


/*
|--------------------------------------------------------------------------
| Get monthly budget
|--------------------------------------------------------------------------
*/

$budgetQuery = $pdo->prepare(
    "
    SELECT budget_amount

    FROM monthly_budgets

    WHERE user_id = ?
    AND budget_month = ?

    LIMIT 1
    "
);

$budgetQuery->execute([
    $userId,
    $monthStart
]);

$budgetRow =
    $budgetQuery->fetch(PDO::FETCH_ASSOC);

$monthlyBudget =
    $budgetRow
        ? (float) $budgetRow["budget_amount"]
        : 0;


/*
|--------------------------------------------------------------------------
| Get expenses and income for selected month
|--------------------------------------------------------------------------
*/

$summaryQuery = $pdo->prepare(
    "
    SELECT

        COALESCE(
            SUM(
                CASE
                    WHEN type = 'expense'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS expenses,

        COALESCE(
            SUM(
                CASE
                    WHEN type = 'income'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS income

    FROM transactions

    WHERE user_id = ?
    AND transaction_date BETWEEN ? AND ?
    "
);

$summaryQuery->execute([
    $userId,
    $monthStart,
    $recordedThrough
]);

$summary =
    $summaryQuery->fetch(PDO::FETCH_ASSOC);

$totalExpenses =
    (float) ($summary["expenses"] ?? 0);

$totalIncome =
    (float) ($summary["income"] ?? 0);


/*
|--------------------------------------------------------------------------
| Spending Prediction
|--------------------------------------------------------------------------
|
| This is an explainable linear projection.
|
| Average Daily Spending:
| expenses so far / days passed
|
| Predicted Month-End Spending:
| average daily spending * number of days in month
|
*/

$projection = sslms_linear_spending_projection($totalExpenses, $daysPassed, $daysInMonth);
$averageDailySpending = $projection['average_daily'];
$predictedRemainingSpending = $projection['remaining'];
$predictedMonthEndSpending = $projection['month_end'];

/*
|--------------------------------------------------------------------------
| For completed historical months
|--------------------------------------------------------------------------
|
| There is nothing left to predict once the month is complete,
| so actual spending becomes the final amount.
|
*/

if ($selectedMonth < $currentMonth) {

    $predictedMonthEndSpending =
        $totalExpenses;

    $predictedRemainingSpending = 0;
}


/*
|--------------------------------------------------------------------------
| Difference between prediction and budget
|--------------------------------------------------------------------------
*/

$predictedDifference = 0;

if ($monthlyBudget > 0) {

    $predictedDifference =
        $predictedMonthEndSpending
        - $monthlyBudget;
}


/*
|--------------------------------------------------------------------------
| Predicted budget usage
|--------------------------------------------------------------------------
*/

$predictedBudgetPercentage = 0;

if ($monthlyBudget > 0) {

    $predictedBudgetPercentage =
        (
            $predictedMonthEndSpending /
            $monthlyBudget
        ) * 100;
}


/*
|--------------------------------------------------------------------------
| Current budget usage
|--------------------------------------------------------------------------
*/

$currentBudgetPercentage = 0;

if ($monthlyBudget > 0) {

    $currentBudgetPercentage =
        (
            $totalExpenses /
            $monthlyBudget
        ) * 100;
}


/*
|--------------------------------------------------------------------------
| Month progress
|--------------------------------------------------------------------------
*/

$monthProgressPercentage = 0;

if ($daysInMonth > 0) {

    $monthProgressPercentage =
        (
            $daysPassed /
            $daysInMonth
        ) * 100;
}


/*
|--------------------------------------------------------------------------
| Prediction status
|--------------------------------------------------------------------------
*/

$predictionStatus = "NO DATA";

$statusClass = "secondary";

$statusIcon = "bi-dash-circle";

$statusMessage =
    "There is not enough spending information to make a prediction.";


if ($selectedMonth > $currentMonth) {

    $predictionStatus = "FUTURE MONTH";

    $statusClass = "secondary";

    $statusIcon = "bi-calendar-event";

    $statusMessage =
        "Spending prediction will become available once this month begins.";

} elseif ($totalExpenses <= 0) {

    $predictionStatus = "NO DATA";

    $statusClass = "secondary";

    $statusIcon = "bi-dash-circle";

    $statusMessage =
        "Add expenses to Finance to generate a spending prediction.";

} elseif ($monthlyBudget <= 0) {

    $predictionStatus = "NO BUDGET";

    $statusClass = "secondary";

    $statusIcon = "bi-wallet2";

    $statusMessage =
        "A spending prediction is available, but set a monthly budget "
        . "to determine whether the prediction is financially safe.";

} elseif ($predictedBudgetPercentage > 110) {

    $predictionStatus = "HIGH RISK";

    $statusClass = "danger";

    $statusIcon = "bi-exclamation-octagon-fill";

    $statusMessage =
        "Your current spending pattern is predicted to exceed "
        . "your monthly budget significantly.";

} elseif ($predictedBudgetPercentage > 100) {

    $predictionStatus = "OVER BUDGET";

    $statusClass = "danger";

    $statusIcon = "bi-exclamation-triangle-fill";

    $statusMessage =
        "At your current spending rate, you are predicted "
        . "to exceed your monthly budget.";

} elseif ($predictedBudgetPercentage >= 90) {

    $predictionStatus = "CAUTION";

    $statusClass = "warning";

    $statusIcon = "bi-exclamation-circle-fill";

    $statusMessage =
        "Your predicted spending is close to your monthly budget.";

} else {

    $predictionStatus = "ON TRACK";

    $statusClass = "success";

    $statusIcon = "bi-check-circle-fill";

    $statusMessage =
        "Your current spending pattern is predicted to remain "
        . "within your monthly budget.";
}


/*
|--------------------------------------------------------------------------
| Smart recommendations
|--------------------------------------------------------------------------
*/

$recommendations = [];


if (
    $selectedMonth === $currentMonth &&
    $totalExpenses > 0
) {

    $recommendations[] =
        "You are currently spending an average of £"
        . number_format(
            $averageDailySpending,
            2
        )
        . " per day.";


    if (
        $monthlyBudget > 0 &&
        $predictedDifference > 0
    ) {

        $recommendations[] =
            "If your current spending pattern continues, "
            . "you may exceed your monthly budget by approximately £"
            . number_format(
                $predictedDifference,
                2
            )
            . ".";
    }


    if (
        $monthlyBudget > 0 &&
        $predictedDifference <= 0
    ) {

        $recommendations[] =
            "Your predicted spending is approximately £"
            . number_format(
                abs($predictedDifference),
                2
            )
            . " below your monthly budget.";
    }


    if (
        $monthlyBudget > 0 &&
        $daysRemaining > 0
    ) {

        $remainingBudget =
            $monthlyBudget - $totalExpenses;

        if ($remainingBudget > 0) {

            $safeDailyAllowance =
                $remainingBudget
                / $daysRemaining;

            $recommendations[] =
                "To remain within budget, try to keep your "
                . "average spending below £"
                . number_format(
                    $safeDailyAllowance,
                    2
                )
                . " per day for the remaining "
                . $daysRemaining
                . " day"
                . ($daysRemaining === 1 ? "" : "s")
                . ".";
        }
    }


    if (
        $currentBudgetPercentage >
        ($monthProgressPercentage + 10)
    ) {

        $recommendations[] =
            "You have used your budget faster than the month "
            . "has progressed, which indicates a higher risk "
            . "of overspending.";
    }
}


/*
|--------------------------------------------------------------------------
| Spending by category
|--------------------------------------------------------------------------
*/

$categoryQuery = $pdo->prepare(
    "
    SELECT
        category,
        SUM(amount) AS total

    FROM transactions

    WHERE user_id = ?
    AND type = 'expense'
    AND transaction_date BETWEEN ? AND ?

    GROUP BY category

    ORDER BY total DESC
    "
);

$categoryQuery->execute([
    $userId,
    $monthStart,
    $recordedThrough
]);

$categories =
    $categoryQuery->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Largest spending category recommendation
|--------------------------------------------------------------------------
*/

if (
    !empty($categories) &&
    $totalExpenses > 0
) {

    $largestCategory =
        $categories[0]["category"];

    $largestCategoryAmount =
        (float) $categories[0]["total"];

    $largestCategoryPercentage =
        (
            $largestCategoryAmount /
            $totalExpenses
        ) * 100;


    if ($largestCategoryPercentage >= 40) {

        $recommendations[] =
            ucfirst($largestCategory)
            . " is your largest spending category and represents "
            . number_format(
                $largestCategoryPercentage,
                0
            )
            . "% of your expenses.";
    }
}


$recommendations =
    array_values(
        array_unique(
            $recommendations
        )
    );

?>

<?php require_once("../includes/header.php"); ?>
<?php require_once("../includes/sidebar.php"); ?>


<main class="main-content">

    <div class="container-fluid">


        <!-- Page heading -->

        <div class="page-heading
                    d-flex
                    justify-content-between
                    align-items-start
                    flex-wrap
                    gap-3">

            <div>

                <div class="page-kicker">
                    INTELLIGENT FINANCE
                </div>

                <h1 class="mb-2">
                    Spending Prediction
                </h1>

                <p class="text-muted mb-0">
                    Estimate your month-end spending using your
                    current daily spending pattern.
                </p>

            </div>


            <div class="d-flex flex-wrap gap-2">

                <a
                    href="budget_advisor.php"
                    class="btn btn-outline-primary"
                >

                    <i class="bi bi-stars me-2"></i>
                    Budget Advisor

                </a>


                <a
                    href="index.php"
                    class="btn btn-outline-secondary"
                >

                    <i class="bi bi-wallet2 me-2"></i>
                    Finance

                </a>

            </div>

        </div>


        <!-- Month selector -->

        <div class="content-card mt-4">

            <div class="p-4">

                <form
                    method="GET"
                    class="row align-items-end g-3"
                >

                    <div class="col-lg-5">

                        <label class="form-label fw-semibold">
                            Prediction Month
                        </label>

                        <input
                            type="month"
                            name="month"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $selectedMonth
                            ) ?>"
                        >

                    </div>


                    <div class="col-lg-3">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-graph-up-arrow me-2"></i>
                            Analyse Month

                        </button>

                    </div>

                </form>

            </div>

        </div>


        <!-- Prediction status -->

        <div class="content-card mt-4">

            <div class="p-4">

                <div class="row align-items-center g-4">

                    <div class="col-lg-8">

                        <div class="page-kicker">
                            PREDICTION STATUS
                        </div>

                        <div class="d-flex
                                    align-items-center
                                    flex-wrap
                                    gap-3
                                    mb-3">

                            <h2 class="mb-0">
                                <?= htmlspecialchars(
                                    $monthLabel
                                ) ?>
                            </h2>


                            <span
                                class="badge
                                       bg-<?= $statusClass ?>
                                       fs-6
                                       p-2"
                            >

                                <i class="bi
                                          <?= $statusIcon ?>
                                          me-1"></i>

                                <?= htmlspecialchars(
                                    $predictionStatus
                                ) ?>

                            </span>

                        </div>


                        <p class="text-muted mb-0">

                            <?= htmlspecialchars(
                                $statusMessage
                            ) ?>

                        </p>

                    </div>


                    <div class="col-lg-4 text-lg-end">

                        <div class="text-muted small">
                            Predicted Month-End Spending
                        </div>

                        <h1 class="mb-0">

                            £<?= number_format(
                                $predictedMonthEndSpending,
                                2
                            ) ?>

                        </h1>

                    </div>

                </div>

            </div>

        </div>


        <!-- Prediction statistics -->

        <div class="row g-4 mt-1">


            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">
                        <i class="bi bi-wallet2"></i>
                    </div>

                    <div>

                        <div class="text-muted small">
                            Monthly Budget
                        </div>

                        <h4 class="mb-0">

                            £<?= number_format(
                                $monthlyBudget,
                                2
                            ) ?>

                        </h4>

                    </div>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">
                        <i class="bi bi-cart3"></i>
                    </div>

                    <div>

                        <div class="text-muted small">
                            Spent So Far
                        </div>

                        <h4 class="mb-0">

                            £<?= number_format(
                                $totalExpenses,
                                2
                            ) ?>

                        </h4>

                    </div>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">
                        <i class="bi bi-calendar-day"></i>
                    </div>

                    <div>

                        <div class="text-muted small">
                            Daily Average
                        </div>

                        <h4 class="mb-0">

                            £<?= number_format(
                                $averageDailySpending,
                                2
                            ) ?>

                        </h4>

                    </div>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>

                    <div>

                        <div class="text-muted small">
                            Predicted Spending
                        </div>

                        <h4 class="mb-0">

                            £<?= number_format(
                                $predictedMonthEndSpending,
                                2
                            ) ?>

                        </h4>

                    </div>

                </div>

            </div>

        </div>


        <div class="row g-4 mt-1">


            <!-- Prediction comparison -->

            <div class="col-xl-7">

                <div class="content-card h-100">

                    <div class="content-card-header">

                        <div>

                            <h4 class="mb-1">
                                <i class="bi bi-graph-up me-2"></i>
                                Budget Prediction
                            </h4>

                            <p class="text-muted mb-0">
                                Current spending compared with the
                                predicted end-of-month result.
                            </p>

                        </div>

                    </div>


                    <div class="p-4">

                        <?php if ($monthlyBudget <= 0): ?>

                            <div class="alert alert-warning">

                                <i class="bi bi-exclamation-circle me-2"></i>

                                Set a monthly budget in Budget Advisor
                                to compare your prediction against a
                                financial target.

                            </div>

                            <a
                                href="budget_advisor.php?month=<?=
                                    urlencode(
                                        $selectedMonth
                                    )
                                ?>"
                                class="btn btn-primary"
                            >

                                Set Monthly Budget

                            </a>

                        <?php else: ?>


                            <div class="mb-4">

                                <div class="d-flex
                                            justify-content-between
                                            mb-2">

                                    <span>
                                        Current spending
                                    </span>

                                    <strong>

                                        <?= number_format(
                                            $currentBudgetPercentage,
                                            1
                                        ) ?>%

                                    </strong>

                                </div>


                                <div
                                    class="progress"
                                    style="height: 10px;"
                                >

                                    <div
                                        class="progress-bar"
                                        style="width: <?=
                                            min(
                                                100,
                                                $currentBudgetPercentage
                                            )
                                        ?>%;"
                                    ></div>

                                </div>

                            </div>


                            <div class="mb-4">

                                <div class="d-flex
                                            justify-content-between
                                            mb-2">

                                    <span>
                                        Predicted month-end spending
                                    </span>

                                    <strong>

                                        <?= number_format(
                                            $predictedBudgetPercentage,
                                            1
                                        ) ?>%

                                    </strong>

                                </div>


                                <div
                                    class="progress"
                                    style="height: 10px;"
                                >

                                    <div
                                        class="progress-bar
                                               bg-<?=
                                                   $statusClass
                                               ?>"
                                        style="width: <?=
                                            min(
                                                100,
                                                $predictedBudgetPercentage
                                            )
                                        ?>%;"
                                    ></div>

                                </div>

                            </div>


                            <div class="row g-3 mt-2">

                                <div class="col-md-6">

                                    <div class="border
                                                rounded
                                                p-3
                                                h-100">

                                        <div class="text-muted small">
                                            Predicted Remaining Spending
                                        </div>

                                        <h4 class="mb-0 mt-1">

                                            £<?= number_format(
                                                $predictedRemainingSpending,
                                                2
                                            ) ?>

                                        </h4>

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="border
                                                rounded
                                                p-3
                                                h-100">

                                        <div class="text-muted small">

                                            <?=
                                                $predictedDifference > 0
                                                    ? "Predicted Overspend"
                                                    : "Predicted Under Budget"
                                            ?>

                                        </div>

                                        <h4 class="mb-0 mt-1 <?=
                                            $predictedDifference > 0
                                                ? "text-danger"
                                                : "text-success"
                                        ?>">

                                            £<?= number_format(
                                                abs(
                                                    $predictedDifference
                                                ),
                                                2
                                            ) ?>

                                        </h4>

                                    </div>

                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <!-- Time progress -->

            <div class="col-xl-5">

                <div class="content-card h-100">

                    <div class="content-card-header">

                        <h4 class="mb-0">
                            Monthly Progress
                        </h4>

                    </div>


                    <div class="p-4">

                        <div class="mb-4">

                            <div class="text-muted small mb-1">
                                Days Passed
                            </div>

                            <h3>
                                <?= $daysPassed ?>
                                <small class="text-muted fs-6">
                                    / <?= $daysInMonth ?>
                                </small>
                            </h3>

                        </div>


                        <div class="mb-4">

                            <div class="d-flex
                                        justify-content-between
                                        mb-2">

                                <span>
                                    Month progress
                                </span>

                                <strong>

                                    <?= number_format(
                                        $monthProgressPercentage,
                                        1
                                    ) ?>%

                                </strong>

                            </div>


                            <div
                                class="progress"
                                style="height: 10px;"
                            >

                                <div
                                    class="progress-bar"
                                    style="width: <?=
                                        min(
                                            100,
                                            $monthProgressPercentage
                                        )
                                    ?>%;"
                                ></div>

                            </div>

                        </div>


                        <hr>


                        <div class="row">

                            <div class="col-6">

                                <div class="text-muted small">
                                    Days Remaining
                                </div>

                                <h4>
                                    <?= $daysRemaining ?>
                                </h4>

                            </div>


                            <div class="col-6">

                                <div class="text-muted small">
                                    Monthly Income
                                </div>

                                <h4 class="text-success">

                                    £<?= number_format(
                                        $totalIncome,
                                        2
                                    ) ?>

                                </h4>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Recommendations -->

        <div class="content-card mt-4">

            <div class="content-card-header">

                <div>

                    <h4 class="mb-1">
                        <i class="bi bi-lightbulb-fill me-2"></i>
                        Prediction Insights
                    </h4>

                    <p class="text-muted mb-0">
                        Recommendations generated from your
                        current spending pattern.
                    </p>

                </div>

            </div>


            <div class="p-4">

                <?php if (empty($recommendations)): ?>

                    <div class="empty-state">

                        <i class="bi bi-graph-up-arrow"></i>

                        <h5>
                            No prediction insights yet
                        </h5>

                        <p>
                            Add expense transactions to generate
                            spending predictions and recommendations.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="row g-3">

                        <?php foreach (
                            $recommendations as $recommendation
                        ): ?>

                            <div class="col-lg-6">

                                <div class="border
                                            rounded
                                            p-3
                                            h-100">

                                    <div class="d-flex gap-3">

                                        <div class="stat-icon">

                                            <i class="bi
                                                      bi-lightbulb"></i>

                                        </div>

                                        <div>

                                            <?= htmlspecialchars(
                                                $recommendation
                                            ) ?>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- Explanation -->

        <div class="content-card mt-4">

            <div class="content-card-header">

                <h4 class="mb-0">
                    How This Prediction Works
                </h4>

            </div>


            <div class="p-4">

                <p class="text-muted">
                    SSLMS uses a transparent spending-rate
                    calculation rather than a hidden prediction
                    model. This allows the student to understand
                    why a prediction was generated.
                </p>


                <div class="row g-3">

                    <div class="col-lg-4">

                        <div class="border rounded p-3 h-100">

                            <strong>
                                1. Daily Spending
                            </strong>

                            <div class="text-muted mt-2">

                                Expenses so far
                                ÷
                                days passed

                            </div>

                        </div>

                    </div>


                    <div class="col-lg-4">

                        <div class="border rounded p-3 h-100">

                            <strong>
                                2. Remaining Prediction
                            </strong>

                            <div class="text-muted mt-2">

                                Daily average
                                ×
                                days remaining

                            </div>

                        </div>

                    </div>


                    <div class="col-lg-4">

                        <div class="border rounded p-3 h-100">

                            <strong>
                                3. Month-End Prediction
                            </strong>

                            <div class="text-muted mt-2">

                                Current expenses
                                +
                                predicted remaining expenses

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</main>


<?php require_once("../includes/footer.php"); ?>