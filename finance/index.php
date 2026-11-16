<?php

require_once("../includes/auth_check.php");
require_once __DIR__ . '/../includes/validation.php';
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Search and filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");
$typeFilter = $_GET["type"] ?? "";
$categoryFilter = $_GET["category"] ?? "";
$monthFilter = $_GET["month"] ?? "";


/*
|--------------------------------------------------------------------------
| Validate transaction type filter
|--------------------------------------------------------------------------
*/

$allowedTypes = [
    "income",
    "expense"
];

if (
    $typeFilter !== "" &&
    !in_array($typeFilter, $allowedTypes, true)
) {
    $typeFilter = "";
}


/*
|--------------------------------------------------------------------------
| Validate month filter
|--------------------------------------------------------------------------
*/

if (
    $monthFilter !== "" &&
    !valid_month($monthFilter)
) {
    $monthFilter = "";
}


/*
|--------------------------------------------------------------------------
| Overall financial summary
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
        ) AS total_expenses,

        COUNT(*) AS total_transactions

    FROM transactions

    WHERE user_id = ?
    "
);

$summaryQuery->execute([
    $userId
]);

$summary = $summaryQuery->fetch(PDO::FETCH_ASSOC);

$totalIncome =
    (float) ($summary["total_income"] ?? 0);

$totalExpenses =
    (float) ($summary["total_expenses"] ?? 0);

$totalTransactions =
    (int) ($summary["total_transactions"] ?? 0);

$currentBalance =
    $totalIncome - $totalExpenses;


/*
|--------------------------------------------------------------------------
| Current month financial summary
|--------------------------------------------------------------------------
*/

$currentMonthStart = date("Y-m-01");
$currentMonthEnd = date("Y-m-t");

$monthlyQuery = $pdo->prepare(
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
        ) AS monthly_income,

        COALESCE(
            SUM(
                CASE
                    WHEN type = 'expense'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS monthly_expenses

    FROM transactions

    WHERE user_id = ?
    AND transaction_date BETWEEN ? AND ?
    "
);

$monthlyQuery->execute([
    $userId,
    $currentMonthStart,
    $currentMonthEnd
]);

$monthlySummary =
    $monthlyQuery->fetch(PDO::FETCH_ASSOC);

$monthlyIncome =
    (float) ($monthlySummary["monthly_income"] ?? 0);

$monthlyExpenses =
    (float) ($monthlySummary["monthly_expenses"] ?? 0);

$monthlyNet =
    $monthlyIncome - $monthlyExpenses;


/*
|--------------------------------------------------------------------------
| Spending percentage against monthly income
|--------------------------------------------------------------------------
*/

$spendingPercentage = 0;

if ($monthlyIncome > 0) {

    $spendingPercentage =
        ($monthlyExpenses / $monthlyIncome) * 100;
}


/*
|--------------------------------------------------------------------------
| Get current monthly budget
|--------------------------------------------------------------------------
|
| This connects the normal Finance page to the Budget Advisor.
|
*/

$currentBudget = 0;

try {

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
        $currentMonthStart
    ]);

    $budgetRow =
        $budgetQuery->fetch(PDO::FETCH_ASSOC);

    if ($budgetRow) {

        $currentBudget =
            (float) $budgetRow["budget_amount"];
    }

} catch (PDOException $e) {

    /*
     * Finance can still load if the monthly_budgets
     * table has not been created yet.
     */

    $currentBudget = 0;
}


/*
|--------------------------------------------------------------------------
| Current budget usage
|--------------------------------------------------------------------------
*/

$budgetUsedPercentage = 0;
$budgetRemaining = 0;

if ($currentBudget > 0) {

    $budgetUsedPercentage =
        ($monthlyExpenses / $currentBudget) * 100;

    $budgetRemaining =
        $currentBudget - $monthlyExpenses;
}


/*
|--------------------------------------------------------------------------
| Budget status
|--------------------------------------------------------------------------
*/

$budgetStatus = "Not Set";
$budgetStatusClass = "secondary";

