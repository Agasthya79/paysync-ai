import 'package:flutter/material.dart';
import 'services/api_service.dart';
import 'models/invoice.dart';

void main() => runApp(const PaySyncApp());

class PaySyncApp extends StatelessWidget {
  const PaySyncApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'PaySync AI',
      theme: ThemeData(colorSchemeSeed: Colors.indigo, useMaterial3: true),
      home: const InvoiceTestScreen(),
    );
  }
}

class InvoiceTestScreen extends StatelessWidget {
  const InvoiceTestScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('PaySync AI')),
      body: FutureBuilder<List<Invoice>>(
        future: ApiService().getInvoices(),
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Center(child: Text('Error: ${snap.error}'));
          }
          final invoices = snap.data!;
          return ListView.builder(
            itemCount: invoices.length,
            itemBuilder: (_, i) => ListTile(
              title: Text(invoices[i].invoiceNumber),
              subtitle: Text(invoices[i].vendorName),
              trailing: Text('${invoices[i].amount} ${invoices[i].currency}'),
            ),
          );
        },
      ),
    );
  }
}