import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../widgets/error_view.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late Future<Map<String, dynamic>> _future;
  bool _running = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final api = ApiService();
    final invoices = await api.getInvoiceTotal();
    final transactions = await api.getTransactionTotal();
    final run = await api.getLatestRun();
    return {'invoices': invoices, 'transactions': transactions, 'run': run};
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<void> _runReconciliation() async {
    setState(() => _running = true);
    try {
      await ApiService().runReconciliation();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Reconciliation completed')));
      _reload();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('Failed: $e')));
    } finally {
      if (mounted) setState(() => _running = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return ErrorView(error: snap.error!, onRetry: _reload);
        }
        final d = snap.data!;
        final run = d['run'] as Map<String, dynamic>?;

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                Expanded(child: _StatCard('Invoices', '${d['invoices']}', Icons.receipt_long)),
                const SizedBox(width: 12),
                Expanded(child: _StatCard('Transactions', '${d['transactions']}', Icons.payments)),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _StatCard('Matched', run == null ? '-' : '${run['matched']}',
                      Icons.check_circle, color: Colors.green),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _StatCard('Needs review', run == null ? '-' : '${run['needs_review']}',
                      Icons.warning_amber, color: Colors.orange),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              run == null
                  ? 'No reconciliation has been run yet.'
                  : 'Latest run #${run['id']}: ${run['invoices_checked']} invoices checked',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: _running ? null : _runReconciliation,
              icon: _running
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.sync),
              label: Text(_running ? 'Running...' : 'Run reconciliation'),
            ),
          ],
        );
      },
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color? color;
  const _StatCard(this.label, this.value, this.icon, {this.color});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: color),
            const SizedBox(height: 8),
            Text(value, style: Theme.of(context).textTheme.headlineMedium),
            Text(label),
          ],
        ),
      ),
    );
  }
}