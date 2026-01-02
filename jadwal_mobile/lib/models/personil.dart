class Personil {
  Personil({
    required this.id,
    required this.nama,
    required this.jabatan,
    required this.role,
    required this.noWa,
  });

  final int id;
  final String nama;
  final String? jabatan;
  final String? role;
  final String? noWa;

  factory Personil.fromJson(Map<String, dynamic> json) {
    return Personil(
      id: (json['id'] as num?)?.toInt() ?? 0,
      nama: (json['nama'] as String?) ?? '-',
      jabatan: json['jabatan'] as String?,
      role: json['role'] as String?,
      noWa: json['no_wa'] as String?,
    );
  }
}
