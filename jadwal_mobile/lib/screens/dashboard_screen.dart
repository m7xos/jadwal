import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../controllers/agenda_controller.dart';
import '../controllers/notifications_controller.dart';
import '../widgets/section_header.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      context.read<NotificationsController>().fetchUnreadCount();
      context.read<AgendaController>().fetchAgenda(pendingOnly: true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final notifications = context.watch<NotificationsController>();
    final agenda = context.watch<AgendaController>();

    return RefreshIndicator(
      onRefresh: () async {
        await notifications.fetchUnreadCount();
        await agenda.fetchAgenda(pendingOnly: true);
      },
      child: ListView(
        padding: const EdgeInsets.only(bottom: 24),
        children: [
          Container(
            margin: const EdgeInsets.all(20),
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [
                  Color(0xFFD31E28),
                  Color(0xFFB0101C),
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Ringkasan Hari Ini',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: Colors.white70,
                      ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Pantau agenda, layanan, dan pesan masuk tanpa membuka web.',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 16),
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: [
                    _StatChip(
                      title: 'Notif Baru',
                      value: notifications.unreadCount.toString(),
                      icon: Icons.notifications_active_outlined,
                    ),
                    _StatChip(
                      title: 'Menunggu Disposisi',
                      value: agenda.items.length.toString(),
                      icon: Icons.timelapse_outlined,
                    ),
                  ],
                ),
              ],
            ),
          ),
          SectionHeader(
            title: 'Agenda Menunggu Disposisi',
            trailing: TextButton(
              onPressed: () {
                context.read<AgendaController>().fetchAgenda(pendingOnly: true);
              },
              child: const Text('Refresh'),
            ),
          ),
          if (agenda.isLoading)
            const Center(child: CircularProgressIndicator())
          else if (agenda.items.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 20),
              child: Text('Tidak ada agenda yang menunggu disposisi.'),
            )
          else
            ...agenda.items.take(3).map(
                  (item) => ListTile(
                    title: Text(item.nama),
                    subtitle: Text(item.tanggal ?? '-'),
                    leading: const Icon(Icons.event_note),
                  ),
                ),
        ],
      ),
    );
  }
}

class _StatChip extends StatelessWidget {
  const _StatChip({
    required this.title,
    required this.value,
    required this.icon,
  });

  final String title;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.15),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white24),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: Colors.white, size: 18),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(color: Colors.white70, fontSize: 12),
              ),
              Text(
                value,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
