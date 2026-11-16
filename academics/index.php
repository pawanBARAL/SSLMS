<?php

/*
|--------------------------------------------------------------------------
| Academics Dashboard
|--------------------------------------------------------------------------
|
| Displays academic assignments belonging to the logged-in student.
| Includes statistics, filters, deadlines and assignment management.
|
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");

$userId = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$statusFilter = trim($_GET["status"] ?? "");
$priorityFilter = trim($_GET["priority"] ?? "");
$search = trim($_GET["search"] ?? "");

$allowedStatuses = [
    "pending",
    "in_progress",
    "completed"
];

$allowedPriorities = [
    "low",
    "medium",
    "high"
];

if (
    $statusFilter !== ""
    &&
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {
    $statusFilter = "";
}

if (
    $priorityFilter !== ""
    &&
    !in_array(
        $priorityFilter,
        $allowedPriorities,
        true
    )
) {
    $priorityFilter = "";
}


/*
|--------------------------------------------------------------------------
| Academic Statistics
|--------------------------------------------------------------------------
*/

$statsQuery = $pdo->prepare(
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
                AND due_date < CURDATE()
                THEN 1
                ELSE 0
            END
        ) AS overdue_tasks,

        SUM(
            CASE
                WHEN status != 'completed'
                AND due_date BETWEEN CURDATE()
                AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                THEN 1
                ELSE 0
            END
        ) AS upcoming_tasks,

        COALESCE(
            SUM(
                CASE
                    WHEN status != 'completed'
                    THEN estimated_hours
                    ELSE 0
                END
            ),
            0
        ) AS remaining_hours

     FROM assignments

     WHERE user_id = ?"
);

$statsQuery->execute([
    $userId
]);

$stats = $statsQuery->fetch();

$totalTasks =
    (int) ($stats["total_tasks"] ?? 0);

$completedTasks =
    (int) ($stats["completed_tasks"] ?? 0);

$overdueTasks =
    (int) ($stats["overdue_tasks"] ?? 0);

$upcomingTasks =
    (int) ($stats["upcoming_tasks"] ?? 0);

$remainingHours =
    (float) ($stats["remaining_hours"] ?? 0);


/*
|--------------------------------------------------------------------------
| Completion Percentage
|--------------------------------------------------------------------------
*/

$completionPercentage = 0;

if ($totalTasks > 0) {

    $completionPercentage =
        (int) round(
            ($completedTasks / $totalTasks)
            * 100
        );
}


/*
|--------------------------------------------------------------------------
| Build Assignment Query
|--------------------------------------------------------------------------
*/

$sql =
    "SELECT *
     FROM assignments
     WHERE user_id = ?";

$params = [
    $userId
];


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($statusFilter !== "") {

    $sql .=
        " AND status = ?";

    $params[] =
        $statusFilter;
}


/*
|--------------------------------------------------------------------------
| Priority Filter
|--------------------------------------------------------------------------
*/

if ($priorityFilter !== "") {

    $sql .=
        " AND priority = ?";

    $params[] =
        $priorityFilter;
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .=
        " AND (
            title LIKE ?
            OR module_name LIKE ?
            OR description LIKE ?
        )";

    $searchValue =
        "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
|
| Incomplete tasks appear before completed tasks.
| Within those groups, earlier deadlines appear first.
|
|--------------------------------------------------------------------------
*/

$sql .=
    " ORDER BY

        CASE
            WHEN status = 'completed'
            THEN 1
            ELSE 0
        END,

        due_date ASC,

        CASE priority
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            WHEN 'low' THEN 3
            ELSE 4
        END,

        created_at DESC";


$assignmentQuery =
    $pdo->prepare($sql);

$assignmentQuery->execute(
    $params
);

$assignments =
    $assignmentQuery->fetchAll();


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$successMessage = "";

if (
    isset($_GET["added"])
    &&
    $_GET["added"] === "1"
) {
    $successMessage =
        "Assignment added successfully.";
}

if (
    isset($_GET["updated"])
    &&
    $_GET["updated"] === "1"
) {
    $successMessage =
        "Assignment updated successfully.";
}

