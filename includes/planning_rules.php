<?php
/**
 * Shared, explainable SSLMS planning rules.
 * The weights mirror those reported in the dissertation and original Study Planner.
 * These heuristic scores support decisions; they do not measure health or academic outcomes.
 */

declare(strict_types=1);

function sslms_assignment_recommendation(array $assignment, ?DateTimeImmutable $today = null): array
{
    $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
    $dueDate = (new DateTimeImmutable((string) $assignment['due_date']))->setTime(0, 0);
    $days = (int) $today->diff($dueDate)->format('%r%a');
    $score = 0;
    $reasons = [];

    if ($days < 0) {
        $score += 60;
        $reasons[] = 'Overdue by ' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's');
    } elseif ($days === 0) {
        $score += 55;
        $reasons[] = 'Due today';
    } elseif ($days === 1) {
        $score += 50;
        $reasons[] = 'Due tomorrow';
    } elseif ($days <= 3) {
        $score += 40;
        $reasons[] = 'Due in ' . $days . ' days';
    } elseif ($days <= 7) {
        $score += 30;
        $reasons[] = 'Due this week';
    } elseif ($days <= 14) {
        $score += 20;
        $reasons[] = 'Due within two weeks';
    } else {
        $score += 10;
        $reasons[] = 'Deadline is further away';
    }

    switch ($assignment['priority'] ?? 'low') {
        case 'high':
            $score += 30;
            $reasons[] = 'High priority';
            break;
        case 'medium':
            $score += 18;
            $reasons[] = 'Medium priority';
            break;
        default:
            $score += 8;
            $reasons[] = 'Low priority';
    }

    $hours = max(0, (float) ($assignment['estimated_hours'] ?? 0));
    if ($hours >= 10) {
        $score += 15;
        $reasons[] = 'Large workload';
    } elseif ($hours >= 5) {
        $score += 10;
        $reasons[] = 'Moderate workload';
    } elseif ($hours > 0) {
        $score += 5;
        $reasons[] = 'Shorter workload';
    }
    if (($assignment['status'] ?? '') === 'in_progress') {
        $score += 5;
        $reasons[] = 'Already in progress';
    }

    if ($score >= 80) {
        $urgency = 'Critical';
        $class = 'bg-danger';
    } elseif ($score >= 60) {
        $urgency = 'High';
        $class = 'bg-warning';
    } elseif ($score >= 40) {
        $urgency = 'Medium';
        $class = 'bg-primary';
    } else {
        $urgency = 'Low';
        $class = 'bg-success';
    }

    $assignment['days_remaining'] = $days;
    $assignment['score'] = $score;
    $assignment['reasons'] = $reasons;
    $assignment['urgency'] = $urgency;
    $assignment['urgency_class'] = $class;
    return $assignment;
}

function sslms_sort_recommendations(array &$recommendations): void
{
    usort($recommendations, static function (array $a, array $b): int {
        $priority = ($b['score'] <=> $a['score']);
        return $priority !== 0 ? $priority : strcmp((string) $a['due_date'], (string) $b['due_date']);
    });
}

/** Return hours; identical start/end is treated as invalid / zero hours. */
function sslms_shift_hours(string $date, string $startTime, string $endTime): float
{
    if ($startTime === $endTime) {
        return 0.0;
    }
    $start = new DateTimeImmutable($date . ' ' . $startTime);
    $end = new DateTimeImmutable($date . ' ' . $endTime);
    if ($end < $start) {
        $end = $end->modify('+1 day');
    }
    return max(0.0, ($end->getTimestamp() - $start->getTimestamp()) / 3600);
}
