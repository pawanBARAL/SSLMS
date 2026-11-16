<?php

/*
|--------------------------------------------------------------------------
| Delete Transaction
|--------------------------------------------------------------------------
| Prevents a transaction from being deleted directly through a GET request.
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
    "SELECT
        id,
        type,
        category,
        amount,
        transaction_date,
        description

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
| Delete
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedId =
        filter_input(
            INPUT_POST,
            "transaction_id",
            FILTER_VALIDATE_INT
        );

    if (
        !$postedId
        ||
        $postedId !== $transactionId
    ) {

        header("Location: index.php");
        exit;
    }


    $deleteQuery = $pdo->prepare(
        "DELETE FROM transactions
         WHERE id = ?
         AND user_id = ?"
    );

    $deleteQuery->execute([
        $transactionId,
        $userId
    ]);


    header(
        "Location: index.php?deleted=1"
    );

    exit;
}


$pageTitle = "Delete Transaction";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">

    <div class="page-heading">

        <p class="page-kicker">
            FINANCE
        </p>

        <h1>
            Delete Transaction
        </h1>

        <p class="text-muted mb-0">
            Confirm removal of this financial record.
        </p>

    </div>


    <div class="row justify-content-center">

        <div class="col-xl-6 col-lg-7">

            <div class="content-card">

                <div class="text-center py-3">

                    <div
                        class="d-inline-flex
                               align-items-center
                               justify-content-center
                               rounded-circle
                               bg-danger-subtle
                               text-danger
                               mb-4"
                        style="
                            width: 72px;
                            height: 72px;
                            font-size: 28px;
                        "
                    >
                        <i class="bi bi-trash3"></i>
                    </div>


                    <h3>
                        Delete this transaction?
                    </h3>

                    <p class="text-muted">
                        This transaction will be permanently removed
                        from your finance records.
                    </p>


                    <div class="text-start border rounded-3 p-3 my-4">

                        <div class="d-flex justify-content-between gap-3 mb-2">

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $transaction["category"]
                                );
                                ?>
                            </strong>

                            <span class="badge <?php
                            echo
                                $transaction["type"] === "income"
                                ? "bg-success"
                                : "bg-danger";
                            ?>">
                                <?php
                                echo ucfirst(
                                    $transaction["type"]
                                );
                                ?>
                            </span>

                        </div>


                        <h4 class="mb-2">

                            £<?php
                            echo number_format(
                                (float) $transaction["amount"],
                                2
                            );
                            ?>

                        </h4>


                        <small class="text-muted">

                            <?php
                            echo date(
                                "d M Y",
                                strtotime(
                                    $transaction["transaction_date"]
                                )
                            );
                            ?>

                        </small>


                        <?php if (!empty($transaction["description"])): ?>

                            <p class="text-muted mt-3 mb-0">

                                <?php
                                echo htmlspecialchars(
                                    $transaction["description"]
                                );
                                ?>

                            </p>

                        <?php endif; ?>

                    </div>


                    <form method="POST">
                    <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="transaction_id"
                            value="<?php echo (int) $transaction["id"]; ?>"
                        >

                        <div class="d-flex justify-content-center gap-2 flex-wrap">

                            <a
                                href="index.php"
                                class="btn btn-outline-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-danger"
                            >
                                <i class="bi bi-trash me-1"></i>
                                Delete Transaction
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</main>


<?php
require_once("../includes/footer.php");
?>