if (
    isset($_GET["deleted"])
    &&
    $_GET["deleted"] === "1"
) {
    $successMessage =
        "Assignment deleted successfully.";
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Academics";

require_once("../includes/header.php");
require_once("../includes/sidebar.php");

?>


<main class="main-content">


    <!-- ================================================================
         PAGE HEADING
         ================================================================ -->

    <div class="page-heading">

        <div
            class="d-flex
                   justify-content-between
                   align-items-start
                   flex-wrap
                   gap-3"
        >

            <div>

                <p class="page-kicker">
                    ACADEMIC MANAGEMENT
                </p>

                <h1>
                    Academics
                </h1>

                <p class="text-muted mb-0">
                    Manage assignments, deadlines,
                    study workload and academic progress.
                </p>

            </div>


            <div class="d-flex gap-2 flex-wrap">

                <a
                    href="study_planner.php"
                    class="btn btn-outline-primary"
                >
                    <i class="bi bi-lightbulb me-1"></i>
                    Study Planner
                </a>

                <a
                    href="add_task.php"
                    class="btn btn-primary"
                >
                    <i class="bi bi-plus-lg me-1"></i>
                    Add Assignment
                </a>

            </div>

        </div>

    </div>


    <!-- ================================================================
         SUCCESS MESSAGE
         ================================================================ -->

    <?php if ($successMessage !== ""): ?>

        <div
            class="alert alert-success"
            role="alert"
        >

            <i class="bi bi-check-circle me-2"></i>

            <?php
            echo htmlspecialchars(
                $successMessage
            );
            ?>

        </div>

    <?php endif; ?>


    <!-- ================================================================
         STATISTICS
         ================================================================ -->

    <div class="row g-3 mb-4">


        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-journal-text"></i>
                </div>

                <div>

                    <span>
                        Total Assignments
                    </span>

                    <h3>
                        <?php echo $totalTasks; ?>
                    </h3>

                    <small class="text-muted">
                        All academic tasks
                    </small>

                </div>

            </div>

        </div>


        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-check2-circle"></i>
                </div>

                <div>

                    <span>
                        Completed
                    </span>

                    <h3>
                        <?php echo $completedTasks; ?>
                    </h3>

                    <small class="text-muted">
                        <?php
                        echo $completionPercentage;
                        ?>% completion rate
                    </small>

                </div>

            </div>

        </div>


        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>

                <div>

                    <span>
                        Overdue
                    </span>

                    <h3>
                        <?php echo $overdueTasks; ?>
                    </h3>

                    <small class="text-muted">
                        Require attention
                    </small>

                </div>

            </div>

        </div>


        <div class="col-xl-3 col-md-6">

            <div class="stat-card h-100">

                <div class="stat-icon">
                    <i class="bi bi-clock"></i>
                </div>

                <div>

                    <span>
                        Study Workload
                    </span>

                    <h3>
                        <?php
                        echo number_format(
                            $remainingHours,
                            1
                        );
                        ?>h
                    </h3>

                    <small class="text-muted">
                        Estimated remaining
                    </small>

                </div>

            </div>

        </div>


    </div>


    <!-- ================================================================
         PROGRESS
         ================================================================ -->

    <div class="content-card mb-4">

        <div class="content-card-header">

            <div>

                <h3>
                    Academic Progress
                </h3>

                <small class="text-muted">
                    Overall assignment completion
                </small>

            </div>

            <strong>
                <?php
                echo $completionPercentage;
                ?>%
            </strong>

        </div>


        <div
            class="progress"
            style="height: 10px;"
        >

            <div
                class="progress-bar"
                role="progressbar"
                style="width:
                    <?php
                    echo $completionPercentage;
                    ?>%;"
                aria-valuenow="<?php
                    echo $completionPercentage;
                ?>"
                aria-valuemin="0"
                aria-valuemax="100"
            ></div>

        </div>


        <div
            class="d-flex
                   justify-content-between
                   flex-wrap
                   gap-2
                   mt-3"
        >

            <small class="text-muted">

                <?php echo $completedTasks; ?>
                of
                <?php echo $totalTasks; ?>
                assignments completed

            </small>

            <small class="text-muted">

                <?php echo $upcomingTasks; ?>
                due within the next 7 days

            </small>

        </div>

    </div>


    <!-- ================================================================
         FILTERS
         ================================================================ -->

    <div class="content-card mb-4">

        <form
            method="GET"
            action=""
        >

            <div class="row g-3 align-items-end">


                <div class="col-lg-5">

                    <label
                        for="search"
                        class="form-label"
                    >
                        Search
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="text"
                            id="search"
                            name="search"
                            class="form-control"
                            value="<?php
                            echo htmlspecialchars(
                                $search
                            );
                            ?>"
                            placeholder="Search title, module or description"
                        >

                    </div>

                </div>


                <div class="col-lg-2 col-md-4">

                    <label
                        for="status"
                        class="form-label"
                    >
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        class="form-select"
                    >

                        <option value="">
                            All Statuses
                        </option>

                        <option
                            value="pending"
                            <?php
                            echo
                                $statusFilter === "pending"
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
                                $statusFilter === "in_progress"
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
                                $statusFilter === "completed"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Completed
                        </option>

                    </select>

                </div>


                <div class="col-lg-2 col-md-4">

                    <label
                        for="priority"
                        class="form-label"
                    >
                        Priority
                    </label>

                    <select
                        id="priority"
                        name="priority"
                        class="form-select"
                    >

                        <option value="">
                            All Priorities
                        </option>

                        <option
                            value="high"
                            <?php
                            echo
                                $priorityFilter === "high"
                                ? "selected"
                                : "";
                            ?>
                        >
                            High
                        </option>

                        <option
                            value="medium"
                            <?php
                            echo
                                $priorityFilter === "medium"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Medium
                        </option>

                        <option
                            value="low"
                            <?php
                            echo
                                $priorityFilter === "low"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Low
                        </option>

                    </select>

                </div>


                <div class="col-lg-3 col-md-4">

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
                        >
                            Reset
                        </a>

                    </div>

                </div>


            </div>

        </form>

    </div>


    <!-- ================================================================
         ASSIGNMENTS
         ================================================================ -->

    <div class="content-card">

        <div class="content-card-header">

            <div>

                <h3>
                    Assignment List
                </h3>

                <small class="text-muted">
                    <?php
                    echo count($assignments);
                    ?>
                    assignment(s) shown
                </small>

            </div>

            <a
                href="add_task.php"
                class="btn btn-sm btn-primary"
            >
                <i class="bi bi-plus-lg me-1"></i>
                New
            </a>

        </div>


        <?php if (empty($assignments)): ?>


            <div class="empty-state">

                <i class="bi bi-journal-plus"></i>

                <h4>
                    No assignments found
                </h4>

                <p>
                    Add your first assignment or change
                    the filters to see academic tasks.
                </p>

                <a
                    href="add_task.php"
                    class="btn btn-primary mt-3"
                >
                    Add Assignment
                </a>

            </div>


        <?php else: ?>


            <div class="table-responsive">

                <table class="table align-middle">

                    <thead>

                        <tr>

                            <th>
                                Assignment
                            </th>

                            <th>
                                Deadline
                            </th>

                            <th>
                                Priority
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Study Time
                            </th>

                            <th class="text-end">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $assignments as $assignment
                    ): ?>


                        <?php

                        $isOverdue =
                            $assignment["status"]
                            !== "completed"
                            &&
                            $assignment["due_date"]
                            <
                            date("Y-m-d");


                        $priorityBadge = match (
                            $assignment["priority"]
                        ) {
                            "high" =>
                                "bg-danger",

                            "medium" =>
                                "bg-warning",

                            default =>
                                "bg-success"
                        };


                        $statusBadge = match (
                            $assignment["status"]
                        ) {
                            "completed" =>
                                "bg-success",

                            "in_progress" =>
                                "bg-primary",

                            default =>
                                "bg-secondary"
                        };


                        $statusLabel = match (
                            $assignment["status"]
                        ) {
                            "in_progress" =>
                                "In Progress",

                            "completed" =>
                                "Completed",

                            default =>
                                "Pending"
                        };

                        ?>


                        <tr>


                            <td>

                                <div>

                                    <strong
                                        class="d-block"
                                    >
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

                                        <small class="text-muted">

                                            <i
                                                class="bi
                                                       bi-book
                                                       me-1"
                                            ></i>

                                            <?php
                                            echo htmlspecialchars(
                                                $assignment["module_name"]
                                            );
                                            ?>

                                        </small>

                                    <?php endif; ?>


                                    <?php if ($isOverdue): ?>

                                        <div class="mt-1">

                                            <span
                                                class="badge bg-danger"
                                            >
                                                Overdue
                                            </span>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </td>


                            <td>

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $assignment["due_date"]
                                    )
                                );
                                ?>

                            </td>


                            <td>

                                <span
                                    class="badge
                                           <?php
                                           echo $priorityBadge;
                                           ?>"
                                >

                                    <?php
                                    echo ucfirst(
                                        $assignment["priority"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="badge
                                           <?php
                                           echo $statusBadge;
                                           ?>"
                                >

                                    <?php
                                    echo $statusLabel;
                                    ?>

                                </span>

                            </td>


                            <td>

                                <?php if (
                                    $assignment["estimated_hours"]
                                    !== null
                                ): ?>

                                    <?php
                                    echo number_format(
                                        (float)
                                        $assignment["estimated_hours"],
                                        1
                                    );
                                    ?>
                                    hrs

                                <?php else: ?>

                                    <span class="text-muted">
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td class="text-end">

                                <div
                                    class="d-flex
                                           justify-content-end
                                           gap-2"
                                >

                                    <a
                                        href="edit_task.php?id=<?php
                                        echo (int)
                                            $assignment["id"];
                                        ?>"
                                        class="btn
                                               btn-sm
                                               btn-outline-primary"
                                        title="Edit assignment"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </a>


                                    <a
                                        href="delete_task.php?id=<?php
                                        echo (int)
                                            $assignment["id"];
                                        ?>"
                                        class="btn
                                               btn-sm
                                               btn-outline-danger"
                                        title="Delete assignment"
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


</main>


<?php
require_once("../includes/footer.php");
?>