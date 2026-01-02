import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../controllers/agenda_controller.dart';
import '../controllers/auth_controller.dart';
import '../controllers/notifications_controller.dart';
import '../utils/personil_role.dart';
import '../widgets/app_card.dart';
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
      final auth = context.read<AuthController>();
      final isLeader = isCamatOrSekcam(auth.personil);
      final today = DateTime.now();

      context.read<AgendaController>().fetchAgenda(
            date: today,
            belumDisposisi: isLeader ? true : false,
          );
    });
  }

  @override
  Widget build(BuildContext context) {
    final notifications = context.watch<NotificationsController>();
    final agenda = context.watch<AgendaController>();
    final auth = context.watch<AuthController>();
    final isLeader = isCamatOrSekcam(auth.personil);
    final personilId = auth.personil?.id ?? 0;
    final filteredAgenda = isLeader
        ? agenda.items.where((item) => !item.sudahDisposisi).toList()
        : agenda.items
            .where((item) =>
                item.sudahDisposisi && item.personilIds.contains(personilId))
            .toList();

    return RefreshIndicator(
      onRefresh: () async {
        await notifications.fetchUnreadCount();
        final today = DateTime.now();
        await agenda.fetchAgenda(
          date: today,
          belumDisposisi: isLeader ? false : true,
        );
      },
      child: ListView(
        padding: const EdgeInsets.only(bottom: 24),
        children: [
          Padding(
            padding: const EdgeInsets.all(20),
            child: AppCard(
              padding: const EdgeInsets.all(20),
              child: Stack(
                children: [
                  Positioned(
                    right: -20,
                    top: -30,
                    child: Container(
                      width: 140,
                      height: 140,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Theme.of(context)
                            .colorScheme
                            .primary
                            .withOpacity(0.08),
                      ),
                    ),
                  ),
                  Positioned(
                    left: -30,
                    bottom: -40,
                    child: Container(
                      width: 160,
                      height: 160,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Theme.of(context)
                            .colorScheme
                            .secondary
                            .withOpacity(0.08),
                      ),
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Ringkasan Hari Ini',
                        style:
                            Theme.of(context).textTheme.titleSmall?.copyWith(
                                  color: Colors.black54,
                                ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Pantau agenda dan notifikasi tanpa membuka web.',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                      ),
                      const SizedBox(height: 18),
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
                            title: isLeader
                                ? 'Belum Disposisi'
                                : 'Agenda Disposisi',
                            value: filteredAgenda.length.toString(),
                            icon: Icons.timelapse_outlined,
                          ),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          SectionHeader(
            title: isLeader
                ? 'Agenda Belum Disposisi'
                : 'Agenda Sudah Disposisi (Anda)',
            trailing: TextButton(
              onPressed: () {
                final today = DateTime.now();
                context.read<AgendaController>().fetchAgenda(
                      date: today,
                      belumDisposisi: isLeader ? true : false,
                    );
              },
              child: const Text('Refresh'),
            ),
          ),
          if (agenda.isLoading)
            const Center(child: CircularProgressIndicator())
          else if (filteredAgenda.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 20),
              child: Text('Belum ada agenda untuk ditampilkan.'),
            )
          else
            ...filteredAgenda.take(3).map(
                  (item) => Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 20,
                      vertical: 6,
                    ),
                    child: AppCard(
                      padding: const EdgeInsets.all(14),
                      child: Row(
                        children: [
                          Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              color: Theme.of(context)
                                  .colorScheme
                                  .primary
                                  .withOpacity(0.12),
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: const Icon(Icons.event_note_outlined),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.nama,
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleMedium
                                      ?.copyWith(fontWeight: FontWeight.w700),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  item.tanggal ?? '-',
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodySmall
                                      ?.copyWith(color: Colors.black54),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
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
        color: Theme.of(context).colorScheme.surfaceVariant,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Theme.of(context).colorScheme.outline.withOpacity(0.6),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: Theme.of(context).colorScheme.primary, size: 18),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(color: Colors.black54, fontSize: 12),
              ),
              Text(
                value,
                style: const TextStyle(
                  color: Colors.black87,
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
