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

// Like Spending Prediction, exclude future-dated transactions from current-month spending.
$recordedThrough = sslms_spending_recorded_through($selectedMonth);



/*
|--------------------------------------------------------------------------
| Save / update monthly budget
|--------------------------------------------------------------------------
*/

$successMessage = "";
$errorMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $budgetAmount =
        filter_input(
            INPUT_POST,
            "budget_amount",
            FILTER_VALIDATE_FLOAT
        );

    if (
        $budgetAmount === false ||
        $budgetAmount <= 0
    ) {

        $errorMessage =
            "Please enter a valid monthly budget greater than 0.";

    } else {

        $budgetAmount =
            round((float) $budgetAmount, 2);

        try {

            $budgetQuery = $pdo->prepare(
                "
                INSERT INTO monthly_budgets
                (
                    user_id,
                    budget_month,
                    budget_amount
                )
                VALUES (?, ?, ?)

                ON DUPLICATE KEY UPDATE
                    budget_amount = VALUES(budget_amount)
                "
            );

            $budgetQuery->execute([
                $userId,
                $monthStart,
                $budgetAmount
            ]);

            header(
                "Location: budget_advisor.php?month="
                . urlencode($selectedMonth)
                . "&saved=1"
            );

            exit;

        } catch (PDOException $e) {

            $errorMessage =
                "The monthly budget could not be saved.";
        }
    }
}


