<?php

namespace App\Support;

/**
 * A deterministic, read-only crosswalk. This produces a bounded evidence
 * disposition, NEVER final field/restore/fairness/academic approval.
 */
final class FigP4OperationalEvidence
{
    public const CASES = [
        'AC-FIG-01', 'AC-FIG-02', 'AC-FIG-03', 'AC-FIG-04',
        'AC-FIG-05', 'AC-FIG-06', 'AC-FIG-07', 'AC-FIG-08',
        'AC-FIG-09', 'AC-FIG-10', 'AC-FIG-11', 'AC-FIG-12',
    ];

    /** @param array<string, string> $stages */
    public static function reconcile(array $stages): array
    {
        $historical = static fn (string $stage): string =>
            ($stages[$stage] ?? '') === 'PASS' ? 'PASS_SYNTHETIC_REUSED' : 'NOT_PROVEN';
        $new = static fn (string $stage): string =>
            ($stages[$stage] ?? '') === 'PASS' ? 'PASS_SYNTHETIC' : 'NOT_PROVEN';
        $records = [
            'AC-FIG-01' => $historical('full_regression'),
            'AC-FIG-02' => $historical('legacy_figural_focused'),
            'AC-FIG-03' => $historical('legacy_figural_focused'),
            'AC-FIG-04' => $historical('legacy_figural_focused'),
            'AC-FIG-05' => $historical('legacy_figural_focused'),
            'AC-FIG-06' => ($stages['chrome_browser_smoke'] ?? '') === 'PASS' ? 'PARTIAL_SYNTHETIC_PHASE' : 'NOT_PROVEN',
            'AC-FIG-07' => ($stages['fig_p4_pdf_focused'] ?? '') === 'PASS' ? 'PARTIAL_SYNTHETIC_CLASS_PDF' : 'NOT_PROVEN',
            'AC-FIG-08' => ($stages['fig_p4_pdf_focused'] ?? '') === 'PASS' ? 'PARTIAL_SYNTHETIC_CROSS_CONTEXT' : 'NOT_PROVEN',
            'AC-FIG-09' => $new('fig_p4_pdf_focused'),
            'AC-FIG-10' => $historical('full_regression'),
            'AC-FIG-11' => 'OPEN_REAL_DEVICE_AND_POLICY',
            'AC-FIG-12' => 'BLOCKED_NO_INDEPENDENT_TARGET',
        ];
        return [
            'case_count' => count($records),
            'cases' => $records,
            'p4_e02' => $new('fig_p4_pdf_focused'),
            'p4_e03_v02' => 'BLOCKED_NO_INDEPENDENT_TARGET',
            'p4_e04_v03' => ($stages['fig_p4_fairness_synthetic'] ?? '') === 'PASS'
                ? 'PASS_SYNTHETIC_OBSERVABILITY_ONLY'
                : 'INCONCLUSIVE_OBSERVABILITY',
            'p4_e05' => 'PARTIAL_EXTERNAL_SIGNATURES_OPEN',
            'gp02' => 'OPEN_HUMAN_ACADEMIC_AND_MEDIA_RIGHTS',
            'gp03' => 'OPEN_OPERATIONAL_EVIDENCE',
            'gp04' => 'OPEN_INDEPENDENT_RESTORE',
            'gp05' => 'OPEN_REAL_DEVICE_THRESHOLDS',
            'gp06' => 'HOLD',
            'pilot_go' => false,
            'public_seo_sales_go' => false,
        ];
    }
}