if ($currentBudget > 0) {

    if ($monthlyExpenses > $currentBudget) {

        $budgetStatus = "Over Budget";
        $budgetStatusClass = "danger";

    } elseif ($budgetUsedPercentage >= 90) {

        $budgetStatus = "High Risk";
        $budgetStatusClass = "danger";

    } elseif ($budgetUsedPercentage >= 75) {

        $budgetStatus = "Caution";
        $budgetStatusClass = "warning";

    } else {

        $budgetStatus = "Safe";
        $budgetStatusClass = "success";
    }
}


/*
|--------------------------------------------------------------------------
| Expense category breakdown for current month
|--------------------------------------------------------------------------
*/

$categorySummaryQuery = $pdo->prepare(
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

$categorySummaryQuery->execute([
    $userId,
    $currentMonthStart,
    $currentMonthEnd
]);

$categorySummary =
    $categorySummaryQuery->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Largest expense category
|--------------------------------------------------------------------------
*/

$largestCategory = null;
$largestCategoryAmount = 0;

if (!empty($categorySummary)) {

    $largestCategory =
        $categorySummary[0]["category"];

    $largestCategoryAmount =
        (float) $categorySummary[0]["total"];
}


/*
|--------------------------------------------------------------------------
| Get available transaction categories
|--------------------------------------------------------------------------
*/

$categoriesQuery = $pdo->prepare(
    "
    SELECT DISTINCT category

    FROM transactions

    WHERE user_id = ?
    AND category IS NOT NULL
    AND category <> ''

    ORDER BY category ASC
    "
);

$categoriesQuery->execute([
    $userId
]);

$availableCategories =
    $categoriesQuery->fetchAll(
        PDO::FETCH_COLUMN
    );


/*
|--------------------------------------------------------------------------
| Build transaction history query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        type,
        category,
        amount,
        transaction_date,
        description,
        created_at

    FROM transactions

    WHERE user_id = ?
";

$params = [
    $userId
];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            category LIKE ?
            OR description LIKE ?
        )
    ";

    $searchValue =
        "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Type filter
|--------------------------------------------------------------------------
*/

if ($typeFilter !== "") {

    $sql .= "
        AND type = ?
    ";

    $params[] = $typeFilter;
}


/*
|--------------------------------------------------------------------------
| Category filter
|--------------------------------------------------------------------------
*/

if ($categoryFilter !== "") {

    $sql .= "
        AND category = ?
    ";

    $params[] = $categoryFilter;
}


/*
|--------------------------------------------------------------------------
| Month filter
|--------------------------------------------------------------------------
*/

if ($monthFilter !== "") {

    $filterStart =
        $monthFilter . "-01";

    $filterEnd =
        date(
            "Y-m-t",
            strtotime($filterStart)
        );

    $sql .= "
        AND transaction_date
        BETWEEN ? AND ?
    ";

    $params[] = $filterStart;
    $params[] = $filterEnd;
}


/*
|--------------------------------------------------------------------------
| Sort newest transactions first
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        transaction_date DESC,
        id DESC
";

$transactionsQuery =
    $pdo->prepare($sql);

$transactionsQuery->execute($params);

$transactions =
    $transactionsQuery->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$successMessage = "";

if (isset($_GET["added"])) {

    $successMessage =
        "Transaction added successfully.";

} elseif (isset($_GET["updated"])) {

    $successMessage =
        "Transaction updated successfully.";

} elseif (isset($_GET["deleted"])) {

    $successMessage =
        "Transaction deleted successfully.";
}

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
                    STUDENT LIFE
                </div>

                <h1 class="mb-2">
                    Finance
                </h1>

                <p class="text-muted mb-0">
                    Track your income, expenses and monthly
                    financial position.
                </p>

            </div>


            <!-- Finance actions -->

            <div class="d-flex flex-wrap gap-2">

                <a href="budget_advisor.php"
                   class="btn btn-primary">

                    <i class="bi bi-stars me-2"></i>
                    Budget Advisor

                </a>


                <a href="spending_prediction.php"
                   class="btn btn-outline-primary">

                    <i class="bi bi-graph-up-arrow me-2"></i>
                    Spending Prediction

                </a>


                <a href="add_income.php"
                   class="btn btn-success">

                    <i class="bi bi-plus-circle me-2"></i>
                    Add Income

                </a>


                <a href="add_expense.php"
                   class="btn btn-outline-danger">

                    <i class="bi bi-dash-circle me-2"></i>
                    Add Expense

                </a>

            </div>

        </div>


        <?php if ($successMessage): ?>

            <div class="alert alert-success mt-4">

                <i class="bi bi-check-circle-fill me-2"></i>

                <?= htmlspecialchars(
                    $successMessage
                ) ?>

            </div>

        <?php endif; ?>


        <!-- Main financial statistics -->

        <div class="row g-4 mt-1">

            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">

                        <i class="bi bi-wallet2"></i>

                    </div>

                    <div>

                        <div class="text-muted small">
                            Current Balance
                        </div>

                        <h3 class="mb-0 <?=
                            $currentBalance < 0
                                ? "text-danger"
                                : ""
                        ?>">

                            £<?= number_format(
                                $currentBalance,
                                2
                            ) ?>

                        </h3>

                    </div>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">

                        <i class="bi bi-arrow-down-left-circle"></i>

                    </div>

                    <div>

                        <div class="text-muted small">
                            Total Income
                        </div>

                        <h3 class="mb-0 text-success">

                            £<?= number_format(
                                $totalIncome,
                                2
                            ) ?>

                        </h3>

                    </div>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">

                        <i class="bi bi-arrow-up-right-circle"></i>

                    </div>

                    <div>

                        <div class="text-muted small">
                            Total Expenses
                        </div>

                        <h3 class="mb-0 text-danger">

                            £<?= number_format(
                                $totalExpenses,
                                2
                            ) ?>

                        </h3>

                    </div>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card h-100">

                    <div class="stat-icon">

                        <i class="bi bi-receipt"></i>

                    </div>

                    <div>

                        <div class="text-muted small">
                            Transactions
                        </div>

                        <h3 class="mb-0">

                            <?= number_format(
                                $totalTransactions
                            ) ?>

                        </h3>

                    </div>

                </div>

            </div>

        </div>


        <!-- Monthly summary and Budget Advisor -->

        <div class="row g-4 mt-1">

            <!-- Monthly overview -->

            <div class="col-xl-7">

                <div class="content-card h-100">

                    <div class="content-card-header">

                        <div>

                            <h4 class="mb-1">
                                This Month
                            </h4>

                            <p class="text-muted mb-0">

                                <?= htmlspecialchars(
                                    date("F Y")
                                ) ?>

                                financial overview

                            </p>

                        </div>

                        <i class="bi bi-calendar3 fs-4"></i>

                    </div>


                    <div class="p-4">

                        <div class="row g-4">

                            <div class="col-md-4">

                                <div class="dashboard-finance-box">

                                    <div class="text-muted small mb-1">
                                        Income
                                    </div>

                                    <h4 class="text-success mb-0">

                                        £<?= number_format(
                                            $monthlyIncome,
                                            2
                                        ) ?>

                                    </h4>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <div class="dashboard-finance-box">

                                    <div class="text-muted small mb-1">
                                        Expenses
                                    </div>

                                    <h4 class="text-danger mb-0">

                                        £<?= number_format(
                                            $monthlyExpenses,
                                            2
                                        ) ?>

                                    </h4>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <div class="dashboard-finance-box">

                                    <div class="text-muted small mb-1">
                                        Net
                                    </div>

                                    <h4 class="mb-0 <?=
                                        $monthlyNet < 0
                                            ? "text-danger"
                                            : "text-success"
                                    ?>">

                                        £<?= number_format(
                                            $monthlyNet,
                                            2
                                        ) ?>

                                    </h4>

                                </div>

                            </div>

                        </div>


                        <div class="mt-4">

                            <div class="d-flex
                                        justify-content-between
                                        align-items-center
                                        mb-2">

                                <span class="text-muted">
                                    Expenses compared with income
                                </span>

                                <strong>

                                    <?= number_format(
                                        $spendingPercentage,
                                        1
                                    ) ?>%

                                </strong>

                            </div>


                            <?php

                            $incomeProgress =
                                min(
                                    100,
                                    max(
                                        0,
                                        $spendingPercentage
                                    )
                                );

                            ?>


                            <div
                                class="progress"
                                style="height: 10px;"
                            >

                                <div
                                    class="progress-bar <?=
                                        $spendingPercentage > 100
                                            ? "bg-danger"
                                            : (
                                                $spendingPercentage >= 80
                                                    ? "bg-warning"
                                                    : "bg-success"
                                            )
                                    ?>"
                                    style="width: <?=
                                        $incomeProgress
                                    ?>%;"
                                ></div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Smart Budget Advisor card -->

            <div class="col-xl-5">

                <div class="content-card h-100">

                    <div class="content-card-header">

                        <div>

                            <div class="page-kicker">
                                SMART INSIGHT
                            </div>

                            <h4 class="mb-0">
                                Budget Advisor
                            </h4>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-stars"></i>
                        </div>

                    </div>


                    <div class="p-4">

                        <?php if ($currentBudget <= 0): ?>

                            <div class="mb-4">

                                <span class="badge bg-secondary p-2 mb-3">
                                    BUDGET NOT SET
                                </span>

                                <h5>
                                    Set your monthly budget
                                </h5>

                                <p class="text-muted mb-0">
                                    Add a spending target to activate
                                    personalised budget recommendations
                                    and spending analysis.
                                </p>

                            </div>


                            <a href="budget_advisor.php"
                               class="btn btn-primary w-100">

                                <i class="bi bi-bullseye me-2"></i>
                                Set Monthly Budget

                            </a>

                        <?php else: ?>

                            <div class="d-flex
                                        justify-content-between
                                        align-items-center
                                        mb-3">

                                <span class="badge
                                             bg-<?=
                                                $budgetStatusClass
                                             ?>
                                             p-2">

                                    <?= htmlspecialchars(
                                        strtoupper(
                                            $budgetStatus
                                        )
                                    ) ?>

                                </span>

                                <strong>

                                    <?= number_format(
                                        $budgetUsedPercentage,
                                        1
                                    ) ?>%

                                </strong>

                            </div>


                            <div class="mb-3">

                                <div class="d-flex
                                            justify-content-between
                                            small
                                            mb-2">

                                    <span>
                                        £<?= number_format(
                                            $monthlyExpenses,
                                            2
                                        ) ?>
                                        spent
                                    </span>

                                    <span>
                                        £<?= number_format(
                                            $currentBudget,
                                            2
                                        ) ?>
                                        budget
                                    </span>

                                </div>


                                <?php

                                $budgetProgress =
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
                                    style="height: 10px;"
                                >

                                    <div
                                        class="progress-bar
                                               bg-<?=
                                                   $budgetStatusClass
                                               ?>"
                                        style="width: <?=
                                            $budgetProgress
                                        ?>%;"
                                    ></div>

                                </div>

                            </div>


                            <div class="border rounded p-3 mb-3">

                                <div class="text-muted small">
                                    Remaining Budget
                                </div>

                                <h4 class="mb-0 <?=
                                    $budgetRemaining < 0
                                        ? "text-danger"
                                        : ""
                                ?>">

                                    £<?= number_format(
                                        $budgetRemaining,
                                        2
                                    ) ?>

                                </h4>

                            </div>


                            <?php if ($largestCategory): ?>

                                <div class="small text-muted mb-4">

                                    <i class="bi bi-pie-chart-fill me-1"></i>

                                    Largest spending category:

                                    <strong>

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $largestCategory
                                            )
                                        ) ?>

                                    </strong>

                                    (£<?= number_format(
                                        $largestCategoryAmount,
                                        2
                                    ) ?>)

                                </div>

                            <?php endif; ?>


                            <div class="d-grid gap-2">

                                <a href="budget_advisor.php"
                                   class="btn btn-primary">

                                    <i class="bi bi-stars me-2"></i>
                                    View Smart Analysis

                                </a>

                                <a href="spending_prediction.php"
                                   class="btn btn-outline-primary">

                                    <i class="bi bi-graph-up-arrow me-2"></i>
                                    View Spending Prediction

                                </a>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- Spending categories -->

        <div class="content-card mt-4">

            <div class="content-card-header">

                <div>

                    <h4 class="mb-1">
                        This Month's Spending
                    </h4>

                    <p class="text-muted mb-0">
                        Expense distribution by category.
                    </p>

                </div>

                <i class="bi bi-pie-chart fs-4"></i>

            </div>


            <div class="p-4">

                <?php if (empty($categorySummary)): ?>

                    <div class="empty-state">

                        <i class="bi bi-pie-chart"></i>

                        <h5>
                            No expenses this month
                        </h5>

                        <p>
                            Add an expense to start seeing
                            your spending breakdown.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="row g-4">

                        <?php foreach (
                            $categorySummary as $category
                        ): ?>

                            <?php

                            $categoryAmount =
                                (float) $category["total"];

                            $categoryPercentage =
                                $monthlyExpenses > 0
                                    ? (
                                        $categoryAmount /
                                        $monthlyExpenses
                                    ) * 100
                                    : 0;

                            ?>


                            <div class="col-lg-6">

                                <div class="border rounded p-3 h-100">

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


                                        <strong>

                                            £<?= number_format(
                                                $categoryAmount,
                                                2
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div class="d-flex
                                                justify-content-between
                                                small
                                                text-muted
                                                mb-2">

                                        <span>
                                            Share of expenses
                                        </span>

                                        <span>

                                            <?= number_format(
                                                $categoryPercentage,
                                                1
                                            ) ?>%

                                        </span>

                                    </div>


                                    <div
                                        class="progress"
                                        style="height: 7px;"
                                    >

                                        <div
                                            class="progress-bar"
                                            style="width: <?=
                                                min(
                                                    100,
                                                    $categoryPercentage
                                                )
                                            ?>%;"
                                        ></div>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- Transaction history -->

        <div class="content-card mt-4">

            <div class="content-card-header">

                <div>

                    <h4 class="mb-1">
                        Transaction History
                    </h4>

                    <p class="text-muted mb-0">
                        Search and manage your financial records.
                    </p>

                </div>

            </div>


            <!-- Filters -->

            <div class="p-4 border-bottom">

                <form
                    method="GET"
                    class="row g-3 align-items-end"
                >

                    <div class="col-xl-3 col-md-6">

                        <label class="form-label">
                            Search
                        </label>

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Category or description..."
                            value="<?= htmlspecialchars(
                                $search
                            ) ?>"
                        >

                    </div>


                    <div class="col-xl-2 col-md-6">

                        <label class="form-label">
                            Type
                        </label>

                        <select
                            name="type"
                            class="form-select"
                        >

                            <option value="">
                                All Types
                            </option>

                            <option
                                value="income"
                                <?= $typeFilter === "income"
                                    ? "selected"
                                    : "" ?>
                            >
                                Income
                            </option>

                            <option
                                value="expense"
                                <?= $typeFilter === "expense"
                                    ? "selected"
                                    : "" ?>
                            >
                                Expense
                            </option>

                        </select>

                    </div>


                    <div class="col-xl-3 col-md-6">

                        <label class="form-label">
                            Category
                        </label>

                        <select
                            name="category"
                            class="form-select"
                        >

                            <option value="">
                                All Categories
                            </option>

                            <?php foreach (
                                $availableCategories as $category
                            ): ?>

                                <option
                                    value="<?= htmlspecialchars(
                                        $category
                                    ) ?>"
                                    <?=
                                        $categoryFilter === $category
                                            ? "selected"
                                            : ""
                                    ?>
                                >

                                    <?= htmlspecialchars(
                                        ucfirst($category)
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-xl-2 col-md-6">

                        <label class="form-label">
                            Month
                        </label>

                        <input
                            type="month"
                            name="month"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $monthFilter
                            ) ?>"
                        >

                    </div>


                    <div class="col-xl-2">

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary flex-grow-1"
                            >

                                <i class="bi bi-funnel me-1"></i>
                                Filter

                            </button>


                            <a
                                href="index.php"
                                class="btn btn-outline-secondary"
                                title="Clear filters"
                            >

                                <i class="bi bi-x-lg"></i>

                            </a>

                        </div>

                    </div>

                </form>

            </div>


            <!-- Transactions -->

            <div class="p-0">

                <?php if (empty($transactions)): ?>

                    <div class="empty-state p-5">

                        <i class="bi bi-receipt"></i>

                        <h5>
                            No transactions found
                        </h5>

                        <p>
                            Add your first income or expense,
                            or change the current filters.
                        </p>

                        <div class="d-flex
                                    justify-content-center
                                    flex-wrap
                                    gap-2">

                            <a
                                href="add_income.php"
                                class="btn btn-success"
                            >
                                Add Income
                            </a>

                            <a
                                href="add_expense.php"
                                class="btn btn-outline-danger"
                            >
                                Add Expense
                            </a>

                        </div>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table
                            class="table
                                   table-hover
                                   align-middle
                                   mb-0"
                        >

                            <thead>

                                <tr>

                                    <th class="ps-4">
                                        Transaction
                                    </th>

                                    <th>
                                        Category
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Type
                                    </th>

                                    <th class="text-end pe-4">
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach (
                                    $transactions as $transaction
                                ): ?>

                                    <?php

                                    $isIncome =
                                        $transaction["type"]
                                        === "income";

                                    ?>


                                    <tr>

                                        <td class="ps-4">

                                            <div class="d-flex
                                                        align-items-center
                                                        gap-3">

                                                <div class="stat-icon">

                                                    <i class="bi <?=
                                                        $isIncome
                                                            ? "bi-arrow-down-left"
                                                            : "bi-arrow-up-right"
                                                    ?>"></i>

                                                </div>


                                                <div>

                                                    <strong>

                                                        <?php

                                                        $description =
                                                            trim(
                                                                $transaction[
                                                                    "description"
                                                                ] ?? ""
                                                            );

                                                        ?>

                                                        <?= htmlspecialchars(
                                                            $description !== ""
                                                                ? $description
                                                                : ucfirst(
                                                                    $transaction[
                                                                        "category"
                                                                    ]
                                                                )
                                                        ) ?>

                                                    </strong>


                                                    <div class="small text-muted">

                                                        #<?= (int)
                                                            $transaction["id"]
                                                        ?>

                                                    </div>

                                                </div>

                                            </div>

                                        </td>


                                        <td>

                                            <span class="badge
                                                         bg-light
                                                         text-dark
                                                         border">

                                                <?= htmlspecialchars(
                                                    ucfirst(
                                                        $transaction[
                                                            "category"
                                                        ]
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                date(
                                                    "d M Y",
                                                    strtotime(
                                                        $transaction[
                                                            "transaction_date"
                                                        ]
                                                    )
                                                )
                                            ) ?>

                                        </td>


                                        <td>

                                            <strong class="<?=
                                                $isIncome
                                                    ? "text-success"
                                                    : "text-danger"
                                            ?>">

                                                <?= $isIncome
                                                    ? "+"
                                                    : "-"
                                                ?>

                                                £<?= number_format(
                                                    (float)
                                                    $transaction[
                                                        "amount"
                                                    ],
                                                    2
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <span class="badge <?=
                                                $isIncome
                                                    ? "bg-success"
                                                    : "bg-danger"
                                            ?>">

                                                <?= htmlspecialchars(
                                                    ucfirst(
                                                        $transaction[
                                                            "type"
                                                        ]
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <td class="text-end pe-4">

                                            <div class="d-inline-flex gap-2">

                                                <a
                                                    href="edit_transaction.php?id=<?=
                                                        (int)
                                                        $transaction["id"]
                                                    ?>"
                                                    class="btn
                                                           btn-sm
                                                           btn-outline-primary"
                                                    title="Edit"
                                                >

                                                    <i class="bi bi-pencil"></i>

                                                </a>


                                                <a
                                                    href="delete_transaction.php?id=<?=
                                                        (int)
                                                        $transaction["id"]
                                                    ?>"
                                                    class="btn
                                                           btn-sm
                                                           btn-outline-danger"
                                                    title="Delete"
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

        </div>

    </div>

</main>


<?php require_once("../includes/footer.php"); ?>