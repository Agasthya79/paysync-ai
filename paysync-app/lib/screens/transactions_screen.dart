import 'package:flutter/material.dart';
import '../models/transaction.dart';
import '../services/api_service.dart';
import '../widgets/error_view.dart';
import '../widgets/status_chip.dart';

class TransactionsScreen extends StatefulWidget {
  const TransactionsScreen({super.key});

  @override
  State<TransactionsScreen> createState() => _TransactionsScreenState();
}

class _TransactionsScreenState extends State<TransactionsScreen> {
  late Future<List<Transaction>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService().getTransactions();
  }

  void _reload() {
    setState(() {
      _future = ApiService().getTransactions();
    });
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<Transaction>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return ErrorView(error: snap.error!, onRetry: _reload);
        }
        final items = snap.data!;
        if (items.isEmpty) {
          return const Center(child: Text('No transactions yet.'));
        }
        return RefreshIndicator(
          onRefresh: () async {
            _reload();
            try {
              await _future;
            } catch (_) {}
          },
          child: ListView.separated(
            itemCount: items.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final t = items[i];
              return ListTile(
                title: Text(t.externalId),
                subtitle: Text(
                    '${t.source.toUpperCase()}  ·  ${t.referenceId != null ? 'Ref: ${t.referenceId}' : 'No reference'}'),
                trailing: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text('${t.amount} ${t.currency}',
                        style: const TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 4),
                    StatusChip(t.status),
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