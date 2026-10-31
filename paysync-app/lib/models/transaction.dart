class Transaction {
  final int id;
  final String externalId;
  final String? referenceId;
  final int amountCents;
  final String amount; // display value like "1500.00"
  final String currency;
  final String source;
  final String status;
  final DateTime? paidAt;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  Transaction({
    required this.id,
    required this.externalId,
    this.referenceId,
    required this.amountCents,
    required this.amount,
    required this.currency,
    required this.source,
    required this.status,
    this.paidAt,
    this.createdAt,
    this.updatedAt,
  });

  static DateTime? _date(dynamic value) =>
      value == null ? null : DateTime.tryParse(value.toString());

  factory Transaction.fromJson(Map<String, dynamic> json) {
    return Transaction(
      id: (json['id'] as num).toInt(),
      externalId: json['external_id'].toString(),
      referenceId: json['reference_id']?.toString(),
      amountCents: (json['amount_cents'] as num).toInt(),
      amount: json['amount'].toString(),
      currency: json['currency'] as String,
      source: json['source'] as String,
      status: json['status'] as String,
      paidAt: _date(json['paid_at']),
      createdAt: _date(json['created_at']),
      updatedAt: _date(json['updated_at']),
    );
  }
}