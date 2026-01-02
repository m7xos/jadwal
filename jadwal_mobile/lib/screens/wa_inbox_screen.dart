import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../controllers/wa_inbox_controller.dart';
import '../models/wa_inbox_message.dart';
import '../widgets/app_card.dart';

class WaInboxScreen extends StatefulWidget {
  const WaInboxScreen({super.key});

  @override
  State<WaInboxScreen> createState() => _WaInboxScreenState();
}

class _WaInboxScreenState extends State<WaInboxScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      context.read<WaInboxController>().fetchMessages();
    });
  }

  @override
  Widget build(BuildContext context) {
    final inbox = context.watch<WaInboxController>();

    return RefreshIndicator(
      onRefresh: () => inbox.fetchMessages(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (inbox.isLoading)
            const Center(child: CircularProgressIndicator())
          else if (inbox.items.isEmpty)
            const Text('Tidak ada pesan masuk.')
          else
            ...inbox.items.map((item) => _InboxCard(
                  item: item,
                  onReply: () => _openReply(context, inbox, item),
                )),
        ],
      ),
    );
  }

  Future<void> _openReply(
      BuildContext context, WaInboxController controller, WaInboxMessage item) async {
    final replyController = TextEditingController();

    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).colorScheme.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(
            left: 20,
            right: 20,
            bottom: MediaQuery.of(context).viewInsets.bottom + 20,
            top: 20,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Balas Pesan',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              const SizedBox(height: 8),
              Text(item.message),
              const SizedBox(height: 16),
              TextField(
                controller: replyController,
                maxLines: 4,
                decoration: const InputDecoration(
                  labelText: 'Balasan',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () async {
                  if (replyController.text.trim().isEmpty) {
                    return;
                  }
                  await controller.replyMessage(
                    item.id,
                    replyController.text.trim(),
                  );
                  if (context.mounted) {
                    Navigator.of(context).pop(true);
                  }
                },
                child: const Text('Kirim'),
              ),
            ],
          ),
        );
      },
    );

    replyController.dispose();

    if (result == true && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Balasan terkirim.')),
      );
    }
  }
}

class _InboxCard extends StatelessWidget {
  const _InboxCard({
    required this.item,
    required this.onReply,
  });

  final WaInboxMessage item;
  final VoidCallback onReply;

  @override
  Widget build(BuildContext context) {
    final sender = item.senderName?.isNotEmpty == true
        ? item.senderName
        : item.senderNumber;
    final statusLabel = _statusLabel(item.status);
    final statusColor = _statusColor(item.status);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      child: AppCard(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              sender ?? 'Pengirim',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 6),
            Text(item.message),
            const SizedBox(height: 10),
            Row(
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: statusColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    statusLabel,
                    style: TextStyle(
                      color: statusColor,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  item.receivedAt ?? '-',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Colors.black54,
                      ),
                ),
                const Spacer(),
                TextButton(
                  onPressed: onReply,
                  child: const Text('Balas'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _statusLabel(String status) {
    switch (status) {
      case 'new':
        return 'Baru';
      case 'assigned':
        return 'Diambil';
      case 'replied':
        return 'Dibalas';
      default:
        return status;
    }
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'new':
        return Colors.orange;
      case 'assigned':
        return Colors.blue;
      case 'replied':
        return Colors.green;
      default:
        return Colors.grey;
    }
  }
}
