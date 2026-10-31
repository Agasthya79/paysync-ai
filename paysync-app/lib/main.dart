import 'package:flutter/material.dart';
import 'screens/dashboard_screen.dart';
import 'screens/invoices_screen.dart';
import 'screens/results_screen.dart';
import 'screens/transactions_screen.dart';

void main() => runApp(const PaySyncApp());

class PaySyncApp extends StatelessWidget {
  const PaySyncApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'PaySync AI',
      theme: ThemeData(colorSchemeSeed: Colors.indigo, useMaterial3: true),
      home: const HomeShell(),
    );
  }
}

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  static const _titles = ['Dashboard', 'Invoices', 'Transactions', 'Results'];

  @override
  Widget build(BuildContext context) {
    // Screens are rebuilt on tab change, so each tab loads fresh data.
    final pages = <Widget>[
      const DashboardScreen(),
      const InvoicesScreen(),
      const TransactionsScreen(),
      const ResultsScreen(),
    ];

    return Scaffold(
      appBar: AppBar(title: Text('PaySync AI · ${_titles[_index]}')),
      body: pages[_index],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard), label: 'Dashboard'),
          NavigationDestination(icon: Icon(Icons.receipt_long), label: 'Invoices'),
          NavigationDestination(icon: Icon(Icons.payments), label: 'Transactions'),
          NavigationDestination(icon: Icon(Icons.fact_check), label: 'Results'),
        ],
      ),
    );
  }
}