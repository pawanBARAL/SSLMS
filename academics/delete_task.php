<?php

/*
|--------------------------------------------------------------------------
| Delete Academic Assignment
|--------------------------------------------------------------------------
|
| Displays a confirmation screen before permanently deleting an assignment.
|
| Security:
| - Login required
| - Assignment ID validation
| - Ownership check
| - POST required for deletion
|
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId =
    (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Assignment ID
|--------------------------------------------------------------------------
*/

$assignmentId =
    filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );


if (!$assignmentId) {

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Find Assignment
|--------------------------------------------------------------------------
*/

$findQuery =
    $pdo->prepare(
        "SELECT
            id,
            title,
            module_name,
            due_date,
            priority,
            status

         FROM assignments

         WHERE id = ?
         AND user_id = ?

         LIMIT 1"
    );

$findQuery->execute([
    $assignmentId,
    $userId
]);

$assignment =
    $findQuery->fetch();


if (!$assignment) {

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Delete Assignment
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |--------------------------------------------------------------------------
    | Verify Posted ID
    |--------------------------------------------------------------------------
    */

    $postedId =
        filter_input(
            INPUT_POST,
            "assignment_id",
            FILTER_VALIDATE_INT
        );


    if (
        !$postedId
        ||
        $postedId !== $assignmentId
    ) {

        header(
            "Location: index.php"
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Only Student's Own Assignment
    |--------------------------------------------------------------------------
    */

    $deleteQuery =
        $pdo->prepare(
            "DELETE FROM assignments
             WHERE id = ?
             AND user_id = ?"
        );


    $deleteQuery->execute([
        $assignmentId,
        $userId
    ]);


    header(
        "Location: index.php?deleted=1"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Delete Assignment";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">


    <div class="page-heading">

        <p class="page-kicker">
            ACADEMICS
        </p>

        <h1>
            Delete Assignment
        </h1>

        <p class="text-muted mb-0">
            Confirm permanent removal of this academic task.
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
                        Delete this assignment?
                    </h3>

                    <p class="text-muted">
                        This action permanently removes the assignment
                        from your academic records.
                    </p>


                    <div
                        class="text-start
                               border
                               rounded-3
                               p-3
                               my-4"
                    >

                        <strong class="d-block mb-1">

                            <?php
                            echo htmlspecialchars(
                                $assignment["title"]
                            );
                            ?>

                        </strong>


                        <?php if (
                            !empty(
                                $assignment["module_name"]
                            )
                        ): ?>

                            <small
                                class="text-muted
                                       d-block
                                       mb-1"
                            >

                                Module:
                                <?php
                                echo htmlspecialchars(
                                    $assignment["module_name"]
                                );
                                ?>

                            </small>

                        <?php endif; ?>


                        <small class="text-muted">

                            Deadline:
                            <?php
                            echo date(
                                "d M Y",
                                strtotime(
                                    $assignment["due_date"]
                                )
                            );
                            ?>

                        </small>

                    </div>


                    <form
                        method="POST"
                        action=""
                    >
                    <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="assignment_id"
                            value="<?php
                            echo (int)
                                $assignment["id"];
                            ?>"
                        >


                        <div
                            class="d-flex
                                   justify-content-center
                                   gap-2
                                   flex-wrap"
                        >

                            <a
                                href="index.php"
                                class="btn
                                       btn-outline-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-danger"
                            >

                                <i
                                    class="bi
                                           bi-trash
                                           me-1"
                                ></i>

                                Delete Assignment

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