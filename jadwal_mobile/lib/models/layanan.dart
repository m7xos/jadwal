class Layanan {
  Layanan({
    required this.id,
    required this.nama,
    required this.kategori,
    required this.deskripsi,
    required this.aktif,
  });

  final int id;
  final String nama;
  final String? kategori;
  final String? deskripsi;
  final bool aktif;

  factory Layanan.fromJson(Map<String, dynamic> json) {
    return Layanan(
      id: (json['id'] as num?)?.toInt() ?? 0,
      nama: (json['nama'] as String?) ?? '-',
      kategori: json['kategori'] as String?,
      deskripsi: json['deskripsi'] as String?,
      aktif: (json['aktif'] as bool?) ?? false,
    );
  }
}
