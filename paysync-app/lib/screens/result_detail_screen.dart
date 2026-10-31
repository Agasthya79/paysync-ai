import 'package:flutter/material.dart';
import '../models/reconciliation.dart';
import '../services/api_service.dart';
import '../widgets/status_chip.dart';

class ResultDetailScreen extends StatefulWidget {
  final Reconciliation result;
  const ResultDetailScreen({super.key, required this.result});

  @override
  State<ResultDetailScreen> createState() => _ResultDetailScreenState();
}

class _ResultDetailScreenState extends State<ResultDetailScreen> {
  bool _busy = false;

  Reconciliation get r => widget.result;

  Future<void> _approve() async {
    setState(() => _busy = true);
    try {
      final msg = await ApiService().approve(r.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))));
      setState(() => _busy = false);
    }
  }

  Widget _row(String label, Object? value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [Text(label), Text(value?.toString() ?? '-', style: const TextStyle(fontWeight: FontWeight.w600))],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final e = r.evidence;
    final canApprove = r.isMatched && r.invoiceStatus != 'paid';

    return Scaffold(
      appBar: AppBar(title: Text(r.invoiceNumber)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              StatusChip(r.status),
              const SizedBox(width: 12),
              Text('Confidence: ${r.confidenceLabel} (${r.confidence})'),
            ],
          ),
          const SizedBox(height: 4),
          Text('Rule-based score, not a probability.',
              style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Why this result', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  Text(r.explanation),
                ],
              ),
            ),
          ),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Evidence', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  _row('Reference', e?['invoice_reference']),
                  _row('Currency', e?['currency']),
                  _row('Invoice amount', e?['invoice_amount']),
                  _row('Payments total', e?['payments_total']),
                  _row('Difference', e?['difference']),
                  _row('Payments linked', e?['payment_count']),
                  if (r.paymentIds.isNotEmpty) _row('Payment IDs', r.paymentIds.join(', ')),
                ],
              ),
            ),
          ),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Recommended action', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  Text(r.nextAction ?? 'Review manually.'),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (canApprove)
            FilledButton.icon(
              onPressed: _busy ? null : _approve,
              icon: const Icon(Icons.check),
              label: Text(_busy ? 'Approving...' : 'Approve and mark invoice as paid'),
            )
          else
            Text(
              r.invoiceStatus == 'paid'
                  ? 'This invoice is already marked as paid.'
                  : 'Only matched results can be approved. This one needs manual review.',
              textAlign: TextAlign.center,
            ),
        ],
      ),
    );
  }
}