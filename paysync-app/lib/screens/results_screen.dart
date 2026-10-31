import 'package:flutter/material.dart';
import '../models/reconciliation.dart';
import '../services/api_service.dart';
import '../widgets/error_view.dart';
import '../widgets/status_chip.dart';
import 'result_detail_screen.dart';

String _money(int cents) =>
    '${cents ~/ 100}.${(cents % 100).toString().padLeft(2, '0')}';

class ResultsScreen extends StatefulWidget {
  const ResultsScreen({super.key});

  @override
  State<ResultsScreen> createState() => _ResultsScreenState();
}

class _ResultsScreenState extends State<ResultsScreen> {
  late Future<Map<String, dynamic>> _future;
  String _filter = 'all';

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final api = ApiService();
    final items = await api.getReconciliations();
    final run = await api.getLatestRun();
    final unmatched = (run?['unmatched_payments'] as List?) ?? [];
    return {'items': items, 'unmatched': unmatched};
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
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

        final all = snap.data!['items'] as List<Reconciliation>;
        final unmatched = snap.data!['unmatched'] as List;

        if (all.isEmpty) {
          return const Center(
              child: Text('No results yet. Run reconciliation from the Dashboard.'));
        }

        final shown = all.where((r) {
          if (_filter == 'matched') return r.isMatched;
          if (_filter == 'review') return !r.isMatched;
          return true;
        }).toList();

        return ListView(
          children: [
            Padding(
              padding: const EdgeInsets.all(12),
              child: SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'all', label: Text('All')),
                  ButtonSegment(value: 'matched', label: Text('Matched')),
                  ButtonSegment(value: 'review', label: Text('Needs review')),
                ],
                selected: {_filter},
                onSelectionChanged: (s) => setState(() => _filter = s.first),
              ),
            ),
            for (final r in shown)
              ListTile(
                title: Text(r.invoiceNumber),
                subtitle: Text(r.explanation, maxLines: 2, overflow: TextOverflow.ellipsis),
                isThreeLine: true,
                trailing: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    StatusChip(r.status),
                    const SizedBox(height: 4),
                    Text('${r.confidenceLabel} (${r.confidence})',
                        style: Theme.of(context).textTheme.bodySmall),
                  ],
                ),
                onTap: () async {
                  final changed = await Navigator.push<bool>(
                    context,
                    MaterialPageRoute(builder: (_) => ResultDetailScreen(result: r)),
                  );
                  if (changed == true) _reload();
                },
              ),
            const Divider(height: 32),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Text('Unmatched payments (${unmatched.length})',
                  style: Theme.of(context).textTheme.titleMedium),
            ),
            if (unmatched.isEmpty)
              const Padding(
                padding: EdgeInsets.all(16),
                child: Text('Every completed payment is linked to an invoice.'),
              ),
            for (final u in unmatched)
              ListTile(
                title: Text(u['external_id'].toString()),
                subtitle: Text(u['explanation'].toString(), maxLines: 2),
                trailing: Text(
                    '${_money((u['amount_cents'] as num).toInt())} ${u['currency']}'),
              ),
            const SizedBox(height: 24),
          ],
        );
      },
    );
  }
}