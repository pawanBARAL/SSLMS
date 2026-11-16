<?php
/** Shared monthly finance helpers used by the Budget Advisor and Spending Prediction. */
declare(strict_types=1);

/**
 * End date for recorded expenditure in a selected month.
 * For the current month, future-dated entries are not considered spent yet.
 */
function sslms_spending_recorded_through(string $selectedMonth, ?DateTimeImmutable $today = null): string
{
    $today = $today ?? new DateTimeImmutable('today');
    $monthStart = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedMonth . '-01');
    if ($monthStart === false || $monthStart->format('Y-m') !== $selectedMonth) {
        throw new InvalidArgumentException('Expected a valid YYYY-MM month.');
    }
    $monthEnd = $monthStart->format('Y-m-t');
    return $selectedMonth === $today->format('Y-m')
        ? min($monthEnd, $today->format('Y-m-d'))
        : $monthEnd;
}

/** Explainable daily-average projection; this is not machine-learning forecasting. */
function sslms_linear_spending_projection(float $expenses, int $daysPassed, int $daysInMonth): array
{
    if ($daysPassed <= 0 || $daysInMonth <= 0 || $expenses <= 0) {
        return ['average_daily' => 0.0, 'remaining' => 0.0, 'month_end' => 0.0];
    }
    $daily = $expenses / $daysPassed;
    $remaining = $daily * max(0, $daysInMonth - $daysPassed);
    return [
        'average_daily' => $daily,
        'remaining' => $remaining,
        'month_end' => $expenses + $remaining,
    ];
}
