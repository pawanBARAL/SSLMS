<?php

/*
|--------------------------------------------------------------------------
| Add Expense
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];

$category = "";
$amount = "";
$transactionDate = date("Y-m-d");
$description = "";

$error = "";

$expenseCategories = [
    "Rent",
    "Food",
    "Transport",
    "Bills",
    "University",
    "Books",
    "Shopping",
    "Entertainment",
    "Health",
    "Subscriptions",
    "Other"
];


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $category =
        trim($_POST["category"] ?? "");

    $amount =
        trim($_POST["amount"] ?? "");

    $transactionDate =
        trim($_POST["transaction_date"] ?? "");

    $description =
        trim($_POST["description"] ?? "");


    if ($category === "") {

        $error =
            "Please select an expense category.";

    } elseif (
        $amount === ""
        ||
        !is_numeric($amount)
        ||
        (float) $amount <= 0
    ) {

        $error =
            "Please enter a valid expense amount.";

    } elseif ($transactionDate === "") {

        $error =
            "Please select the expense date.";

    } else {

        $insertQuery = $pdo->prepare(
            "INSERT INTO transactions
            (
                user_id,
                type,
                category,
                amount,
                transaction_date,
                description
            )
            VALUES (?, 'expense', ?, ?, ?, ?)"
        );

        $insertQuery->execute([
            $userId,
            $category,
            (float) $amount,
            $transactionDate,
            $description !== ""
                ? $description
                : null
        ]);

        header(
            "Location: index.php?added=expense"
        );

        exit;
    }
}


$pageTitle = "Add Expense";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">

    <div class="page-heading">

        <p class="page-kicker">
            FINANCE
        </p>

        <h1>
            Add Expense
        </h1>

        <p class="text-muted mb-0">
            Record your student spending and keep
            your financial overview accurate.
        </p>

    </div>


    <div class="row">

        <div class="col-xl-7 col-lg-9">

            <div class="content-card">

                <div class="content-card-header">

                    <div>

                        <h3>
                            Expense Details
                        </h3>

                        <small class="text-muted">
                            Enter the details of your expense.
                        </small>

                    </div>

                    <div class="stat-icon">
                        <i class="bi bi-arrow-up-right-circle"></i>
                    </div>

                </div>


                <?php if ($error !== ""): ?>

                    <div class="alert alert-danger">

                        <i class="bi bi-exclamation-circle me-2"></i>

                        <?php echo htmlspecialchars($error); ?>

                    </div>

                <?php endif; ?>


                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label
                                for="category"
                                class="form-label"
                            >
                                Expense Category *
                            </label>

                            <select
                                id="category"
                                name="category"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select category
                                </option>

                                <?php foreach ($expenseCategories as $expenseCategory): ?>

                                    <option
                                        value="<?php echo htmlspecialchars($expenseCategory); ?>"
                                        <?php
                                        echo
                                            $category === $expenseCategory
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        <?php echo htmlspecialchars($expenseCategory); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label
                                for="amount"
                                class="form-label"
                            >
                                Amount *
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    £
                                </span>

                                <input
                                    type="number"
                                    id="amount"
                                    name="amount"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($amount); ?>"
                                    min="0.01"
                                    step="0.01"
                                    placeholder="0.00"
                                    required
                                >

                            </div>

                        </div>


                        <div class="col-md-6">

                            <label
                                for="transaction_date"
                                class="form-label"
                            >
                                Date *
                            </label>

                            <input
                                type="date"
                                id="transaction_date"
                                name="transaction_date"
                                class="form-control"
                                value="<?php echo htmlspecialchars($transactionDate); ?>"
                                required
                            >

                        </div>


                        <div class="col-12">

                            <label
                                for="description"
                                class="form-label"
                            >
                                Description
                            </label>

                            <textarea
                                id="description"
                                name="description"
                                class="form-control"
                                rows="4"
                                placeholder="Optional notes about this expense..."
                            ><?php echo htmlspecialchars($description); ?></textarea>

                        </div>

                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-check-lg me-1"></i>
                            Save Expense
                        </button>

                        <a
                            href="index.php"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </a>

                    </div>

                </form>

            </div>

        </div>

    </div>

</main>


<?php
require_once("../includes/footer.php");
?>