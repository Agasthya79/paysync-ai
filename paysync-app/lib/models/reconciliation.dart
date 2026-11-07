class Reconciliation {
  final int id;
  final int invoiceId;
  final String invoiceNumber;
  final String invoiceStatus;
  final String status;
  final int confidence;
  final String explanation;
  final String? nextAction;
  final String? aiExplanation;
  final Map<String, dynamic>? evidence;
  final List<String> paymentIds; // external IDs of the linked payments

  Reconciliation({
    required this.id,
    required this.invoiceId,
    required this.invoiceNumber,
    required this.invoiceStatus,
    required this.status,
    required this.confidence,
    required this.explanation,
    this.nextAction,
    this.aiExplanation,
    this.evidence,
    required this.paymentIds,
  });

  bool get isMatched => status == 'matched' || status == 'split_matched';

  // A rule-based score, not a probability, so we show a label.
  String get confidenceLabel {
    if (confidence >= 90) return 'High';
    if (confidence >= 50) return 'Medium';
    if (confidence > 0) return 'Low';
    return 'None';
  }

  factory Reconciliation.fromJson(Map<String, dynamic> json) {
    final invoice = json['invoice'] as Map<String, dynamic>?;
    final many = (json['transactions'] as List?) ?? [];
    final single = json['transaction'] as Map<String, dynamic>?;

    final ids = many.isNotEmpty
        ? many.map((t) => (t as Map<String, dynamic>)['external_id'].toString()).toList()
        : (single != null ? [single['external_id'].toString()] : <String>[]);

    return Reconciliation(
      id: (json['id'] as num).toInt(),
      invoiceId: (json['invoice_id'] as num).toInt(),
      invoiceNumber: invoice?['invoice_number']?.toString() ?? '-',
      invoiceStatus: invoice?['status']?.toString() ?? '',
      status: json['status'] as String,
      confidence: (json['confidence'] as num).toInt(),
      explanation: json['explanation'].toString(),
      nextAction: json['next_action']?.toString(),
      aiExplanation: json['ai_explanation']?.toString(),
      evidence: json['evidence'] as Map<String, dynamic>?,
      paymentIds: ids,
    );
  }
}