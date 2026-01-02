import '../models/personil.dart';

bool isCamatOrSekcam(Personil? personil) {
  final jabatan = personil?.jabatan?.toLowerCase() ?? '';
  if (jabatan.isEmpty) {
    return false;
  }

  return jabatan.contains('camat') ||
      jabatan.contains('sekretaris kecamatan') ||
      jabatan.contains('sekcam');
}
