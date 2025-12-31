import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../controllers/agenda_controller.dart';
import '../models/kegiatan.dart';

class AgendaScreen extends StatefulWidget {
  const AgendaScreen({super.key});

  @override
  State<AgendaScreen> createState() => _AgendaScreenState();
}

class _AgendaScreenState extends State<AgendaScreen> {
  bool _pendingOnly = false;

  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      context.read<AgendaController>().fetchAgenda();
    });
  }

  Future<void> _refresh() {
    return context
        .read<AgendaController>()
        .fetchAgenda(pendingOnly: _pendingOnly);
  }

  @override
  Widget build(BuildContext context) {
    final agenda = context.watch<AgendaController>();

    return RefreshIndicator(
      onRefresh: _refresh,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Tampilkan hanya belum disposisi',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ),
              Switch(
                value: _pendingOnly,
                onChanged: (value) {
                  setState(() => _pendingOnly = value);
                  _refresh();
                },
              ),
            ],
          ),
          const SizedBox(height: 12),
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
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
      ),
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
          Text(item.tanggal ?? '-'),
          const SizedBox(height: 6),
          if (item.tempat != null)
            Text('Tempat: ${item.tempat}'),
          const SizedBox(height: 10),
          Row(
            children: [
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: statusColor.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  item.sudahDisposisi ? 'Sudah disposisi' : 'Menunggu disposisi',
                  style: TextStyle(
                    color: statusColor,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              if (item.jenisSurat != null) ...[
                const SizedBox(width: 8),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.blueGrey.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    item.jenisSurat == 'tindak_lanjut'
                        ? 'Tindak lanjut'
                        : 'Undangan',
                    style: const TextStyle(
                      color: Colors.black87,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
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
    );
  }
}
