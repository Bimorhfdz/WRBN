<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ContactPerson;
use App\Models\ProjectAssignment;
use App\Models\ProjectUpdate;
use App\Models\RescheduleRequest;
use App\Models\Service;
use App\Models\UpgradeRequest;
use App\Services\Sheets\SheetsMirror;
use App\Support\Bot;
use App\Support\Input;
use App\Support\IntakePayload;
use App\Support\ProjectCode;
use App\Support\Rows;
use App\Support\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsultationController extends Controller
{
    private const STATUSES = ['submitted', 'reviewing', 'in_progress', 'completed', 'cancelled'];

    private function owned(Request $request, string $id): ?object
    {
        return DB::selectOne(
            'SELECT c.*, s.name service_name, s.price_label
             FROM consultations c JOIN services s ON s.id = c.service_id
             WHERE c.id = ? AND c.user_id = ?',
            [$id, $request->user()->id]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $serviceId = Input::clean($request->input('serviceId'), 100);
        $title = Input::clean($request->input('title'), 160);
        $description = Input::clean($request->input('description'), 5000);
        $scheduledAt = Input::clean($request->input('scheduledAt'), 80);
        $notes = Input::clean($request->input('notes'), 3000);
        $needType = Input::clean($request->input('needType'), 120);
        $deadline = Input::clean($request->input('deadline'), 80);

        if ($serviceId === '' || mb_strlen($title) < 3 || mb_strlen($description) < 5) {
            return response()->json(['message' => 'Paket, judul, dan deskripsi wajib diisi.'], 400);
        }

        if ($scheduledAt !== '' && ! Input::isValidDate($scheduledAt)) {
            return response()->json(['message' => 'Jadwal tidak valid.'], 400);
        }

        if ($deadline !== '' && ! Input::isValidDate($deadline)) {
            return response()->json(['message' => 'Deadline tidak valid.'], 400);
        }

        $service = Service::query()->where('id', $serviceId)->where('active', 1)->first();

        if (! $service) {
            return response()->json(['message' => 'Paket tidak ditemukan.'], 404);
        }

        $gate = $this->paymentGate($request, (string) $serviceId);
        if ($gate !== null) {
            return $gate;
        }

        $quota = $this->quotaGate($request, $service);
        if ($quota !== null) {
            return $quota;
        }

        $consultation = DB::transaction(function () use ($request, $service, $scheduledAt, $notes, $title, $description, $needType, $deadline) {

            $paid = DB::selectOne(
                "SELECT id FROM payments WHERE user_id = ? AND service_id = ? AND status = 'paid' ORDER BY paid_at DESC, created_at DESC LIMIT 1",
                [$request->user()->id, $service->id]
            );

            $consultation = Consultation::query()->create([
                'project_code' => null,
                'user_id' => $request->user()->id,
                'service_id' => $service->id,
                'payment_id' => $paid->id ?? null,
                'title' => $title,
                'description' => $description,
                'scheduled_at' => $scheduledAt !== '' ? $scheduledAt : null,
                'meeting_url' => null,
                'consultant_name' => null,
                'contact_person' => null,
                'notes' => $notes !== '' ? $notes : null,
                'need_type' => $needType !== '' ? $needType : null,
                'deadline' => $deadline !== '' ? $deadline : null,
                'status' => 'submitted',
            ]);

            $consultation->project_code = ProjectCode::make($consultation->created_at->toIso8601String(), $consultation->id);
            $consultation->save();

            ProjectUpdate::query()->create([
                'consultation_id' => $consultation->id,
                'title' => 'Project diterima',
                'message' => 'Pengajuan project sudah diterima dan menunggu ditinjau oleh konsultan.',
                'status' => 'submitted',
            ]);

            $this->assignGeneralContactPerson($consultation);

            return $consultation;
        });

        SheetsMirror::push([
            'paymentId' => null,
            'userId' => (string) $request->user()->id,
            'email' => (string) $request->user()->email,
            'nama' => (string) $request->user()->name,
            'whatsapp' => (string) $request->user()->whatsapp,
            'service' => (string) $service->name,
            'paket' => (string) $service->name,
            'jadwal' => $scheduledAt,
            'catatan' => $notes,
            'status' => (string) $consultation->status,
        ] + IntakePayload::for((string) $request->user()->id, $consultation));

        Bot::notify(Bot::PROJECT_CREATED, [
            'projectCode' => (string) $consultation->project_code,
            'title' => (string) $consultation->title,
            'service' => (string) $service->name,
            'clientName' => (string) $request->user()->name,
            'whatsapp' => (string) $request->user()->whatsapp,
            'contactPerson' => (string) $consultation->contact_person,
        ]);

        return response()->json([
            'consultation' => Rows::one(DB::selectOne('SELECT * FROM consultations WHERE id = ?', [$consultation->id])),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $rows = DB::select(
            'SELECT c.*, s.name service_name, s.price_label,
               (SELECT pu.title FROM project_updates pu WHERE pu.consultation_id = c.id ORDER BY pu.created_at DESC LIMIT 1) AS latest_update_title,
               (SELECT pu.message FROM project_updates pu WHERE pu.consultation_id = c.id ORDER BY pu.created_at DESC LIMIT 1) AS latest_update_message
             FROM consultations c JOIN services s ON s.id = c.service_id
             WHERE c.user_id = ? ORDER BY c.created_at DESC',
            [$request->user()->id]
        );

        return response()->json(['consultations' => Rows::iso($rows)]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $consultation = $this->owned($request, $id);

        if (! $consultation) {
            return response()->json(['message' => 'Project tidak ditemukan.'], 404);
        }

        $updates = DB::select('SELECT id, title, message, status, created_at FROM project_updates WHERE consultation_id = ? ORDER BY created_at DESC', [$id]);
        $files = DB::select('SELECT id, original_name, mime_type, size_bytes, created_at FROM consultation_files WHERE consultation_id = ? ORDER BY created_at DESC', [$id]);
        $reschedules = DB::select('SELECT id, current_scheduled_at, requested_scheduled_at, reason, status, created_at, updated_at FROM reschedule_requests WHERE consultation_id = ? ORDER BY created_at DESC', [$id]);
        $upgrades = DB::select(
            'SELECT ur.id, ur.reason, ur.status, ur.created_at, ur.updated_at, s.name requested_service_name, s.price_label requested_price_label
             FROM upgrade_requests ur JOIN services s ON s.id = ur.requested_service_id
             WHERE ur.consultation_id = ? ORDER BY ur.created_at DESC',
            [$id]
        );

        $liaison = DB::selectOne(
            'SELECT assignee_name, assignee_contact FROM project_assignments
             WHERE consultation_id = ? AND role = ? LIMIT 1',
            [$id, 'client_liaison']
        );

        return response()->json([
            'consultation' => Rows::one($consultation),
            'updates' => Rows::iso($updates),
            'files' => Rows::iso($files),
            'reschedules' => Rows::iso($reschedules),
            'upgrades' => Rows::iso($upgrades),
            'steps' => Workflow::clientSteps(),
            'currentStage' => (string) ($consultation->workflow_stage ?: 'client_intake'),
            'clientContact' => Rows::one($liaison),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $status = Input::clean($request->input('status'), 30);

        if ($status !== 'cancelled') {
            return response()->json(['message' => 'Status project dikelola oleh konsultan.'], 403);
        }

        $consultation = $this->owned($request, $id);

        if (! $consultation) {
            return response()->json(['message' => 'Project tidak ditemukan.'], 404);
        }

        if (in_array($consultation->status, ['cancelled', 'completed'], true)) {
            return response()->json(['message' => 'Project ini sudah tidak dapat dibatalkan.'], 409);
        }

        DB::transaction(function () use ($consultation) {
            Consultation::query()->where('id', $consultation->id)->update(['status' => 'cancelled']);

            ProjectUpdate::query()->create([
                'consultation_id' => $consultation->id,
                'title' => 'Project dibatalkan',
                'message' => 'Project dibatalkan oleh client.',
                'status' => 'cancelled',
            ]);
        });

        return response()->json(['consultation' => Rows::one($this->owned($request, $consultation->id))]);
    }

    public function reschedule(Request $request, string $id): JsonResponse
    {
        $consultation = $this->owned($request, $id);

        if (! $consultation) {
            return response()->json(['message' => 'Project tidak ditemukan.'], 404);
        }

        if (in_array($consultation->status, ['cancelled', 'completed'], true)) {
            return response()->json(['message' => 'Project ini tidak dapat dijadwalkan ulang.'], 409);
        }

        $requested = Input::clean($request->input('requestedScheduledAt'), 80);
        $reason = Input::clean($request->input('reason'), 1000);

        if ($requested === '' || ! Input::isValidDate($requested)) {
            return response()->json(['message' => 'Tanggal/jam reschedule tidak valid.'], 400);
        }

        $pending = DB::selectOne("SELECT id FROM reschedule_requests WHERE consultation_id = ? AND status = 'pending' LIMIT 1", [$id]);

        if ($pending) {
            return response()->json(['message' => 'Masih ada pengajuan reschedule yang menunggu diproses.'], 409);
        }

        RescheduleRequest::query()->create([
            'consultation_id' => $id,
            'user_id' => $request->user()->id,
            'current_scheduled_at' => $consultation->scheduled_at,
            'requested_scheduled_at' => $requested,
            'reason' => $reason !== '' ? $reason : null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pengajuan reschedule berhasil dikirim dan menunggu konfirmasi.',
            'status' => 'pending',
        ], 201);
    }

    public function upgrade(Request $request, string $id): JsonResponse
    {
        $consultation = $this->owned($request, $id);

        if (! $consultation) {
            return response()->json(['message' => 'Project tidak ditemukan.'], 404);
        }

        if (in_array($consultation->status, ['cancelled', 'completed'], true)) {
            return response()->json(['message' => 'Project ini tidak dapat diajukan upgrade.'], 409);
        }

        $requestedServiceId = Input::clean($request->input('requestedServiceId'), 100);
        $reason = Input::clean($request->input('reason'), 1000);

        $target = Service::query()->where('id', $requestedServiceId)->where('active', 1)->first();

        if (! $target) {
            return response()->json(['message' => 'Paket tujuan tidak ditemukan.'], 404);
        }

        if ($target->id === $consultation->service_id) {
            return response()->json(['message' => 'Paket tujuan sama dengan paket saat ini.'], 400);
        }

        $pending = DB::selectOne("SELECT id FROM upgrade_requests WHERE consultation_id = ? AND status = 'pending' LIMIT 1", [$id]);

        if ($pending) {
            return response()->json(['message' => 'Masih ada pengajuan upgrade yang menunggu diproses.'], 409);
        }

        UpgradeRequest::query()->create([
            'consultation_id' => $id,
            'user_id' => $request->user()->id,
            'current_service_id' => $consultation->service_id,
            'requested_service_id' => $target->id,
            'reason' => $reason !== '' ? $reason : null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pengajuan upgrade berhasil dikirim dan menunggu konfirmasi.',
            'status' => 'pending',
        ], 201);
    }

    /**
     * A brand new project has no team yet, so the general contact person is
     * attached as the client liaison until an admin assigns somebody else.
     */
    private function assignGeneralContactPerson(Consultation $consultation): void
    {
        $general = ContactPerson::query()
            ->where('active', true)
            ->orderByDesc('is_general')
            ->orderBy('created_at')
            ->first();

        if (! $general) {
            return;
        }

        ProjectAssignment::query()->create([
            'consultation_id' => $consultation->id,
            'role' => 'client_liaison',
            'assignee_name' => (string) $general->name,
            'assignee_contact' => (string) $general->whatsapp,
        ]);

        $consultation->contact_person = (string) $general->name;
        $consultation->save();
    }

    /**
     * Returns a ready-to-send error response when the project may not be
     * created, null when it may. Two doors: members skip the paid payment.
     */
    private function paymentGate(Request $request, string $serviceId): ?JsonResponse
    {
        $user = $request->user();

        if ($user->access_plan === 'member') {
            $active = DB::table('consultations')
                ->where('user_id', $user->id)
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->count();

            if ($active >= (int) config('wrbn.member_max_active')) {
                return response()->json([
                    'message' => 'Batas project aktif member tercapai. Selesaikan project berjalan terlebih dahulu.',
                ], 409);
            }

            return null;
        }

        $paid = DB::selectOne(
            "SELECT id FROM payments WHERE user_id = ? AND service_id = ? AND status = 'paid' ORDER BY paid_at DESC, created_at DESC LIMIT 1",
            [$user->id, $serviceId]
        );

        if (! $paid) {
            return response()->json(['message' => 'Paket belum dibayar. Selesaikan payment terlebih dahulu.'], 403);
        }

        $used = DB::selectOne('SELECT id FROM consultations WHERE payment_id = ? LIMIT 1', [$paid->id]);

        if ($used) {
            return response()->json([
                'message' => 'Pembayaran ini sudah dipakai untuk project. Buat payment baru untuk project berikutnya.',
            ], 409);
        }

        return null;
    }

    /**
     * A package covers a fixed number of datasets. Once they are used up the
     * client is sent back to the pricing table to pick another package.
     */
    private function quotaGate(Request $request, Service $service): ?JsonResponse
    {
        $used = DB::table('consultations')
            ->where('user_id', $request->user()->id)
            ->where('service_id', $service->id)
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($used >= (int) $service->data_quota) {
            return response()->json([
                'message' => sprintf(
                    'Kuota paket %s sudah habis (%d data). Pilih dan bayar paket baru untuk mengirim data berikutnya.',
                    $service->name,
                    (int) $service->data_quota
                ),
            ], 409);
        }

        return null;
    }
}
