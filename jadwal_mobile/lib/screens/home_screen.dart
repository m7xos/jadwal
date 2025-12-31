import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../controllers/auth_controller.dart';
import '../controllers/notifications_controller.dart';
import 'agenda_screen.dart';
import 'dashboard_screen.dart';
import 'layanan_screen.dart';
import 'notifications_screen.dart';
import 'wa_inbox_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _index = 0;

  final _pages = const [
    DashboardScreen(),
    AgendaScreen(),
    LayananScreen(),
    WaInboxScreen(),
  ];

  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      context.read<NotificationsController>().fetchUnreadCount();
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    final notifications = context.watch<NotificationsController>();

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Jadwal Watumalang'),
            if (auth.personil?.jabatan != null)
              Text(
                auth.personil?.jabatan ?? '',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.black54,
                    ),
              ),
          ],
        ),
        actions: [
          IconButton(
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => const NotificationsScreen(),
                ),
              );
              if (mounted) {
                context.read<NotificationsController>().fetchUnreadCount();
              }
            },
            icon: Stack(
              children: [
                const Icon(Icons.notifications_outlined),
                if (notifications.unreadCount > 0)
                  Positioned(
                    right: 0,
                    top: 0,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.error,
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        notifications.unreadCount.toString(),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 10,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          PopupMenuButton<String>(
            onSelected: (value) async {
              if (value == 'logout') {
                await auth.logout();
              }
            },
            itemBuilder: (context) => [
              PopupMenuItem(
                value: 'profile',
                enabled: false,
                child: Text(
                  auth.personil?.nama ?? 'Personil',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
              const PopupMenuItem(
                value: 'logout',
                child: Text('Keluar'),
              ),
            ],
          ),
        ],
      ),
      body: IndexedStack(
        index: _index,
        children: _pages,
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (value) {
          setState(() {
            _index = value;
          });
        },
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            label: 'Beranda',
          ),
          NavigationDestination(
            icon: Icon(Icons.event_note_outlined),
            label: 'Agenda',
          ),
          NavigationDestination(
            icon: Icon(Icons.receipt_long_outlined),
            label: 'Layanan',
          ),
          NavigationDestination(
            icon: Icon(Icons.forum_outlined),
            label: 'Inbox',
          ),
        ],
      ),
    );
  }
}
