<?php

/*
|--------------------------------------------------------------------------
| Edit Transaction
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];

$transactionId =
    filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );

if (!$transactionId) {

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Retrieve Transaction
|--------------------------------------------------------------------------
*/

$findQuery = $pdo->prepare(
    "SELECT *
     FROM transactions
     WHERE id = ?
     AND user_id = ?
     LIMIT 1"
);

$findQuery->execute([
    $transactionId,
    $userId
]);

$transaction = $findQuery->fetch();

if (!$transaction) {

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Current Values
|--------------------------------------------------------------------------
*/

$type = $transaction["type"];
$category = $transaction["category"];
$amount = $transaction["amount"];
$transactionDate = $transaction["transaction_date"];
$description = $transaction["description"] ?? "";

$error = "";

$incomeCategories = [
    "Salary",
    "Part-time Work",
    "Student Finance",
    "Scholarship",
    "Family Support",
    "Freelance",
    "Refund",
    "Other"
];

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


/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $type =
        trim($_POST["type"] ?? "");

    $category =
        trim($_POST["category"] ?? "");

    $amount =
        trim($_POST["amount"] ?? "");

    $transactionDate =
        trim($_POST["transaction_date"] ?? "");

    $description =
        trim($_POST["description"] ?? "");


    if (!in_array($type, ["income", "expense"], true)) {

        $error =
            "Please select a valid transaction type.";

    } elseif ($category === "") {

        $error =
            "Please select a category.";

    } elseif (
        $amount === ""
        ||
        !is_numeric($amount)
        ||
        (float) $amount <= 0
    ) {

        $error =
            "Please enter a valid amount.";

    } elseif ($transactionDate === "") {

        $error =
            "Please select a transaction date.";

    } else {

        $updateQuery = $pdo->prepare(
            "UPDATE transactions

             SET
                type = ?,
                category = ?,
                amount = ?,
                transaction_date = ?,
                description = ?

             WHERE id = ?
             AND user_id = ?"
        );

        $updateQuery->execute([
            $type,
            $category,
            (float) $amount,
            $transactionDate,
            $description !== ""
                ? $description
                : null,
            $transactionId,
            $userId
        ]);

        header(
            "Location: index.php?updated=1"
        );

        exit;
    }
}


$pageTitle = "Edit Transaction";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">

    <div class="page-heading">

        <p class="page-kicker">
            FINANCE
        </p>

        <h1>
            Edit Transaction
        </h1>

        <p class="text-muted mb-0">
            Update the financial record below.
        </p>

    </div>


    <div class="row">

        <div class="col-xl-7 col-lg-9">

            <div class="content-card">

                <div class="content-card-header">

                    <div>

                        <h3>
                            Transaction Details
                        </h3>

                        <small class="text-muted">
                            Modify the transaction and save your changes.
                        </small>

                    </div>

                    <div class="stat-icon">
                        <i class="bi bi-pencil-square"></i>
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
                                for="type"
                                class="form-label"
                            >
                                Transaction Type *
                            </label>

                            <select
                                id="type"
                                name="type"
                                class="form-select"
                                required
                            >

                                <option
                                    value="income"
                                    <?php echo $type === "income" ? "selected" : ""; ?>
                                >
                                    Income
                                </option>

                                <option
                                    value="expense"
                                    <?php echo $type === "expense" ? "selected" : ""; ?>
                                >
                                    Expense
                                </option>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label
                                for="category"
                                class="form-label"
                            >
                                Category *
                            </label>

                            <select
                                id="category"
                                name="category"
                                class="form-select"
                                required
                            >

                                <?php

                                $allCategories =
                                    array_values(
                                        array_unique(
                                            array_merge(
                                                $incomeCategories,
                                                $expenseCategories
                                            )
                                        )
                                    );

                                ?>

                                <?php foreach ($allCategories as $option): ?>

                                    <option
                                        value="<?php echo htmlspecialchars($option); ?>"
                                        <?php
                                        echo
                                            $category === $option
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        <?php echo htmlspecialchars($option); ?>
                                    </option>

                                <?php endforeach; ?>

                                <?php if (!in_array($category, $allCategories, true)): ?>

                                    <option
                                        value="<?php echo htmlspecialchars($category); ?>"
                                        selected
                                    >
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>

                                <?php endif; ?>

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
                                    value="<?php echo htmlspecialchars((string) $amount); ?>"
                                    min="0.01"
                                    step="0.01"
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
                            ><?php echo htmlspecialchars($description); ?></textarea>

                        </div>

                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-check-lg me-1"></i>
                            Save Changes
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