if (isset($_GET["saved"])) {

    $successMessage =
        "Your monthly budget has been updated.";
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
| Calculate monthly income and expenses
|--------------------------------------------------------------------------
*/

$summaryQuery = $pdo->prepare(
    "
    SELECT

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
        ) AS total_expenses

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

$totalIncome =
    (float) ($summary["total_income"] ?? 0);

$totalExpenses =
    (float) ($summary["total_expenses"] ?? 0);

$netAmount =
    $totalIncome - $totalExpenses;


/*
|--------------------------------------------------------------------------
| Budget calculations
|--------------------------------------------------------------------------
*/

$remainingBudget =
    $monthlyBudget - $totalExpenses;

$budgetUsedPercentage = 0;

if ($monthlyBudget > 0) {

    $budgetUsedPercentage =
        ($totalExpenses / $monthlyBudget) * 100;
}


/*
|--------------------------------------------------------------------------
| Time progress through selected month
|--------------------------------------------------------------------------
|
| For the current month, this allows us to compare:
|
| - how much of the month has passed
| - how much of the budget has already been used
|
*/

$today = new DateTime();

$selectedMonthDate =
    new DateTime($monthStart);

$currentMonth =
    date("Y-m");

$daysInMonth =
    (int) date(
        "t",
        strtotime($monthStart)
    );

if ($selectedMonth === $currentMonth) {

    $daysPassed =
        (int) date("j");

    $daysRemaining =
        max(
            0,
            $daysInMonth - $daysPassed
        );

} elseif ($selectedMonth < $currentMonth) {

    $daysPassed =
        $daysInMonth;

    $daysRemaining = 0;

} else {

    $daysPassed = 0;

    $daysRemaining =
        $daysInMonth;
}


$monthProgressPercentage = 0;

if ($daysInMonth > 0) {

    $monthProgressPercentage =
        ($daysPassed / $daysInMonth) * 100;
}


/*
|--------------------------------------------------------------------------
| Daily spending calculations
|--------------------------------------------------------------------------
*/

$averageDailySpending = 0;

if ($daysPassed > 0) {

    $averageDailySpending =
        $totalExpenses / $daysPassed;
}


$dailyAllowance = 0;

if (
    $remainingBudget > 0 &&
    $daysRemaining > 0
) {

    $dailyAllowance =
        $remainingBudget / $daysRemaining;
}


/*
|--------------------------------------------------------------------------
| Category spending
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


$largestCategory = null;
$largestCategoryAmount = 0;

if (!empty($categories)) {

    $largestCategory =
        $categories[0]["category"];

    $largestCategoryAmount =
        (float) $categories[0]["total"];
}


/*
|--------------------------------------------------------------------------
| Rule-Based Budget Advisor
|--------------------------------------------------------------------------
|
| The rules are intentionally transparent.
| This makes the recommendation explainable to the student.
|
*/

$budgetStatus = "NO BUDGET";
$statusClass = "secondary";
$statusIcon = "bi-dash-circle";

$recommendations = [];


if ($monthlyBudget <= 0) {

    $recommendations[] =
        "Set a monthly spending budget to activate personalised budget advice.";

} elseif ($totalExpenses > $monthlyBudget) {

    $budgetStatus = "OVER BUDGET";
    $statusClass = "danger";
    $statusIcon = "bi-exclamation-octagon-fill";

    $recommendations[] =
        "Your expenses have exceeded your monthly budget by £"
        . number_format(
            abs($remainingBudget),
            2
        )
        . ".";

    $recommendations[] =
        "Review non-essential spending for the rest of the month.";

} elseif ($budgetUsedPercentage >= 90) {

    $budgetStatus = "HIGH RISK";
    $statusClass = "danger";
    $statusIcon = "bi-exclamation-triangle-fill";

    $recommendations[] =
        "You have used more than 90% of your monthly budget.";

    if ($daysRemaining > 0) {

        $recommendations[] =
            "You still have "
            . $daysRemaining
            . " day"
            . ($daysRemaining === 1 ? "" : "s")
            . " remaining this month.";
    }

} elseif ($budgetUsedPercentage >= 75) {

    $budgetStatus = "CAUTION";
    $statusClass = "warning";
    $statusIcon = "bi-exclamation-circle-fill";

    $recommendations[] =
        "You have used at least 75% of your monthly budget.";

    $recommendations[] =
        "Consider limiting optional spending until the end of the month.";

} else {

    $budgetStatus = "SAFE";
    $statusClass = "success";
    $statusIcon = "bi-check-circle-fill";

    $recommendations[] =
        "Your total spending is currently within your monthly budget.";
}


/*
|--------------------------------------------------------------------------
| Spending pace rule
|--------------------------------------------------------------------------
|
| Example:
|
| 40% of month passed
| 65% of budget already spent
|
| Spending is running ahead of the month's progress.
|
*/

if (
    $monthlyBudget > 0 &&
    $selectedMonth === $currentMonth &&
    $budgetUsedPercentage >
        ($monthProgressPercentage + 10)
) {

    $recommendations[] =
        "Your spending is progressing faster than the month. "
        . "At the current pace, your budget may become difficult to maintain.";
}


/*
|--------------------------------------------------------------------------
| Largest spending category rule
|--------------------------------------------------------------------------
*/

if (
    $largestCategory !== null &&
    $totalExpenses > 0
) {

    $categoryPercentage =
        (
            $largestCategoryAmount /
            $totalExpenses
        ) * 100;

    if ($categoryPercentage >= 40) {

        $recommendations[] =
            ucfirst($largestCategory)
            . " represents "
            . number_format(
                $categoryPercentage,
                0
            )
            . "% of your spending this month. "
            . "Review this category for possible savings.";
    }
}


/*
|--------------------------------------------------------------------------
| Daily allowance recommendation
|--------------------------------------------------------------------------
*/

if (
    $monthlyBudget > 0 &&
    $remainingBudget > 0 &&
    $daysRemaining > 0
) {

    $recommendations[] =
        "To remain within budget, your remaining average allowance is approximately £"
        . number_format(
            $dailyAllowance,
            2
        )
        . " per day.";
}


/*
|--------------------------------------------------------------------------
| Remove duplicate recommendations
|--------------------------------------------------------------------------
*/

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
                    Budget Advisor
                </h1>

                <p class="text-muted mb-0">
                    Monitor your monthly spending and receive
                    rule-based financial recommendations.
                </p>

            </div>

            <a href="index.php"
               class="btn btn-outline-primary">

                <i class="bi bi-wallet2 me-2"></i>
                Finance

            </a>

        </div>


        <?php if ($successMessage): ?>

            <div class="alert alert-success mt-4">

                <i class="bi bi-check-circle-fill me-2"></i>

                <?= htmlspecialchars($successMessage) ?>

            </div>

        <?php endif; ?>


        <?php if ($errorMessage): ?>

            <div class="alert alert-danger mt-4">

                <i class="bi bi-exclamation-triangle-fill me-2"></i>

                <?= htmlspecialchars($errorMessage) ?>

            </div>

        <?php endif; ?>


        <!-- Month selector and budget -->

        <div class="content-card mt-4">

            <div class="p-4">

                <div class="row g-4 align-items-end">

                    <div class="col-lg-5">

                        <form method="GET">

                            <label class="form-label fw-semibold">
                                Analysis Month
                            </label>

                            <div class="input-group">

                                <input
                                    type="month"
                                    name="month"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $selectedMonth
                                    ) ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-outline-primary"
                                >
                                    View
                                </button>

                            </div>

                        </form>

                    </div>


                    <div class="col-lg-7">

                        <form method="POST">
                    <?= csrf_field() ?>

                            <label class="form-label fw-semibold">
                                Monthly Spending Budget
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    £
                                </span>

                                <input
                                    type="number"
                                    name="budget_amount"
                                    class="form-control"
                                    min="0.01"
                                    step="0.01"
                                    value="<?=
                                        $monthlyBudget > 0
                                            ? htmlspecialchars(
                                                number_format(
                                                    $monthlyBudget,
                                                    2,
                                                    ".",
                                                    ""
                                                )
                                            )
                                            : ""
                                    ?>"
                                    placeholder="Example: 800.00"
                                    required
                                >

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >

                                    <i class="bi bi-check2-circle me-1"></i>
                                    Save Budget

                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>


        <!-- Main statistics -->

        <div class="row g-4 mt-1">

            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">
                        <i class="bi bi-bullseye"></i>
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
                            Spent
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
                        <i class="bi bi-piggy-bank"></i>
                    </div>

                    <div>

                        <div class="text-muted small">
                            Remaining
                        </div>

                        <h4 class="mb-0 <?=
                            $remainingBudget < 0
                                ? "text-danger"
                                : ""
                        ?>">
                            £<?= number_format(
                                $remainingBudget,
                                2
                            ) ?>
                        </h4>

                    </div>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">
                        <i class="bi bi-percent"></i>
                    </div>

                    <div>

                        <div class="text-muted small">
                            Budget Used
                        </div>

                        <h4 class="mb-0">
                            <?= number_format(
                                $budgetUsedPercentage,
                                1
                            ) ?>%
                        </h4>

                    </div>

                </div>

            </div>

        </div>


        <div class="row g-4 mt-1">

            <!-- Advisor -->

            <div class="col-xl-7">

                <div class="content-card h-100">

                    <div class="content-card-header">

                        <div>

                            <h4 class="mb-1">
                                <i class="bi bi-stars me-2"></i>
                                Smart Budget Analysis
                            </h4>

                            <p class="text-muted mb-0">
                                Analysis for
                                <?= htmlspecialchars(
                                    $monthLabel
                                ) ?>
                            </p>

                        </div>

                        <span class="badge bg-<?=
                            $statusClass
                        ?> fs-6 p-2">

                            <i class="bi <?=
                                $statusIcon
                            ?> me-1"></i>

                            <?= htmlspecialchars(
                                $budgetStatus
                            ) ?>

                        </span>

                    </div>


                    <div class="p-4">

                        <?php if ($monthlyBudget > 0): ?>

                            <div class="mb-4">

                                <div class="d-flex
                                            justify-content-between
                                            mb-2">

                                    <span>
                                        Budget usage
                                    </span>

                                    <strong>
                                        <?= number_format(
                                            $budgetUsedPercentage,
                                            1
                                        ) ?>%
                                    </strong>

                                </div>

                                <?php

                                $progressWidth =
                                    min(
                                        100,
                                        max(
                                            0,
                                            $budgetUsedPercentage
                                        )
                                    );

                                ?>

                                <div
                                    class="progress"
                                    style="height: 12px;"
                                >

                                    <div
                                        class="progress-bar bg-<?=
                                            $statusClass
                                        ?>"
                                        style="width: <?=
                                            $progressWidth
                                        ?>%;"
                                    ></div>

                                </div>

                            </div>

                        <?php endif; ?>


                        <h6 class="fw-bold mb-3">
                            Recommendations
                        </h6>


                        <?php foreach (
                            $recommendations as $recommendation
                        ): ?>

                            <div class="d-flex
                                        gap-3
                                        border
                                        rounded
                                        p-3
                                        mb-3">

                                <div>

                                    <i class="bi bi-lightbulb-fill
                                              fs-4"></i>

                                </div>

                                <div>
                                    <?= htmlspecialchars(
                                        $recommendation
                                    ) ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>


            <!-- Spending information -->

            <div class="col-xl-5">

                <div class="content-card h-100">

                    <div class="content-card-header">

                        <h4 class="mb-0">
                            Spending Pace
                        </h4>

                    </div>


                    <div class="p-4">

                        <div class="mb-4">

                            <div class="text-muted small">
                                Average spent per day
                            </div>

                            <h3>
                                £<?= number_format(
                                    $averageDailySpending,
                                    2
                                ) ?>
                            </h3>

                        </div>


                        <hr>


                        <div class="mb-4">

                            <div class="text-muted small">
                                Recommended remaining daily allowance
                            </div>

                            <h3>
                                £<?= number_format(
                                    $dailyAllowance,
                                    2
                                ) ?>
                            </h3>

                        </div>


                        <hr>


                        <div class="row">

                            <div class="col-6">

                                <div class="text-muted small">
                                    Days Passed
                                </div>

                                <strong>
                                    <?= $daysPassed ?>
                                </strong>

                            </div>


                            <div class="col-6">

                                <div class="text-muted small">
                                    Days Remaining
                                </div>

                                <strong>
                                    <?= $daysRemaining ?>
                                </strong>

                            </div>

                        </div>


                        <hr>


                        <div>

                            <div class="text-muted small">
                                Income this month
                            </div>

                            <strong class="text-success">
                                £<?= number_format(
                                    $totalIncome,
                                    2
                                ) ?>
                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Category analysis -->

        <div class="content-card mt-4">

            <div class="content-card-header">

                <div>

                    <h4 class="mb-1">
                        Spending by Category
                    </h4>

                    <p class="text-muted mb-0">
                        See where your money is going.
                    </p>

                </div>

            </div>


            <div class="p-4">

                <?php if (empty($categories)): ?>

                    <div class="empty-state">

                        <i class="bi bi-pie-chart"></i>

                        <h5>
                            No expense data
                        </h5>

                        <p>
                            Add expenses to Finance to receive
                            category-based analysis.
                        </p>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $categories as $category
                    ): ?>

                        <?php

                        $categoryAmount =
                            (float) $category["total"];

                        $percentage =
                            $totalExpenses > 0
                                ? (
                                    $categoryAmount /
                                    $totalExpenses
                                ) * 100
                                : 0;

                        ?>

                        <div class="mb-4">

                            <div class="d-flex
                                        justify-content-between
                                        align-items-center
                                        mb-2">

                                <strong>
                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $category["category"]
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    £<?= number_format(
                                        $categoryAmount,
                                        2
                                    ) ?>

                                    <small class="text-muted">
                                        (<?= number_format(
                                            $percentage,
                                            1
                                        ) ?>%)
                                    </small>
                                </span>

                            </div>


                            <div
                                class="progress"
                                style="height: 8px;"
                            >

                                <div
                                    class="progress-bar"
                                    style="width: <?=
                                        min(
                                            100,
                                            $percentage
                                        )
                                    ?>%;"
                                ></div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>

</main>


<?php require_once("../includes/footer.php"); ?>