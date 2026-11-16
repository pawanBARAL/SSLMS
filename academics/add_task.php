<?php

/*
|--------------------------------------------------------------------------
| Add Academic Assignment
|--------------------------------------------------------------------------
|
| Allows the logged-in student to create a new academic assignment.
|
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId =
    (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Default Values
|--------------------------------------------------------------------------
*/

$title = "";
$moduleName = "";
$description = "";
$dueDate = "";
$priority = "medium";
$status = "pending";
$estimatedHours = "";

$error = "";


/*
|--------------------------------------------------------------------------
| Process Form
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title =
        trim(
            $_POST["title"]
            ?? ""
        );

    $moduleName =
        trim(
            $_POST["module_name"]
            ?? ""
        );

    $description =
        trim(
            $_POST["description"]
            ?? ""
        );

    $dueDate =
        trim(
            $_POST["due_date"]
            ?? ""
        );

    $priority =
        $_POST["priority"]
        ?? "medium";

    $status =
        $_POST["status"]
        ?? "pending";

    $estimatedHours =
        trim(
            $_POST["estimated_hours"]
            ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    $allowedPriorities = [
        "low",
        "medium",
        "high"
    ];

    $allowedStatuses = [
        "pending",
        "in_progress",
        "completed"
    ];


    if ($title === "") {

        $error =
            "Please enter an assignment title.";

    } elseif (strlen($title) > 200) {

        $error =
            "The assignment title is too long.";

    } elseif ($dueDate === "") {

        $error =
            "Please select a deadline.";

    } elseif (
        !in_array(
            $priority,
            $allowedPriorities,
            true
        )
    ) {

        $error =
            "Please select a valid priority.";

    } elseif (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {

        $error =
            "Please select a valid status.";

    } elseif (
        $estimatedHours !== ""
        &&
        (
            !is_numeric($estimatedHours)
            ||
            (float) $estimatedHours < 0
            ||
            (float) $estimatedHours > 9999.99
        )
    ) {

        $error =
            "Please enter a valid estimated study time.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Insert Assignment
        |--------------------------------------------------------------------------
        */

        $insertQuery =
            $pdo->prepare(
                "INSERT INTO assignments
                (
                    user_id,
                    title,
                    module_name,
                    description,
                    due_date,
                    priority,
                    status,
                    estimated_hours
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )"
            );


        $insertQuery->execute([
            $userId,
            $title,
            $moduleName !== ""
                ? $moduleName
                : null,
            $description !== ""
                ? $description
                : null,
            $dueDate,
            $priority,
            $status,
            $estimatedHours !== ""
                ? (float) $estimatedHours
                : null
        ]);


        header(
            "Location: index.php?added=1"
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Add Assignment";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">


    <div class="page-heading">

        <p class="page-kicker">
            ACADEMICS
        </p>

        <h1>
            Add Assignment
        </h1>

        <p class="text-muted mb-0">
            Add a new academic task,
            coursework item or deadline.
        </p>

    </div>


    <div class="row">

        <div class="col-xl-8 col-lg-10">


            <div class="content-card">


                <div class="content-card-header">

                    <div>

                        <h3>
                            Assignment Details
                        </h3>

                        <small class="text-muted">
                            Enter the academic task information below.
                        </small>

                    </div>

                    <div class="stat-icon">

                        <i class="bi bi-journal-plus"></i>

                    </div>

                </div>


                <?php if ($error !== ""): ?>

                    <div
                        class="alert alert-danger"
                        role="alert"
                    >

                        <i
                            class="bi
                                   bi-exclamation-circle
                                   me-2"
                        ></i>

                        <?php
                        echo htmlspecialchars(
                            $error
                        );
                        ?>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    action=""
                >
                    <?= csrf_field() ?>


                    <div class="row g-3">


                        <!-- Assignment title -->

                        <div class="col-md-7">

                            <label
                                for="title"
                                class="form-label"
                            >
                                Assignment Title *
                            </label>

                            <input
                                type="text"
                                id="title"
                                name="title"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $title
                                );
                                ?>"
                                maxlength="200"
                                placeholder="e.g. Database Systems Report"
                                required
                            >

                        </div>


                        <!-- Module -->

                        <div class="col-md-5">

                            <label
                                for="module_name"
                                class="form-label"
                            >
                                Module
                            </label>

                            <input
                                type="text"
                                id="module_name"
                                name="module_name"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $moduleName
                                );
                                ?>"
                                maxlength="200"
                                placeholder="e.g. Database Systems"
                            >

                        </div>


                        <!-- Deadline -->

                        <div class="col-md-4">

                            <label
                                for="due_date"
                                class="form-label"
                            >
                                Deadline *
                            </label>

                            <input
                                type="date"
                                id="due_date"
                                name="due_date"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars(
                                    $dueDate
                                );
                                ?>"
                                required
                            >

                        </div>


                        <!-- Priority -->

                        <div class="col-md-4">

                            <label
                                for="priority"
                                class="form-label"
                            >
                                Priority *
                            </label>

                            <select
                                id="priority"
                                name="priority"
                                class="form-select"
                                required
                            >

                                <option
                                    value="low"
                                    <?php
                                    echo
                                        $priority === "low"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Low
                                </option>

                                <option
                                    value="medium"
                                    <?php
                                    echo
                                        $priority === "medium"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Medium
                                </option>

                                <option
                                    value="high"
                                    <?php
                                    echo
                                        $priority === "high"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    High
                                </option>

                            </select>

                        </div>


                        <!-- Status -->

                        <div class="col-md-4">

                            <label
                                for="status"
                                class="form-label"
                            >
                                Status *
                            </label>

                            <select
                                id="status"
                                name="status"
                                class="form-select"
                                required
                            >

                                <option
                                    value="pending"
                                    <?php
                                    echo
                                        $status === "pending"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Pending
                                </option>

                                <option
                                    value="in_progress"
                                    <?php
                                    echo
                                        $status === "in_progress"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    In Progress
                                </option>

                                <option
                                    value="completed"
                                    <?php
                                    echo
                                        $status === "completed"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Completed
                                </option>

                            </select>

                        </div>


                        <!-- Estimated hours -->

                        <div class="col-md-5">

                            <label
                                for="estimated_hours"
                                class="form-label"
                            >
                                Estimated Study Hours
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">

                                    <i class="bi bi-clock"></i>

                                </span>

                                <input
                                    type="number"
                                    id="estimated_hours"
                                    name="estimated_hours"
                                    class="form-control"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $estimatedHours
                                    );
                                    ?>"
                                    min="0"
                                    max="9999.99"
                                    step="0.5"
                                    placeholder="e.g. 5"
                                >

                            </div>

                        </div>


                        <!-- Description -->

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
                                rows="5"
                                placeholder="Add notes, requirements or other assignment information..."
                            ><?php
                            echo htmlspecialchars(
                                $description
                            );
                            ?></textarea>

                        </div>


                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i
                                class="bi
                                       bi-check-lg
                                       me-1"
                            ></i>

                            Save Assignment

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