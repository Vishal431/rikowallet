import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

class ApiFailure implements Exception {
  final String message;
  const ApiFailure(this.message);
  @override
  String toString() => message;
}

class RikoApi {
  static const baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: kIsWeb
        ? 'http://127.0.0.1:8010/api'
        : 'http://10.0.2.2:8010/api',
  );
  final storage = const FlutterSecureStorage();
  String? token;
  Future<void> restore() async {
    token = await storage.read(key: 'riko_session');
  }

  Map<String, String> get headers => {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    if (token != null) 'Authorization': 'Bearer $token',
  };
  Future<Map<String, dynamic>> call(
    String path, [
    Map<String, dynamic>? body,
  ]) async {
    try {
      final url = Uri.parse('$baseUrl/$path');
      final response = body == null
          ? await http
                .get(url, headers: headers)
                .timeout(const Duration(seconds: 20))
          : await http
                .post(url, headers: headers, body: jsonEncode(body))
                .timeout(const Duration(seconds: 20));
      return decode(response);
    } on ApiFailure {
      rethrow;
    } catch (_) {
      throw const ApiFailure(
        'Could not reach RikoWallet. Check your connection and try again.',
      );
    }
  }

  Map<String, dynamic> decode(http.Response response) {
    Map<String, dynamic> data;
    try {
      data = jsonDecode(response.body) as Map<String, dynamic>;
    } catch (_) {
      throw const ApiFailure('The server could not complete this request.');
    }
    if (response.statusCode >= 400) {
      final errors = data['errors'] as Map<String, dynamic>?;
      throw ApiFailure(
        errors != null
            ? (errors.values.first as List).first.toString()
            : (data['message'] ?? 'Request failed.').toString(),
      );
    }
    return data;
  }

  Future<void> login(String email, String password) async {
    final data = await call('login', {'email': email, 'password': password});
    token = data['token'];
    await storage.write(key: 'riko_session', value: token);
  }

  Future<void> register(Map<String, dynamic> fields) async {
    final data = await call('register', fields);
    token = data['token'];
    await storage.write(key: 'riko_session', value: token);
  }

  Future<void> logout() async {
    try {
      await call('logout', {});
    } finally {
      token = null;
      await storage.delete(key: 'riko_session');
    }
  }

  Future<void> uploadKyc(String name, String filename, List<int> bytes) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('$baseUrl/actions/kyc'),
    );
    request.headers.addAll({
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    });
    request.fields['legal_name'] = name;
    request.files.add(
      http.MultipartFile.fromBytes('document', bytes, filename: filename),
    );
    decode(
      await http.Response.fromStream(
        await request.send().timeout(const Duration(seconds: 40)),
      ),
    );
  }

  static String key() {
    final random = Random.secure();
    final b = List<int>.generate(16, (_) => random.nextInt(256));
    b[6] = (b[6] & 15) | 64;
    b[8] = (b[8] & 63) | 128;
    final s = b.map((v) => v.toRadixString(16).padLeft(2, '0')).join();
    return '${s.substring(0, 8)}-${s.substring(8, 12)}-${s.substring(12, 16)}-${s.substring(16, 20)}-${s.substring(20)}';
  }
}
