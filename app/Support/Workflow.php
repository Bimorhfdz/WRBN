<?php

namespace App\Support;

/**
 * The delivery pipeline from the meeting notes:
 *
 *   client_intake -> client_liaison -> koordinator -> pendukung ->
 *   analisis_utama -> qc -> finalisasi_dokumen -> done
 *
 * The client-facing stepper shows the first seven stages; "done" only exists
 * so the pipeline can be closed without inventing a ninth value.
 */
class Workflow
{
    /**
     * @var array<int, string>
     */
    public const STAGES = [
        'client_intake',
        'client_liaison',
        'koordinator',
        'pendukung',
        'analisis_utama',
        'qc',
        'finalisasi_dokumen',
        'done',
    ];

    /**
     * @var array<string, string>
     */
    public const STAGE_LABELS = [
        'client_intake' => 'Intake client',
        'client_liaison' => 'Client liaison',
        'koordinator' => 'Koordinator',
        'pendukung' => 'Tim pendukung',
        'analisis_utama' => 'Analis utama',
        'qc' => 'QC',
        'finalisasi_dokumen' => 'Finalisasi dokumen',
        'done' => 'Selesai',
    ];

    /**
     * Assignment roles. Deliberately different from the stage list: the team
     * does not assign anybody to the intake or the closing stage.
     *
     * @var array<int, string>
     */
    public const ROLES = [
        'client_liaison',
        'koordinator',
        'pendukung',
        'analis_utama',
        'qc',
        'finalisasi',
    ];

    /**
     * @var array<string, string>
     */
    public const ROLE_LABELS = [
        'client_liaison' => 'Client liaison',
        'koordinator' => 'Koordinator',
        'pendukung' => 'Tim pendukung',
        'analis_utama' => 'Analis utama',
        'qc' => 'QC',
        'finalisasi' => 'Finalisasi dokumen',
    ];

    /**
     * Legacy `consultations.status` derived from the pipeline stage.
     *
     * @var array<string, string>
     */
    public const STATUS_BY_STAGE = [
        'client_intake' => 'submitted',
        'client_liaison' => 'reviewing',
        'koordinator' => 'reviewing',
        'pendukung' => 'in_progress',
        'analisis_utama' => 'in_progress',
        'qc' => 'in_progress',
        'finalisasi_dokumen' => 'in_progress',
        'done' => 'completed',
    ];

    /**
     * Stages the client can see in the read-only stepper.
     *
     * @var array<int, string>
     */
    public const CLIENT_STAGES = [
        'client_intake',
        'client_liaison',
        'koordinator',
        'pendukung',
        'analisis_utama',
        'qc',
        'finalisasi_dokumen',
    ];

    public static function isStage(string $stage): bool
    {
        return in_array($stage, self::STAGES, true);
    }

    public static function isRole(string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    public static function label(string $stage): string
    {
        return self::STAGE_LABELS[$stage] ?? $stage;
    }

    public static function statusForStage(string $stage): string
    {
        return self::STATUS_BY_STAGE[$stage] ?? 'submitted';
    }

    /**
     * One step forward, or one step back (a rejected QC round returns the
     * project to the previous stage).
     */
    public static function canAdvance(string $from, string $to): bool
    {
        if (! self::isStage($from) || ! self::isStage($to)) {
            return false;
        }

        $distance = array_search($to, self::STAGES, true) - array_search($from, self::STAGES, true);

        return $distance === 1 || $distance === -1;
    }

    /**
     * @return array<int, array{stage: string, label: string, status: string}>
     */
    public static function clientSteps(): array
    {
        return array_map(fn (string $stage): array => [
            'stage' => $stage,
            'label' => self::label($stage),
            'status' => self::statusForStage($stage),
        ], self::CLIENT_STAGES);
    }
}
