import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/invoice.dart';
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
}