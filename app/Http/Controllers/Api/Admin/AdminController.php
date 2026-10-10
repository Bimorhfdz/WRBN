<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationFile;
use App\Models\MemberEmail;
use App\Models\Payment;
use App\Models\ProjectAssignment;
use App\Models\ProjectUpdate;
use App\Models\StageHistory;
use App\Support\Bot;
use App\Support\Input;
use App\Support\Rows;
use App\Support\Uploads;
use App\Support\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    private const STATUSES = ['submitted', 'reviewing', 'in_progress', 'completed', 'cancelled'];

    public function dashboard(): JsonResponse
    {
        $stats = [
            'users' => (int) DB::table('users')->count(),
            'projects' => (int) DB::table('consultations')->count(),
            'active' => (int) DB::table('consultations')->whereIn('status', ['submitted', 'reviewing', 'in_progress'])->count(),
            'completed' => (int) DB::table('consultations')->where('status', 'completed')->count(),
            'pendingReschedules' => (int) DB::table('reschedule_requests')->where('status', 'pending')->count(),
            'pendingUpgrades' => (int) DB::table('upgrade_requests')->where('status', 'pending')->count(),
            'pendingPayments' => (int) DB::table('payments')->where('status', 'pending')->count(),
            'messages' => (int) DB::table('contact_messages')->count(),
        ];

        return response()->json(['stats' => $stats]);
    }

    public function users(): JsonResponse
    {
        $users = DB::select(
            'SELECT u.id, u.name, u.email, u.whatsapp, u.created_at,
               (SELECT COUNT(*) FROM consultations c WHERE c.user_id = u.id) AS consultation_count,
               (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id) AS payment_count
             FROM users u ORDER BY u.created_at DESC'
        );

        return response()->json(['users' => Rows::iso($users)]);
    }

    public function deleteUsers(Request $request): JsonResponse
    {
        $raw = $request->input('ids');
        $ids = is_array($raw) ? array_values(array_unique(array_filter(array_map(fn ($value) => trim((string) $value), $raw), fn ($value) => $value !== ''))) : [];

        if (count($ids) === 0) {
            return response()->json(['message' => 'Pilih minimal satu akun.'], 400);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $users = DB::select("SELECT id, email FROM users WHERE id IN ({$placeholders})", $ids);

        if (count($users) === 0) {
            return response()->json(['message' => 'Akun yang dipilih tidak ditemukan.'], 404);
        }

        $userIds = array_column($users, 'id');
        $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $files = DB::select(
            "SELECT cf.storage_path FROM consultation_files cf
             JOIN consultations c ON c.id = cf.consultation_id
             WHERE c.user_id IN ({$userPlaceholders})",
            $userIds
        );

        DB::transaction(function () use ($userIds, $userPlaceholders): void {
            DB::delete("DELETE FROM users WHERE id IN ({$userPlaceholders})", $userIds);
        });

        foreach ($files as $file) {
            Uploads::delete($file->storage_path ?? null);
        }

        return response()->json([
            'message' => count($users).' akun berhasil dihapus.',
            'deleted' => array_map(fn (object $user) => $user->email, $users),
        ]);
    }

    /**
     * The member whitelist behind door number two.
     */
    public function members(): JsonResponse
    {
        $members = DB::select(
            'SELECT m.id, m.email, m.plan_label, m.note, m.expires_at, m.created_at,
                    (SELECT COUNT(*) FROM users u WHERE u.email = m.email) AS linked_users
             FROM member_emails m ORDER BY m.created_at DESC'
        );

        return response()->json(['members' => Rows::iso($members)]);
    }

    /**
     * Accepts a single textarea or an array; emails may be separated by new
     * lines, commas, semicolons or spaces. Duplicates are ignored.
     */
    public function addMembers(Request $request): JsonResponse
    {
        $planLabel = Input::clean($request->input('planLabel'), 80);
        $note = Input::clean($request->input('note'), 300);
        $expiresAt = Input::clean($request->input('expiresAt'), 40);

        if ($expiresAt !== '' && ! Input::isValidDate($expiresAt)) {
            return response()->json(['message' => 'Tanggal kadaluarsa tidak valid.'], 400);
        }

        $emails = [];

        foreach ($this->splitEmails($request->input('emails')) as $email) {
            if (Input::isEmail($email)) {
                $emails[$email] = true;
            }
        }

        if (count($emails) === 0) {
            return response()->json(['message' => 'Tidak ada email yang valid.'], 400);
        }

        $added = [];

        foreach (array_keys($emails) as $email) {
            if (DB::table('member_emails')->where('email', $email)->exists()) {
                continue;
            }

            MemberEmail::query()->create([
                'email' => $email,
                'plan_label' => $planLabel !== '' ? $planLabel : null,
                'note' => $note !== '' ? $note : null,
                'expires_at' => $expiresAt !== '' ? $expiresAt : null,
            ]);

            $added[] = $email;
        }

        return response()->json([
            'message' => count($added).' email berhasil ditambahkan ke whitelist member.',
            'added' => $added,
        ]);
    }

    /**
     * Removing an address only leaves the whitelist: the account and its
     * projects stay exactly where they are.
     */
    public function removeMembers(Request $request): JsonResponse
    {
        $raw = $request->input('ids');
        $ids = is_array($raw) ? array_values(array_unique(array_filter(array_map(fn ($value) => trim((string) $value), $raw), fn ($value) => $value !== ''))) : [];

        if (count($ids) > 0) {
            $deleted = DB::table('member_emails')->whereIn('id', $ids)->pluck('id')->all();
        } else {
            $emails = array_values(array_unique(array_filter($this->splitEmails($request->input('emails')))));

            if (count($emails) === 0) {
                return response()->json(['message' => 'Pilih minimal satu email.'], 400);
            }

            $deleted = DB::table('member_emails')->whereIn('email', $emails)->pluck('id')->all();
        }

        if (count($deleted) === 0) {
            return response()->json(['message' => 'Email tidak ditemukan di whitelist.'], 404);
        }

        $placeholders = implode(',', array_fill(0, count($deleted), '?'));
        DB::delete("DELETE FROM member_emails WHERE id IN ({$placeholders})", $deleted);

        return response()->json([
            'message' => count($deleted).' email dihapus dari whitelist member.',
            'deleted' => array_values($deleted),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function splitEmails(mixed $raw): array
    {
        if (is_array($raw)) {
            $raw = implode("\n", array_map(fn ($value): string => (string) $value, $raw));
        }

        $parts = preg_split('/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn (string $value): string => strtolower(trim($value)), $parts);
    }

    public function projects(): JsonResponse
    {
        $projects = DB::select(
            'SELECT c.id, c.project_code, c.title, c.status, c.workflow_stage, c.scheduled_at, c.meeting_url,
                    c.consultant_name, c.contact_person, c.notes, c.created_at, c.updated_at,
                    u.name user_name, u.email user_email, u.whatsapp user_whatsapp,
                    s.name service_name, s.price_label,
                    (SELECT pu.title FROM project_updates pu WHERE pu.consultation_id = c.id ORDER BY pu.created_at DESC LIMIT 1) latest_update_title,
                    (SELECT pu.message FROM project_updates pu WHERE pu.consultation_id = c.id ORDER BY pu.created_at DESC LIMIT 1) latest_update_message
             FROM consultations c
             JOIN users u ON u.id = c.user_id
             JOIN services s ON s.id = c.service_id
             ORDER BY c.updated_at DESC'
        );

        return response()->json(['projects' => Rows::iso($projects)]);
    }

    public function project(string $id): JsonResponse
    {
        $project = DB::selectOne(
            'SELECT c.*, u.name user_name, u.email user_email, u.whatsapp user_whatsapp,
                    s.name service_name, s.price_label
             FROM consultations c
             JOIN users u ON u.id = c.user_id
             JOIN services s ON s.id = c.service_id
             WHERE c.id = ?',
            [$id]
        );

        if (! $project) {
            return response()->json(['message' => 'Project tidak ditemukan.'], 404);
        }

        $updates = DB::select('SELECT id, title, message, status, created_at FROM project_updates WHERE consultation_id = ? ORDER BY created_at DESC', [$id]);
        $files = DB::select('SELECT id, original_name, mime_type, size_bytes, created_at FROM consultation_files WHERE consultation_id = ? ORDER BY created_at DESC', [$id]);
        $reschedules = DB::select(
            'SELECT r.*, u.name user_name, u.email user_email
             FROM reschedule_requests r JOIN users u ON u.id = r.user_id
             WHERE r.consultation_id = ? ORDER BY r.created_at DESC',
            [$id]
        );
        $upgrades = DB::select(
            'SELECT r.*, a.name current_service_name, a.price_label current_price_label,
                    b.name requested_service_name, b.price_label requested_price_label
             FROM upgrade_requests r
             JOIN services a ON a.id = r.current_service_id
             JOIN services b ON b.id = r.requested_service_id
             WHERE r.consultation_id = ? ORDER BY r.created_at DESC',
            [$id]
        );
        $payments = DB::select(
            'SELECT p.*, s.name service_name
             FROM payments p JOIN services s ON s.id = p.service_id
             WHERE p.user_id = ? ORDER BY p.created_at DESC',
            [$project->user_id]
        );

        $profile = DB::selectOne(
            'SELECT age, occupation, institution, level, city, referral
             FROM user_profiles WHERE user_id = ?',
            [$project->user_id]
        );

        return response()->json([
            'project' => Rows::one($project),
            'clientProfile' => Rows::one($profile),

            'updates' => Rows::iso($updates),
            'files' => Rows::iso($files),
            'reschedules' => Rows::iso($reschedules),
            'upgrades' => Rows::iso($upgrades),
            'payments' => Rows::iso($payments),
            'assignments' => $this->assignments($id),
            'stageHistory' => $this->stageHistory($id),
            'workflow' => $this->workflowPayload($project),
        ]);
    }

    public function updateProject(Request $request, string $id): JsonResponse
    {
        $current = DB::selectOne('SELECT * FROM consultations WHERE id = ?', [$id]);

        if (! $current) {
            return response()->json(['message' => 'Project tidak ditemukan.'], 404);
        }

        $status = array_key_exists('status', $request->all()) ? Input::clean($request->input('status'), 30) : $current->status;
        $scheduledAt = array_key_exists('scheduledAt', $request->all()) ? Input::clean($request->input('scheduledAt'), 80) : $current->scheduled_at;
        $meetingUrl = array_key_exists('meetingUrl', $request->all()) ? Input::clean($request->input('meetingUrl'), 500) : $current->meeting_url;
        $consultantName = array_key_exists('consultantName', $request->all()) ? Input::clean($request->input('consultantName'), 160) : $current->consultant_name;
        $contactPerson = array_key_exists('contactPerson', $request->all()) ? Input::clean($request->input('contactPerson'), 160) : $current->contact_person;
        $notes = array_key_exists('notes', $request->all()) ? Input::clean($request->input('notes'), 3000) : $current->notes;

        if (! in_array($status, self::STATUSES, true)) {
            return response()->json(['message' => 'Status project tidak valid.'], 400);
        }

        if ($scheduledAt !== null && $scheduledAt !== '' && ! Input::isValidDate($scheduledAt)) {
            return response()->json(['message' => 'Jadwal tidak valid.'], 400);
        }

        $changed = [];
        if ($status !== $current->status) {
            $changed[] = "Status: {$status}";
        }
        if ($scheduledAt !== $current->scheduled_at) {
            $changed[] = 'Jadwal: '.($scheduledAt ?: 'dihapus');
        }
        if ($meetingUrl !== $current->meeting_url) {
            $changed[] = 'Link meeting diperbarui';
        }
        if ($consultantName !== $current->consultant_name) {
            $changed[] = 'Konsultan: '.($consultantName ?: 'belum ditentukan');
        }
        if ($contactPerson !== $current->contact_person) {
            $changed[] = 'Kontak: '.($contactPerson ?: 'belum ditentukan');
        }

        DB::transaction(function () use ($current, $status, $scheduledAt, $meetingUrl, $consultantName, $contactPerson, $notes, $changed) {
            Consultation::query()->where('id', $current->id)->update([
                'status' => $status,
                'scheduled_at' => $scheduledAt ?: null,
                'meeting_url' => $meetingUrl ?: null,
                'consultant_name' => $consultantName ?: null,
                'contact_person' => $contactPerson ?: null,
                'notes' => $notes ?: null,
            ]);

            if (count($changed) > 0) {
                ProjectUpdate::query()->create([
                    'consultation_id' => $current->id,
                    'title' => 'Project diperbarui oleh admin',
                    'message' => implode(' · ', $changed),
                    'status' => $status,
                ]);
            }
        });

        return response()->json(['message' => 'Project berhasil diperbarui.']);
    }

    public function addProgress(Request $request, string $id): JsonResponse
    {
        $consultation = DB::selectOne('SELECT id, status, project_code, user_id FROM consultations WHERE id = ?', [$id]);

        if (! $consultation) {
            return response()->json(['message' => 'Project tidak ditemukan.'], 404);
        }

        $title = Input::clean($request->input('title'), 160);
        $message = Input::clean($request->input('message'), 5000);
        $status = $request->filled('status') ? Input::clean($request->input('status'), 30) : $consultation->status;

        if (mb_strlen($title) < 2 || mb_strlen($message) < 2) {
            return response()->json(['message' => 'Judul dan isi progress wajib diisi.'], 400);
        }

        if (! in_array($status, self::STATUSES, true)) {
            return response()->json(['message' => 'Status progress tidak valid.'], 400);
        }

        DB::transaction(function () use ($consultation, $title, $message, $status) {
            Consultation::query()->where('id', $consultation->id)->update(['status' => $status]);

            ProjectUpdate::query()->create([
                'consultation_id' => $consultation->id,
                'title' => $title,
                'message' => $message,
                'status' => $status,
            ]);
        });

        Bot::notify(Bot::PROGRESS_ADDED, $this->botPayload($consultation, [
            'progressTitle' => $title,
            'progressMessage' => $message,
            'status' => $status,
        ]));

        return response()->json(['message' => 'Progress berhasil dikirim ke Client Area.'], 201);
    }

    /**
     * Move the project one step forward (or back) along the delivery pipeline.
     */
    public function updateStage(Request $request, string $id): JsonResponse
    {
        $project = DB::selectOne('SELECT id, status, workflow_stage, project_code, user_id FROM consultations WHERE id = ?', [$id]);

        if (! $project) {
            return response()->json(['message' => 'Project tidak ditemukan.'], 404);
        }

        if ($project->status === 'cancelled') {
            return response()->json(['message' => 'Project yang dibatalkan tidak dapat mengubah tahap.'], 409);
        }

        $stage = Input::clean($request->input('stage'), 40);
        $note = Input::clean($request->input('note'), 1000);

        if (! Workflow::isStage($stage)) {
            return response()->json(['message' => 'Tahap tidak valid.'], 400);
        }

        $from = (string) ($project->workflow_stage ?: 'client_intake');

        if ($stage === $from) {
            return response()->json(['message' => 'Project sudah berada di tahap tersebut.'], 409);
        }

        if (! Workflow::canAdvance($from, $stage)) {
            return response()->json(['message' => 'Perpindahan tahap harus satu langkah maju atau mundur.'], 409);
        }

        $status = Workflow::statusForStage($stage);

        DB::transaction(function () use ($project, $from, $stage, $note, $status): void {
            Consultation::query()->where('id', $project->id)->update([
                'workflow_stage' => $stage,
                'status' => $status,
            ]);

            StageHistory::query()->create([
                'consultation_id' => $project->id,
                'from_stage' => $from,
                'to_stage' => $stage,
                'note' => $note !== '' ? $note : null,
            ]);

            ProjectUpdate::query()->create([
                'consultation_id' => $project->id,
                'title' => 'Tahap project diperbarui',
                'message' => sprintf(
                    '%s -> %s%s',
                    Workflow::label($from),
                    Workflow::label($stage),
                    $note !== '' ? ' - '.$note : ''
                ),
                'status' => $status,
            ]);
        });

        Bot::notify(Bot::STAGE_CHANGED, $this->botPayload($project, [
            'fromStage' => $from,
            'toStage' => $stage,
            'fromLabel' => Workflow::label($from),
            'toLabel' => Workflow::label($stage),
            'note' => $note !== '' ? $note : null,
            'status' => $status,
        ]));

        return response()->json([
            'message' => 'Tahap project diperbarui.',

            'project' => $this->projectRow($id),
            'assignments' => $this->assignments($id),
            'stageHistory' => $this->stageHistory($id),
            'workflow' => $this->workflowPayload(DB::selectOne('SELECT workflow_stage FROM consultations WHERE id = ?', [$id])),
        ]);
    }

    /**
     * Replace the six per-project roles. An empty name removes the role.
     */
    public function updateAssignments(Request $request, string $id): JsonResponse
    {
        $project = DB::selectOne('SELECT id FROM consultations WHERE id = ?', [$id]);

        if (! $project) {
            return response()->json(['message' => 'Project tidak ditemukan.'], 404);
        }

        $raw = $request->input('assignments');

        if (! is_array($raw)) {
            return response()->json(['message' => 'Data penugasan tidak valid.'], 400);
        }

        $assign = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                return response()->json(['message' => 'Data penugasan tidak valid.'], 400);
            }

            $role = Input::clean($entry['role'] ?? null, 30);
            $name = Input::clean($entry['name'] ?? null, 160);
            $contact = Input::clean($entry['contact'] ?? null, 160);

            if (! Workflow::isRole($role)) {
                return response()->json(['message' => 'Peran tidak valid.'], 400);
            }

            if ($name === '') {
                continue;
            }

            $assign[$role] = ['name' => $name, 'contact' => $contact];
        }

        DB::transaction(function () use ($project, $assign): void {
            foreach (Workflow::ROLES as $role) {
                if (! isset($assign[$role])) {
                    ProjectAssignment::query()
                        ->where('consultation_id', $project->id)
                        ->where('role', $role)
                        ->delete();

                    continue;
                }

                ProjectAssignment::query()->updateOrCreate(
                    ['consultation_id' => $project->id, 'role' => $role],
                    ['assignee_name' => $assign[$role]['name'], 'assignee_contact' => $assign[$role]['contact']]
                );
            }

            Consultation::query()->where('id', $project->id)->update([
                'consultant_name' => $assign['analis_utama']['name'] ?? null,
                'contact_person' => $assign['client_liaison']['name'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Penugasan tim tersimpan.',
            'project' => $this->projectRow($id),
            'assignments' => $this->assignments($id),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * Shared envelope for every bot notification. `text` is what LinkBot turns
     * into a wa.me deep link an operator can send by hand.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function botPayload(object $consultation, array $extra = []): array
    {
        $user = DB::selectOne('SELECT name, whatsapp FROM users WHERE id = ?', [$consultation->user_id ?? null]);

        $code = (string) ($consultation->project_code ?? '');

        $parts = [];
        foreach ($extra as $key => $value) {
            $parts[] = $key.': '.($value === null ? '-' : (string) $value);
        }

        return [
            'projectCode' => $code,
            'clientName' => (string) ($user->name ?? ''),
            'whatsapp' => (string) ($user->whatsapp ?? ''),
            'text' => '[WRBN] '.implode(' | ', $parts).' (project '.($code === '' ? '-' : $code).')',
        ] + $extra;
    }

    protected function assignments(string $consultationId): array
    {
        $rows = DB::select(
            'SELECT id, role, assignee_name, assignee_contact, created_at, updated_at
             FROM project_assignments WHERE consultation_id = ? ORDER BY created_at ASC',
            [$consultationId]
        );

        return Rows::iso($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function stageHistory(string $consultationId): array
    {
        $rows = DB::select(
            'SELECT id, from_stage, to_stage, note, created_at
             FROM stage_history WHERE consultation_id = ? ORDER BY created_at ASC',
            [$consultationId]
        );

        return Rows::iso($rows);
    }

    /**
     * @return array<string, mixed>
     */
    protected function workflowPayload(object $project): array
    {
        $current = (string) ($project->workflow_stage ?: 'client_intake');

        return [
            'current' => $current,
            'label' => Workflow::label($current),
            'status' => Workflow::statusForStage($current),
            'stages' => array_map(fn (string $stage): array => [
                'stage' => $stage,
                'label' => Workflow::label($stage),
                'status' => Workflow::statusForStage($stage),
            ], Workflow::STAGES),
            'available' => array_values(array_filter(
                Workflow::STAGES,
                fn (string $stage): bool => Workflow::canAdvance($current, $stage)
            )),
        ];
    }

    public function requests(): JsonResponse
    {
        $reschedules = DB::select(
            'SELECT r.*, c.project_code, c.title, u.name user_name, u.email user_email
             FROM reschedule_requests r
             JOIN consultations c ON c.id = r.consultation_id
             JOIN users u ON u.id = r.user_id
             WHERE r.status = ? ORDER BY r.created_at DESC',
            ['pending']
        );

        $upgrades = DB::select(
            'SELECT r.*, c.project_code, c.title, u.name user_name, u.email user_email,
                    a.name current_service_name, b.name requested_service_name, b.price_label requested_price_label
             FROM upgrade_requests r
             JOIN consultations c ON c.id = r.consultation_id
             JOIN users u ON u.id = r.user_id
             JOIN services a ON a.id = r.current_service_id
             JOIN services b ON b.id = r.requested_service_id
             WHERE r.status = ? ORDER BY r.created_at DESC',
            ['pending']
        );

        return response()->json([
            'reschedules' => Rows::iso($reschedules),
            'upgrades' => Rows::iso($upgrades),
        ]);
    }

    public function resolveReschedule(Request $request, string $id): JsonResponse
    {
        $action = Input::clean($request->input('action'), 20);

        if (! in_array($action, ['approved', 'rejected'], true)) {
            return response()->json(['message' => 'Aksi harus approved atau rejected.'], 400);
        }

        $row = DB::selectOne('SELECT * FROM reschedule_requests WHERE id = ?', [$id]);

        if (! $row) {
            return response()->json(['message' => 'Pengajuan reschedule tidak ditemukan.'], 404);
        }

        if ($row->status !== 'pending') {
            return response()->json(['message' => 'Pengajuan ini sudah diproses.'], 409);
        }

        $consultation = DB::selectOne('SELECT * FROM consultations WHERE id = ?', [$row->consultation_id]);

        DB::transaction(function () use ($row, $consultation, $action) {
            DB::table('reschedule_requests')->where('id', $row->id)->update(['status' => $action, 'updated_at' => now()]);

            if ($action === 'approved') {
                DB::table('consultations')->where('id', $consultation->id)->update(['scheduled_at' => $row->requested_scheduled_at, 'updated_at' => now()]);
            }

            ProjectUpdate::query()->create([
                'consultation_id' => $consultation->id,
                'title' => $action === 'approved' ? 'Reschedule disetujui' : 'Reschedule ditolak',
                'message' => $action === 'approved' ? "Jadwal baru: {$row->requested_scheduled_at}" : 'Pengajuan perubahan jadwal ditolak oleh admin.',
                'status' => $consultation->status,
            ]);
        });

        Bot::notify(Bot::RESCHEDULE_RESOLVED, $this->botPayload($consultation, [
            'action' => $action,
            'requestedScheduledAt' => $row->requested_scheduled_at,
            'reason' => $row->reason,
        ]));

        return response()->json(['message' => 'Reschedule '.($action === 'approved' ? 'disetujui' : 'ditolak').'.']);
    }

    public function resolveUpgrade(Request $request, string $id): JsonResponse
    {
        $action = Input::clean($request->input('action'), 20);

        if (! in_array($action, ['approved', 'rejected'], true)) {
            return response()->json(['message' => 'Aksi harus approved atau rejected.'], 400);
        }

        $row = DB::selectOne('SELECT * FROM upgrade_requests WHERE id = ?', [$id]);

        if (! $row) {
            return response()->json(['message' => 'Pengajuan upgrade tidak ditemukan.'], 404);
        }

        if ($row->status !== 'pending') {
            return response()->json(['message' => 'Pengajuan ini sudah diproses.'], 409);
        }

        $consultation = DB::selectOne('SELECT * FROM consultations WHERE id = ?', [$row->consultation_id]);
        $target = DB::selectOne('SELECT name FROM services WHERE id = ?', [$row->requested_service_id]);

        DB::transaction(function () use ($row, $consultation, $target, $action) {
            DB::table('upgrade_requests')->where('id', $row->id)->update(['status' => $action, 'updated_at' => now()]);

            if ($action === 'approved') {
                DB::table('consultations')->where('id', $consultation->id)->update(['service_id' => $row->requested_service_id, 'updated_at' => now()]);
            }

            ProjectUpdate::query()->create([
                'consultation_id' => $consultation->id,
                'title' => $action === 'approved' ? 'Upgrade disetujui' : 'Upgrade ditolak',
                'message' => $action === 'approved' ? "Paket project diperbarui menjadi {$target->name}." : 'Pengajuan upgrade ditolak oleh admin.',
                'status' => $consultation->status,
            ]);
        });

        Bot::notify(Bot::UPGRADE_RESOLVED, $this->botPayload($consultation, [
            'action' => $action,
            'requestedService' => $target->name,
            'reason' => $row->reason,
        ]));

        return response()->json(['message' => 'Upgrade '.($action === 'approved' ? 'disetujui' : 'ditolak').'.']);
    }

    public function payments(): JsonResponse
    {
        $payments = DB::select(
            'SELECT p.*, u.name user_name, u.email user_email, s.name service_name
             FROM payments p
             JOIN users u ON u.id = p.user_id
             JOIN services s ON s.id = p.service_id
             ORDER BY p.created_at DESC'
        );

        return response()->json(['payments' => Rows::iso($payments)]);
    }

    public function updatePayment(Request $request, string $id): JsonResponse
    {
        $status = Input::clean($request->input('status'), 20);

        if (! in_array($status, ['pending', 'paid', 'failed', 'expired'], true)) {
            return response()->json(['message' => 'Status payment tidak valid.'], 400);
        }

        $payment = DB::selectOne('SELECT id FROM payments WHERE id = ?', [$id]);

        if (! $payment) {
            return response()->json(['message' => 'Payment tidak ditemukan.'], 404);
        }

        Payment::query()->where('id', $payment->id)->update([
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);

        return response()->json(['message' => 'Status payment diperbarui.']);
    }

    public function downloadFile(string $id)
    {
        $file = ConsultationFile::query()->select('original_name', 'storage_path', 'mime_type', 'contents')->where('id', $id)->first();

        if (! $file) {
            return response()->json(['message' => 'File tidak ditemukan.'], 404);
        }

        if ($file->contents !== null) {
            return response()->streamDownload(function () use ($file): void {
                echo $file->contents;
            }, $file->original_name, ['Content-Type' => $file->mime_type]);
        }

        $path = Uploads::absolutize($file->storage_path);

        if ($path === null || ! is_file($path)) {
            return response()->json(['message' => 'File tidak tersedia di server.'], 404);
        }

        return response()->download($path, $file->original_name);
    }

    public function messages(): JsonResponse
    {
        $messages = DB::select('SELECT * FROM contact_messages ORDER BY created_at DESC');

        return response()->json(['messages' => Rows::iso($messages)]);
    }

    /**
     * Shared by the admin project detail so bagian 8 can append assignments.
     *
     * @return array<string, mixed>|null
     */
    protected function projectRow(string $id): ?array
    {
        $project = DB::selectOne(
            'SELECT c.*, u.name user_name, u.email user_email, u.whatsapp user_whatsapp,
                    s.name service_name, s.price_label
             FROM consultations c
             JOIN users u ON u.id = c.user_id
             JOIN services s ON s.id = c.service_id
             WHERE c.id = ?',
            [$id]
        );

        return $project ? Rows::one($project) : null;
    }
}
