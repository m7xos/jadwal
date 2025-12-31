class Kegiatan {
  Kegiatan({
    required this.id,
    required this.nama,
    required this.tanggal,
    required this.waktu,
    required this.tempat,
    required this.keterangan,
    required this.jenisSurat,
    required this.sudahDisposisi,
    required this.suratPreviewUrl,
    required this.suratViewUrl,
  });

  final int id;
  final String nama;
  final String? tanggal;
  final String? waktu;
  final String? tempat;
  final String? keterangan;
  final String? jenisSurat;
  final bool sudahDisposisi;
  final String? suratPreviewUrl;
  final String? suratViewUrl;

  factory Kegiatan.fromJson(Map<String, dynamic> json) {
    return Kegiatan(
      id: (json['id'] as num?)?.toInt() ?? 0,
      nama: (json['nama_kegiatan'] as String?) ?? '-',
      tanggal: json['tanggal'] as String?,
      waktu: json['waktu'] as String?,
      tempat: json['tempat'] as String?,
      keterangan: json['keterangan'] as String?,
      jenisSurat: json['jenis_surat'] as String?,
      sudahDisposisi: (json['sudah_disposisi'] as bool?) ?? false,
      suratPreviewUrl: json['surat_preview_url'] as String?,
      suratViewUrl: json['surat_view_url'] as String?,
    );
  }
}
