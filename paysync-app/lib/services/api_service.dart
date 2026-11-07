import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/invoice.dart';
import '../models/reconciliation.dart';
import '../models/transaction.dart';

class ApiService {
  // Chrome runs on the same machine as the API. For an Android emulator use 10.0.2.2 instead.
  static const String baseUrl = 'http://127.0.0.1:8000/api';

  static const Map<String, String> _headers = {'Accept': 'application/json'};

  Future<dynamic> _get(String path) async {
    final res = await http
        .get(Uri.parse('$baseUrl$path'), headers: _headers)
        .timeout(const Duration(seconds: 15));

    if (res.statusCode != 200) {
      throw Exception('Request failed (HTTP ${res.statusCode}) for $path');
    }
    return jsonDecode(res.body);
  }

  Future<dynamic> _post(String path) async {
    final res = await http
        .post(Uri.parse('$baseUrl$path'), headers: _headers)
        .timeout(const Duration(seconds: 30));

    if (res.statusCode != 200) {
      throw Exception('Request failed (HTTP ${res.statusCode}) for $path');
    }
    return jsonDecode(res.body);
  }

  // Laravel wraps paginated lists in a "data" field.
  Future<List<Invoice>> getInvoices() async {
    final body = await _get('/invoices');
    return (body['data'] as List)
        .map((j) => Invoice.fromJson(j as Map<String, dynamic>))
        .toList();
  }

  Future<List<Transaction>> getTransactions() async {
    final body = await _get('/transactions');
    return (body['data'] as List)
        .map((j) => Transaction.fromJson(j as Map<String, dynamic>))
        .toList();
  }

  Future<int> getInvoiceTotal() async {
    final body = await _get('/invoices');
    return (body['total'] as num).toInt();
  }

  Future<int> getTransactionTotal() async {
    final body = await _get('/transactions');
    return (body['total'] as num).toInt();
  }

  // Latest reconciliation run summary, or null if none has been run yet.
  Future<Map<String, dynamic>?> getLatestRun() async {
    final body = await _get('/reconciliation/runs');
    final list = body['data'] as List;
    return list.isEmpty ? null : list.first as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> runReconciliation() async {
    return await _post('/reconciliation/run') as Map<String, dynamic>;
  }

  Future<List<Reconciliation>> getReconciliations() async {
    final body = await _get('/reconciliation');
    return (body as List)
        .map((j) => Reconciliation.fromJson(j as Map<String, dynamic>))
        .toList();
  }

  // Returns the server's message. Throws with the server's reason on a 422.
  Future<String> approve(int reconciliationId) async {
    final res = await http
        .post(Uri.parse('$baseUrl/reconciliation/$reconciliationId/approve'), headers: _headers)
        .timeout(const Duration(seconds: 15));

    final body = jsonDecode(res.body);
    if (res.statusCode != 200) {
      throw Exception(body['message'] ?? 'Approve failed (HTTP ${res.statusCode})');
    }
    return body['message'] as String;
  }

  // AI summary of the engine's decision. Falls back to the template text on the server.
  Future<Map<String, dynamic>> explain(int reconciliationId) async {
    final res = await http
        .post(Uri.parse('$baseUrl/reconciliation/$reconciliationId/explain'), headers: _headers)
        .timeout(const Duration(seconds: 40));

    final body = jsonDecode(res.body);
    if (res.statusCode != 200) {
      throw Exception(body['message'] ?? 'Explain failed (HTTP ${res.statusCode})');
    }
    return body as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> createPaypalOrder(int invoiceId) async {
    final res = await http
        .post(Uri.parse('$baseUrl/invoices/$invoiceId/paypal/order'), headers: _headers)
        .timeout(const Duration(seconds: 30));
    final body = jsonDecode(res.body);
    if (res.statusCode != 201) {
      throw Exception(body['message'] ?? 'Could not create order');
    }
    return body as Map<String, dynamic>;
  }

  Future<String> capturePaypalOrder(String orderId) async {
    final res = await http
        .post(Uri.parse('$baseUrl/paypal/orders/$orderId/capture'), headers: _headers)
        .timeout(const Duration(seconds: 30));
    final body = jsonDecode(res.body);
    if (res.statusCode != 200) {
      throw Exception(body['message'] ?? 'Capture failed');
    }
    return body['message'] as String;
  }
}