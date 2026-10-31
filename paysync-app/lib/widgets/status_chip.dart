import 'package:flutter/material.dart';

class StatusChip extends StatelessWidget {
  final String status;
  const StatusChip(this.status, {super.key});

  Color get _color {
    switch (status) {
      case 'paid':
      case 'completed':
      case 'matched':
      case 'split_matched':
        return Colors.green;
      case 'pending':
      case 'partially_paid':
      case 'underpaid':
        return Colors.orange;
      case 'disputed':
      case 'failed':
      case 'overpaid':
      case 'missing_payment':
      case 'duplicate_candidates':
        return Colors.red;
      case 'refunded':
        return Colors.purple;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: _color.withOpacity(0.12),
        border: Border.all(color: _color),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        status.replaceAll('_', ' '),
        style: TextStyle(color: _color, fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }
}