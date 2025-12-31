import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../controllers/layanan_controller.dart';
import '../models/layanan.dart';

class LayananScreen extends StatefulWidget {
  const LayananScreen({super.key});

  @override
  State<LayananScreen> createState() => _LayananScreenState();
}

class _LayananScreenState extends State<LayananScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      context.read<LayananController>().fetchLayanan();
    });
  }

  @override
  Widget build(BuildContext context) {
    final layanan = context.watch<LayananController>();

    return RefreshIndicator(
      onRefresh: () => layanan.fetchLayanan(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Layanan Publik Aktif',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
              FilledButton.icon(
                onPressed: layanan.items.isEmpty
                    ? null
                    : () => _openRegisterForm(context, layanan),
                icon: const Icon(Icons.add),
                label: const Text('Daftarkan'),
              ),
            ],
          ),
          const SizedBox(height: 16),
          if (layanan.isLoading)
            const Center(child: CircularProgressIndicator())
          else if (layanan.items.isEmpty)
            const Text('Tidak ada layanan aktif.')
          else
            ...layanan.items.map(_LayananCard.new),
        ],
      ),
    );
  }

  Future<void> _openRegisterForm(
      BuildContext context, LayananController controller) async {
    final namaController = TextEditingController();
    final waController = TextEditingController();
    int? selectedId = controller.items.first.id;

    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
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
                'Register Layanan',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<int>(
                value: selectedId,
                decoration: const InputDecoration(
                  labelText: 'Pilih Layanan',
                  border: OutlineInputBorder(),
                ),
                items: controller.items
                    .map(
                      (item) => DropdownMenuItem(
                        value: item.id,
                        child: Text(item.nama),
                      ),
                    )
                    .toList(),
                onChanged: (value) => selectedId = value,
              ),
              const SizedBox(height: 12),
              TextField(
                controller: namaController,
                decoration: const InputDecoration(
                  labelText: 'Nama Pemohon',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: waController,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Nomor WA Pemohon',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () async {
                  if (selectedId == null ||
                      namaController.text.trim().isEmpty ||
                      waController.text.trim().isEmpty) {
                    return;
                  }
                  final result = await controller.createRequest(
                    layananId: selectedId!,
                    namaPemohon: namaController.text.trim(),
                    noWaPemohon: waController.text.trim(),
                  );
                  if (context.mounted) {
                    Navigator.of(context).pop(result);
                  }
                },
                child: const Text('Simpan'),
              ),
            ],
          ),
        );
      },
    );

    namaController.dispose();
    waController.dispose();

    if (result is Map && context.mounted) {
      final kode = result['kode_register'] ?? '-';
      final queue = result['queue_number'] ?? '-';
      final layanan = result['layanan']?['nama'] ?? '-';

      showDialog(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Register Berhasil'),
          content: Text(
            'Kode: $kode\n'
            'Antrian: $queue\n'
            'Layanan: $layanan',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Tutup'),
            ),
          ],
        ),
      );
    }
  }
}

class _LayananCard extends StatelessWidget {
  const _LayananCard(this.item);

  final Layanan item;

  @override
  Widget build(BuildContext context) {
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
          if (item.kategori != null) ...[
            const SizedBox(height: 4),
            Text('Kategori: ${item.kategori}'),
          ],
          if (item.deskripsi != null) ...[
            const SizedBox(height: 8),
            Text(item.deskripsi!),
          ],
        ],
      ),
    );
  }
}
