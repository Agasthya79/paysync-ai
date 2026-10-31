import 'package:flutter/material.dart';
import '../models/invoice.dart';
import '../services/api_service.dart';
import '../widgets/error_view.dart';
import '../widgets/status_chip.dart';

class InvoicesScreen extends StatefulWidget {
  const InvoicesScreen({super.key});

  @override
  State<InvoicesScreen> createState() => _InvoicesScreenState();
}

class _InvoicesScreenState extends State<InvoicesScreen> {
  late Future<List<Invoice>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService().getInvoices();
  }

  void _reload() {
    setState(() {
      _future = ApiService().getInvoices();
    });
  }

  Future<void> _pay(Invoice inv) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Pay ${inv.invoiceNumber}'),
        content: Text(
            'Pay ${inv.amount} ${inv.currency} with PayPal?\n\n'
            'Demo runs in mock mode: the server simulates PayPal, and verifies '
            'the amount and currency against the invoice.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Pay with PayPal')),
        ],
      ),
    );
    if (ok != true) return;

    try {
      final api = ApiService();
      final order = await api.createPaypalOrder(inv.id);
      final msg = await api.capturePaypalOrder(order['order_id'] as String);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$msg Run reconciliation to match it.')));
      _reload();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))));
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<Invoice>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return ErrorView(error: snap.error!, onRetry: _reload);
        }
        final invoices = snap.data!;
        if (invoices.isEmpty) {
          return const Center(child: Text('No invoices yet.'));
        }
        return RefreshIndicator(
          onRefresh: () async {
            _reload();
            try {
              await _future;
            } catch (_) {}
          },
          child: ListView.separated(
            itemCount: invoices.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final inv = invoices[i];
              final payable = inv.status != 'paid' && inv.referenceId != null;
              return ListTile(
                title: Text(inv.invoiceNumber),
                subtitle: Text(inv.referenceId != null
                    ? 'Ref: ${inv.referenceId}'
                    : inv.vendorName),
                onTap: payable ? () => _pay(inv) : null,
                trailing: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text('${inv.amount} ${inv.currency}',
                        style: const TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 4),
                    StatusChip(inv.status),
                  ],
                ),
              );
            },
          ),
        );
      },
    );
  }
}