<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\GradingPolicy;
use App\Repositories\ResultRepository;
use App\Repositories\StudentRepository;

final class ResultService
{
    public function __construct(
        private readonly ResultRepository $results,
        private readonly ?StudentRepository $students = null
    )
    {
    }

    public function getCurrentTermResultBundle(int $studentId): array
    {
        // Dashboard "current" term should always be the latest academic term
        // by year/semester for the student, regardless of upload time or is_current flag.
        $terms = $this->results->findTermsForStudent($studentId);
        $term = $terms[0] ?? null;

        if (!$term) {
            return [
                'term' => null,
                'rows' => [],
                'gpa' => null,
                'cgpa' => null,
            ];
        }

        $rows = $this->results->findResultsByTerm($studentId, (int) $term['id']);
        $gpa = $this->results->findTermGpaOverride($studentId, (int) $term['id']) ?? $this->computeGpa($rows);
        $cgpa = $this->computeCgpa($studentId);

        return [
            'term' => $term,
            'rows' => $rows,
            'gpa' => $gpa,
            'cgpa' => $cgpa,
        ];
    }

    public function getHistoryGrouped(int $studentId): array
    {
        $terms = $this->results->findTermsForStudent($studentId);
        $history = [];

        foreach ($terms as $term) {
            $rows = $this->results->findResultsByTerm($studentId, (int) $term['id']);
            $manualGpa = $this->results->findTermGpaOverride($studentId, (int) $term['id']);
            $history[] = [
                'term' => $term,
                'rows' => $rows,
                'gpa' => $manualGpa ?? $this->computeGpa($rows),
            ];
        }

        return $history;
    }

    public function getTranscriptBundle(int $studentId): array
    {
        $student = $this->students ? $this->students->findById($studentId) : null;
        $history = $this->getHistoryGrouped($studentId);

        return [
            'student' => $student,
            'history' => $history,
            'policy_name' => GradingPolicy::policyName(),
            'cgpa' => $this->computeCgpa($studentId),
        ];
    }

    private function computeCgpa(int $studentId): ?float
    {
        $manualCgpa = null;
        if (method_exists($this->results, 'findCgpaOverride')) {
            $finder = 'findCgpaOverride';
            $manualCgpa = $this->results->$finder($studentId);
        }
        if ($manualCgpa !== null) {
            return $manualCgpa;
        }

        $terms = $this->results->findTermsForStudent($studentId);
        if ($terms === []) {
            return null;
        }

        $allRows = [];
        foreach ($terms as $term) {
            $rows = $this->results->findResultsByTerm($studentId, (int) $term['id']);
            $allRows = array_merge($allRows, $rows);
        }

        return $this->computeGpa($allRows);
    }

    private function computeGpa(array $rows): ?float
    {
        if ($rows === []) {
            return null;
        }

        $weighted = 0.0;
        $credits = 0;

        foreach ($rows as $row) {
            $grade = strtoupper((string) ($row['grade'] ?? 'F'));
            $credit = (int) ($row['credit_hours'] ?? 0);
            if ($credit <= 0) {
                continue;
            }

            $point = GradingPolicy::gradePoints()[$grade] ?? 0.0;
            $weighted += $point * $credit;
            $credits += $credit;
        }

        if ($credits === 0) {
            return null;
        }

        return round($weighted / $credits, GradingPolicy::precision());
    }
}
