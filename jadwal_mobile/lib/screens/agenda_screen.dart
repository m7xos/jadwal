import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../controllers/agenda_controller.dart';
import '../controllers/auth_controller.dart';
import '../models/kegiatan.dart';
import '../utils/personil_role.dart';
import '../widgets/app_card.dart';

class AgendaScreen extends StatefulWidget {
  const AgendaScreen({super.key});

  @override
  State<AgendaScreen> createState() => _AgendaScreenState();
}

class _AgendaScreenState extends State<AgendaScreen> {
  int _selectedDay = 0;
  int _tomorrowFilterIndex = 0;
  bool _fallbackToAllToday = false;

  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      _loadAgenda();
    });
  }

  DateTime _today() {
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day);
  }

  DateTime _tomorrow() {
    final today = _today();
    return today.add(const Duration(days: 1));
  }

  Future<void> _loadAgenda() async {
    final agenda = context.read<AgendaController>();
    final auth = context.read<AuthController>();
    final isLeader = isCamatOrSekcam(auth.personil);
    final selectedDate = _selectedDay == 0 ? _today() : _tomorrow();

    if (!isLeader) {
      await agenda.fetchAgenda(date: selectedDate);
      _fallbackToAllToday = false;
      if (mounted) {
        setState(() {});
      }
      return;
    }

    if (_selectedDay == 0) {
      await agenda.fetchAgenda(date: selectedDate, belumDisposisi: true);
      if (agenda.items.isEmpty) {
        _fallbackToAllToday = true;
        await agenda.fetchAgenda(date: selectedDate);
      } else {
        _fallbackToAllToday = false;
      }
      if (mounted) {
        setState(() {});
      }
      return;
    }

    if (_tomorrowFilterIndex == 0) {
      await agenda.fetchAgenda(date: selectedDate, belumDisposisi: true);
    } else {
      await agenda.fetchAgenda(date: selectedDate, belumDisposisi: false);
    }
    _fallbackToAllToday = false;
    if (mounted) {
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final agenda = context.watch<AgendaController>();
    final auth = context.watch<AuthController>();
    final isLeader = isCamatOrSekcam(auth.personil);

    return RefreshIndicator(
      onRefresh: _loadAgenda,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          AppCard(
            padding: const EdgeInsets.all(10),
            child: SegmentedButton<int>(
              segments: const [
                ButtonSegment(value: 0, label: Text('Hari ini')),
                ButtonSegment(value: 1, label: Text('Besok')),
              ],
              selected: {_selectedDay},
              onSelectionChanged: (selection) {
                setState(() {
                  _selectedDay = selection.first;
                });
                _loadAgenda();
              },
            ),
          ),
          if (isLeader && _selectedDay == 1) ...[
            const SizedBox(height: 12),
            AppCard(
              padding: const EdgeInsets.all(10),
              child: SegmentedButton<int>(
                segments: const [
                  ButtonSegment(value: 0, label: Text('Belum disposisi')),
                  ButtonSegment(value: 1, label: Text('Sudah disposisi')),
                ],
                selected: {_tomorrowFilterIndex},
                onSelectionChanged: (selection) {
                  setState(() {
                    _tomorrowFilterIndex = selection.first;
                  });
                  _loadAgenda();
                },
              ),
            ),
          ],
          if (isLeader && _selectedDay == 0 && _fallbackToAllToday)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(
                'Tidak ada agenda belum disposisi. Menampilkan semua agenda hari ini.',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.black54,
                    ),
              ),
            ),
          const SizedBox(height: 16),
          if (agenda.isLoading)
            const Center(child: CircularProgressIndicator())
          else if (agenda.items.isEmpty)
            const Text('Belum ada agenda.')
          else
            ...agenda.items.map(_AgendaCard.new),
        ],
      ),
    );
  }
}

class _AgendaCard extends StatelessWidget {
  const _AgendaCard(this.item);

  final Kegiatan item;

  @override
  Widget build(BuildContext context) {
    final statusColor = item.sudahDisposisi
        ? Colors.green.shade600
        : Colors.orange.shade700;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      child: AppCard(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              item.nama,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                Icon(Icons.calendar_today_outlined,
                    size: 14, color: Colors.black54),
                const SizedBox(width: 6),
                Text(
                  item.tanggal ?? '-',
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: Colors.black54),
                ),
                const SizedBox(width: 12),
                if (item.waktu != null) ...[
                  Icon(Icons.schedule_outlined,
                      size: 14, color: Colors.black54),
                  const SizedBox(width: 6),
                  Text(
                    item.waktu ?? '-',
                    style: Theme.of(context)
                        .textTheme
                        .bodySmall
                        ?.copyWith(color: Colors.black54),
                  ),
                ],
              ],
            ),
            if (item.tempat != null) ...[
              const SizedBox(height: 6),
              Text(
                'Tempat: ${item.tempat}',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.black54,
                    ),
              ),
            ],
            const SizedBox(height: 12),
            Row(
              children: [
                _Tag(
                  label: item.sudahDisposisi
                      ? 'Sudah disposisi'
                      : 'Menunggu disposisi',
                  color: statusColor,
                ),
                if (item.jenisSurat != null) ...[
                  const SizedBox(width: 8),
                  _Tag(
                    label: item.jenisSurat == 'tindak_lanjut'
                        ? 'Tindak lanjut'
                        : 'Undangan',
                    color: Colors.blueGrey,
                  ),
                ],
                const Spacer(),
                if (item.suratViewUrl != null)
                  TextButton(
                    onPressed: () async {
                      final url = item.suratViewUrl ?? item.suratPreviewUrl;
                      if (url == null) {
                        return;
                      }
                      final success = await launchUrl(
                        Uri.parse(url),
                        mode: LaunchMode.externalApplication,
                      );
                      if (!success && context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                            content: Text('Gagal membuka tautan surat.'),
                          ),
                        );
                      }
                    },
                    child: const Text('Buka Surat'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Tag extends StatelessWidget {
  const _Tag({
    required this.label,
    required this.color,
  });

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
