
class Invoice {
  final int id;
  final String invoiceNumber;
  final String vendorName;
  final String? referenceId;
  final int amountCents;
  final String currency;
  final String status;
  final String amount;

  Invoice({
    required this.id,
    required this.invoiceNumber,
    required this.vendorName,
    this.referenceId,
    required this.amountCents,
    required this.currency,
    required this.status,
    required this.amount,
  });

  factory Invoice.fromJson(Map<String, dynamic> json) {
    return Invoice(
      id: (json['id'] as num).toInt(),
      invoiceNumber: json['invoice_number'] as String,
      vendorName: json['vendor_name'] as String,
      referenceId: json['reference_id'] as String?,
      amountCents: (json['amount_cents'] as num).toInt(),
      currency: json['currency'] as String,
      status: json['status'] as String,
      amount: json['amount'].toString(),
    );
  }
}
