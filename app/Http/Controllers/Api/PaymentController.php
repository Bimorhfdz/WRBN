<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Service;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Sheets\SheetsMirror;
use App\Support\Input;
use App\Support\IntakePayload;
use App\Support\Rows;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $serviceId = Input::clean($request->input('serviceId'), 100);
        $scheduledAt = Input::clean($request->input('scheduledAt'), 80);
        $notes = Input::clean($request->input('notes'), 2000);
        $whatsapp = Input::clean($request->input('whatsapp'), 30);

        if ($serviceId === '') {
            return response()->json(['message' => 'Service wajib dipilih.'], 400);
        }

        if ($scheduledAt !== '' && ! Input::isValidDate($scheduledAt)) {
            return response()->json(['message' => 'Jadwal tidak valid.'], 400);
        }

        $service = Service::query()->where('id', $serviceId)->where('active', 1)->first();

        if (! $service) {
            return response()->json(['message' => 'Service tidak ditemukan.'], 404);
        }

        $payment = Payment::query()->create([
            'user_id' => $request->user()->id,
            'service_id' => $service->id,
            'amount' => $service->price,
            'currency' => 'IDR',
            'status' => 'pending',
            'provider' => (string) config('wrbn.payment_provider'),
            'provider_reference' => null,
            'scheduled_at' => $scheduledAt !== '' ? $scheduledAt : null,
            'whatsapp' => $whatsapp,
            'notes' => $notes,
            'paid_at' => null,
        ]);

        $created = PaymentGatewayFactory::make()->createPayment($payment->getAttributes());
        $payment->update(['provider_reference' => $created['providerReference']]);

        SheetsMirror::push([
            'paymentId' => $payment->id,
            'userId' => $request->user()->id,
            'email' => $request->user()->email,
            'nama' => $request->user()->name,
            'whatsapp' => $whatsapp,
            'service' => $service->name,
            'paket' => $service->name,
            'jadwal' => $scheduledAt,
            'catatan' => $notes,
            'status' => 'pending',
        ] + IntakePayload::for((string) $request->user()->id));

        $row = DB::selectOne(
            'SELECT id, user_id, service_id, amount, currency, status, provider, provider_reference,
                    scheduled_at, whatsapp, notes, created_at, updated_at, paid_at
             FROM payments WHERE id = ?',
            [$payment->id]
        );

        return response()->json(['payment' => Rows::one($row)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $payments = DB::select(
            'SELECT p.id, p.amount, p.currency, p.status, p.provider, p.scheduled_at, p.created_at,
                    p.updated_at, p.paid_at, s.id service_id, s.name service_name, s.price_label
             FROM payments p JOIN services s ON s.id = p.service_id
             WHERE p.user_id = ? ORDER BY p.created_at DESC',
            [$request->user()->id]
        );

        return response()->json(['payments' => Rows::iso($payments)]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $payment = DB::selectOne(
            'SELECT p.*, s.name service_name, s.price_label
             FROM payments p JOIN services s ON s.id = p.service_id
             WHERE p.id = ? AND p.user_id = ?',
            [$id, $request->user()->id]
        );

        if (! $payment) {
            return response()->json(['message' => 'Payment tidak ditemukan.'], 404);
        }

        return response()->json(['payment' => Rows::one($payment)]);
    }

    public function dummyPay(Request $request, string $id): JsonResponse
    {
        if (app()->isProduction() && ! config('wrbn.allow_dummy_pay')) {
            return response()->json(['message' => 'Dummy payment dinonaktifkan.'], 403);
        }

        $payment = Payment::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment tidak ditemukan.'], 404);
        }

        if ($payment->status === 'paid') {
            return response()->json(['payment' => Rows::one($payment->getAttributes())]);
        }

        if ($payment->status !== 'pending') {
            return response()->json(['message' => "Payment berstatus {$payment->status}."], 409);
        }

        $result = PaymentGatewayFactory::make()->pay(
            $payment->getAttributes(),
            Input::clean($request->input('method'), 30)
        );

        $payment->update([
            'status' => $result['status'],
            'provider_reference' => $result['providerReference'],
            'paid_at' => $result['status'] === 'paid' ? now() : null,
        ]);

        SheetsMirror::push([
            'paymentId' => $payment->id,
            'userId' => $request->user()->id,
            'email' => $request->user()->email,
            'status' => $result['status'],
        ] + IntakePayload::for((string) $request->user()->id));

        $row = DB::selectOne(
            'SELECT p.*, s.name service_name, s.price_label
             FROM payments p JOIN services s ON s.id = p.service_id WHERE p.id = ?',
            [$payment->id]
        );

        return response()->json(['payment' => Rows::one($row)]);
    }
}
