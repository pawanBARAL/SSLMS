<?php

/*
|--------------------------------------------------------------------------
| Smart Study Planner
|--------------------------------------------------------------------------
|
| Analyses incomplete academic assignments and recommends which tasks the
| student should focus on first.
|
| The recommendation score considers:
|
| - Deadline proximity
| - Assignment priority
| - Estimated workload
| - Overdue status
| - Current assignment progress
|
|--------------------------------------------------------------------------
*/

require_once("../includes/auth_check.php");
require_once("../config/db.php");
require_once __DIR__ . "/../includes/planning_rules.php";

$userId =
    (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Retrieve Active Assignments
|--------------------------------------------------------------------------
*/

$assignmentQuery =
    $pdo->prepare(
        "SELECT
            id,
            title,
            module_name,
            description,
            due_date,
            priority,
            status,
            estimated_hours

         FROM assignments

         WHERE user_id = ?
         AND status != 'completed'

         ORDER BY due_date ASC"
    );

$assignmentQuery->execute([
    $userId
]);

$assignments =
    $assignmentQuery->fetchAll();


/*
|--------------------------------------------------------------------------
| Planner Data
|--------------------------------------------------------------------------
*/

$studyPlan = [];

$totalStudyHours = 0;

$highUrgencyCount = 0;


/* Shared recommendation logic used by both this page and the dashboard. */
$todayForPlanner = new DateTimeImmutable('today');
foreach ($assignments as $assignment) {
    $result = sslms_assignment_recommendation($assignment, $todayForPlanner);
    $studyPlan[] = $result;
    $totalStudyHours += max(0, (float) ($assignment['estimated_hours'] ?? 0));
    if ($result['score'] >= 60) {
        $highUrgencyCount++;
    }
}
sslms_sort_recommendations($studyPlan);

/*
|--------------------------------------------------------------------------
| Top Recommendation
|--------------------------------------------------------------------------
*/

$topRecommendation =
    $studyPlan[0]
    ?? null;


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Smart Study Planner";

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
                    SMART ACADEMIC PLANNING
                </p>

                <h1>
                    Smart Study Planner
                </h1>

                <p class="text-muted mb-0">
                    Prioritised recommendations based on
                    deadlines, workload and task importance.
                </p>

            </div>


            <a
                href="index.php"
                class="btn btn-outline-primary"
            >
                <i class="bi bi-arrow-left me-1"></i>
                Academics
            </a>

        </div>

    </div>


    <!-- ================================================================
         SUMMARY
         ================================================================ -->

    <div class="row g-3 mb-4">


        <div class="col-lg-4">

            <div class="stat-card h-100">

                <div class="stat-icon">

                    <i class="bi bi-journal-text"></i>

                </div>

                <div>

                    <span>
                        Active Tasks
                    </span>

                    <h3>
                        <?php
                        echo count(
                            $studyPlan
                        );
                        ?>
                    </h3>

                    <small class="text-muted">
                        Incomplete assignments
                    </small>

                </div>

            </div>

        </div>


        <div class="col-lg-4">

            <div class="stat-card h-100">

                <div class="stat-icon">

                    <i class="bi bi-clock-history"></i>

                </div>

                <div>

                    <span>
                        Study Workload
                    </span>

                    <h3>
                        <?php
                        echo number_format(
                            $totalStudyHours,
                            1
                        );
                        ?>h
                    </h3>

                    <small class="text-muted">
                        Estimated remaining workload
                    </small>

                </div>

            </div>

        </div>


        <div class="col-lg-4">

            <div class="stat-card h-100">

                <div class="stat-icon">

                    <i class="bi bi-exclamation-triangle"></i>

                </div>

                <div>

                    <span>
                        Urgent Tasks
                    </span>

                    <h3>
                        <?php
                        echo $highUrgencyCount;
                        ?>
                    </h3>

                    <small class="text-muted">
                        High or critical priority
                    </small>

                </div>

            </div>

        </div>


    </div>


    <?php if (!$topRecommendation): ?>


        <!-- ============================================================
             EMPTY PLAN
             ============================================================ -->

        <div class="content-card">

            <div class="empty-state">

                <i class="bi bi-check2-circle"></i>

                <h4>
                    No active assignments
                </h4>

                <p>
                    You currently have no incomplete assignments.
                    Add a new academic task to generate a study plan.
                </p>

                <a
                    href="add_task.php"
                    class="btn btn-primary mt-3"
                >
                    <i class="bi bi-plus-lg me-1"></i>
                    Add Assignment
                </a>

            </div>

        </div>


    <?php else: ?>


        <!-- ============================================================
             TOP RECOMMENDATION
             ============================================================ -->

        <div class="content-card mb-4">


            <div class="content-card-header">

                <div>

                    <p class="page-kicker mb-1">
                        RECOMMENDED FOCUS
                    </p>

                    <h3>
                        What should I study next?
                    </h3>

                </div>


                <div class="stat-icon">

                    <i class="bi bi-lightbulb"></i>

                </div>

            </div>


            <div class="recommended-task">


                <div
                    class="d-flex
                           justify-content-between
                           align-items-start
                           gap-3
                           flex-wrap"
                >

                    <div>

                        <span
                            class="badge
                                   <?php
                                   echo
                                       $topRecommendation[
                                           "urgency_class"
                                       ];
                                   ?>
                                   mb-2"
                        >

                            <?php
                            echo htmlspecialchars(
                                $topRecommendation[
                                    "urgency"
                                ]
                            );
                            ?>
                            Urgency

                        </span>


                        <h4>

                            <?php
                            echo htmlspecialchars(
                                $topRecommendation[
                                    "title"
                                ]
                            );
                            ?>

                        </h4>


                        <?php if (
                            !empty(
                                $topRecommendation[
                                    "module_name"
                                ]
                            )
                        ): ?>

                            <p
                                class="text-muted
                                       mb-2"
                            >

                                <i
                                    class="bi
                                           bi-book
                                           me-1"
                                ></i>

                                <?php
                                echo htmlspecialchars(
                                    $topRecommendation[
                                        "module_name"
                                    ]
                                );
                                ?>

                            </p>

                        <?php endif; ?>


                        <div
                            class="d-flex
                                   gap-2
                                   flex-wrap
                                   mt-3"
                        >

                            <?php foreach (
                                $topRecommendation[
                                    "reasons"
                                ]
                                as $reason
                            ): ?>

                                <span
                                    class="badge
                                           text-bg-light
                                           border
                                           text-dark"
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        $reason
                                    );
                                    ?>
                                </span>

                            <?php endforeach; ?>

                        </div>

                    </div>


                    <div class="text-end">

                        <small
                            class="text-muted
                                   d-block"
                        >
                            Recommendation Score
                        </small>

                        <strong
                            style="
                                font-size: 32px;
                            "
                        >
                            <?php
                            echo (int)
                                $topRecommendation[
                                    "score"
                                ];
                            ?>
                        </strong>

                    </div>

                </div>


                <hr>


                <div
                    class="row
                           g-3
                           align-items-center"
                >


                    <div class="col-md-4">

                        <small
                            class="text-muted
                                   d-block"
                        >
                            Deadline
                        </small>

                        <strong>

                            <?php
                            echo date(
                                "d M Y",
                                strtotime(
                                    $topRecommendation[
                                        "due_date"
                                    ]
                                )
                            );
                            ?>

                        </strong>

                    </div>


                    <div class="col-md-4">

                        <small
                            class="text-muted
                                   d-block"
                        >
                            Estimated Study
                        </small>

                        <strong>

                            <?php

                            if (
                                $topRecommendation[
                                    "estimated_hours"
                                ]
                                !== null
                            ) {

                                echo number_format(
                                    (float)
                                    $topRecommendation[
                                        "estimated_hours"
                                    ],
                                    1
                                )
                                .
                                " hours";

                            } else {

                                echo "Not specified";
                            }

                            ?>

                        </strong>

                    </div>


                    <div
                        class="col-md-4
                               text-md-end"
                    >

                        <a
                            href="edit_task.php?id=<?php
                            echo (int)
                                $topRecommendation[
                                    "id"
                                ];
                            ?>"
                            class="btn btn-primary"
                        >

                            <i
                                class="bi
                                       bi-play-circle
                                       me-1"
                            ></i>

                            Work on Task

                        </a>

                    </div>


                </div>


            </div>


        </div>


        <!-- ============================================================
             PRIORITISED STUDY PLAN
             ============================================================ -->

        <div class="content-card">


            <div class="content-card-header">

                <div>

                    <h3>
                        Prioritised Study Plan
                    </h3>

                    <small class="text-muted">
                        Tasks are automatically ranked
                        by recommendation score.
                    </small>

                </div>

                <span class="badge bg-primary">

                    <?php
                    echo count(
                        $studyPlan
                    );
                    ?>
                    Tasks

                </span>

            </div>


            <div class="table-responsive">

                <table class="table align-middle">

                    <thead>

                        <tr>

                            <th>
                                Rank
                            </th>

                            <th>
                                Assignment
                            </th>

                            <th>
                                Deadline
                            </th>

                            <th>
                                Workload
                            </th>

                            <th>
                                Urgency
                            </th>

                            <th>
                                Score
                            </th>

                            <th class="text-end">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $studyPlan as
                        $index => $task
                    ): ?>


                        <tr>


                            <td>

                                <strong>
                                    #<?php
                                    echo $index + 1;
                                    ?>
                                </strong>

                            </td>


                            <td>

                                <strong
                                    class="d-block"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $task["title"]
                                    );
                                    ?>

                                </strong>


                                <?php if (
                                    !empty(
                                        $task[
                                            "module_name"
                                        ]
                                    )
                                ): ?>

                                    <small
                                        class="text-muted"
                                    >
                                        <?php
                                        echo htmlspecialchars(
                                            $task[
                                                "module_name"
                                            ]
                                        );
                                        ?>
                                    </small>

                                <?php endif; ?>


                                <div
                                    class="d-flex
                                           flex-wrap
                                           gap-1
                                           mt-2"
                                >

                                    <?php foreach (
                                        array_slice(
                                            $task["reasons"],
                                            0,
                                            3
                                        )
                                        as $reason
                                    ): ?>

                                        <span
                                            class="badge
                                                   text-bg-light
                                                   border
                                                   text-dark"
                                        >
                                            <?php
                                            echo htmlspecialchars(
                                                $reason
                                            );
                                            ?>
                                        </span>

                                    <?php endforeach; ?>

                                </div>

                            </td>


                            <td>

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $task[
                                            "due_date"
                                        ]
                                    )
                                );
                                ?>


                                <?php if (
                                    $task[
                                        "days_remaining"
                                    ]
                                    < 0
                                ): ?>

                                    <small
                                        class="d-block
                                               text-danger"
                                    >
                                        Overdue
                                    </small>

                                <?php elseif (
                                    $task[
                                        "days_remaining"
                                    ]
                                    === 0
                                ): ?>

                                    <small
                                        class="d-block
                                               text-danger"
                                    >
                                        Today
                                    </small>

                                <?php else: ?>

                                    <small
                                        class="d-block
                                               text-muted"
                                    >

                                        <?php
                                        echo
                                            $task[
                                                "days_remaining"
                                            ];
                                        ?>
                                        days left

                                    </small>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php

                                if (
                                    $task[
                                        "estimated_hours"
                                    ]
                                    !== null
                                ) {

                                    echo number_format(
                                        (float)
                                        $task[
                                            "estimated_hours"
                                        ],
                                        1
                                    )
                                    .
                                    " hrs";

                                } else {

                                    echo
                                        '<span class="text-muted">—</span>';
                                }

                                ?>

                            </td>


                            <td>

                                <span
                                    class="badge
                                           <?php
                                           echo
                                               $task[
                                                   "urgency_class"
                                               ];
                                           ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $task[
                                            "urgency"
                                        ]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <strong>
                                    <?php
                                    echo (int)
                                        $task["score"];
                                    ?>
                                </strong>

                            </td>


                            <td class="text-end">

                                <a
                                    href="edit_task.php?id=<?php
                                    echo (int)
                                        $task["id"];
                                    ?>"
                                    class="btn
                                           btn-sm
                                           btn-outline-primary"
                                >
                                    <i class="bi bi-pencil"></i>
                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        </div>


    <?php endif; ?>


</main>


<?php
require_once("../includes/footer.php");
?>