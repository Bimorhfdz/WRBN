class DummyPaymentService {
  async createPayment(payment) {
    return {
      providerReference: `DUMMY-${payment.id}`,
      status: 'pending'
    };
  }

  async pay(payment, method) {
    return {
      providerReference: payment.provider_reference || `DUMMY-${payment.id}`,
      status: 'paid',
      method: method || 'qris'
    };
  }
}

class ExternalPaymentService {
  constructor({apiUrl, apiKey}) {
    this.apiUrl = apiUrl;
    this.apiKey = apiKey;
  }

  async createPayment(payment) {
    throw new Error('External payment provider belum diimplementasikan. Hubungkan adapter gateway di payment-service.js.');
  }

  async pay() {
    throw new Error('External payment provider belum diimplementasikan. Gunakan webhook gateway pada production.');
  }
}

function getPaymentService(env) {
  if (env.paymentProvider === 'dummy') return new DummyPaymentService();
  return new ExternalPaymentService({apiUrl:env.paymentApiUrl,apiKey:env.paymentApiKey});
}

module.exports = { getPaymentService };
