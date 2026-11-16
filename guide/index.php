<?php
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'SSLMS User Guide';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <div class="container-fluid">
    <div class="page-heading d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <p class="page-kicker">STUDENT HELP</p>
        <h1>Using SSLMS</h1>
        <p class="text-muted mb-0">A quick guide to keeping your studies, work and finances together.</p>
      </div>
      <a class="btn btn-outline-primary" href="../index.php"><i class="bi bi-house me-2"></i>Back to dashboard</a>
    </div>

    <div class="row g-4 mt-2">
      <div class="col-lg-6">
        <section class="content-card p-4 h-100">
          <h3><i class="bi bi-journal-check me-2"></i>1. Academic tasks</h3>
          <p>Add assignments with a deadline, priority, progress status and an estimated number of hours. You can edit them as your plans change.</p>
          <a class="btn btn-outline-primary" href="../academics/index.php">Open Academics</a>
        </section>
      </div>
      <div class="col-lg-6">
        <section class="content-card p-4 h-100">
          <h3><i class="bi bi-lightbulb me-2"></i>2. Smart Study Planner</h3>
          <p>The planner ranks unfinished assignments using four rules: deadline proximity, priority, estimated effort and progress. The highest score appears first, with reasons you can check.</p>
          <p class="small text-muted">Scores are planning prompts, not marks or probabilities. Correct an assignment if its deadline or estimate is wrong.</p>
          <a class="btn btn-outline-primary" href="../academics/study_planner.php">View study priorities</a>
        </section>
      </div>
      <div class="col-lg-6">
        <section class="content-card p-4 h-100">
          <h3><i class="bi bi-wallet2 me-2"></i>3. Finance and budgets</h3>
          <p>Record income and expenses, then set a monthly budget. The Budget Advisor compares your recorded spending with that amount; Spending Prediction projects current-month costs from an average daily rate.</p>
          <p class="small text-muted">Forecasts use recorded expenses through today, not future-dated entries. A one-off bill can distort the average. SSLMS does not connect to a bank.</p>
          <a class="btn btn-outline-primary me-2" href="../finance/index.php">View finance</a>
          <a class="btn btn-outline-primary" href="../finance/spending_prediction.php">Spending projection</a>
        </section>
      </div>
      <div class="col-lg-6">
        <section class="content-card p-4 h-100">
          <h3><i class="bi bi-briefcase me-2"></i>4. Work and study balance</h3>
          <p>Enter your work shifts with start and end times. The advisor compares scheduled working hours with the estimated work remaining on unfinished assignments.</p>
          <p class="small text-muted">The pressure score is a rule-based alert, not a medical measure. A shared date may indicate a planning concern, not an actual time clash. The 20-hour target is not a legal limit.</p>
          <a class="btn btn-outline-primary" href="../work/balance_advisor.php">Balance advisor</a>
        </section>
      </div>
      <div class="col-lg-6">
        <section class="content-card p-4 h-100">
          <h3><i class="bi bi-calendar3 me-2"></i>5. Calendar and reminders</h3>
          <p>See assignments, recorded shifts, reminders and personal events in a combined calendar. You can add or change reminders and events from their own pages.</p>
          <a class="btn btn-outline-primary me-2" href="../calendar/index.php">Open calendar</a>
          <a class="btn btn-outline-primary" href="../reminders/index.php">Reminders</a>
        </section>
      </div>
      <div class="col-lg-6">
        <section class="content-card p-4 h-100">
          <h3><i class="bi bi-info-circle me-2"></i>6. What this prototype does not do</h3>
          <p>SSLMS provides manual planning and explainable calculations. It does not automatically import schedule images, connect to university or bank systems, or learn from machine-learning models.</p>
          <p class="small text-muted">Image-based schedule import remains outside the verified project scope.</p>
          <a class="btn btn-outline-primary" href="../schedule_import/index.php">Schedule import status</a>
        </section>
      </div>
    </div>
    <div class="alert alert-light border mt-4" role="note">
      <strong>Privacy reminder:</strong> Only record details you are comfortable storing in this local project. Use fictional data for demonstrations, sign out after use, and back up your database before making changes.
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
