class AppNotification {
  AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.type,
    required this.readAt,
    required this.createdAt,
  });

  final String id;
  final String title;
  final String body;
  final String type;
  final String? readAt;
  final String? createdAt;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>? ?? {};
    return AppNotification(
      id: (json['id'] as String?) ?? '',
      title: (data['title'] as String?) ?? '-',
      body: (data['body'] as String?) ?? '-',
      type: (data['type'] as String?) ?? '',
      readAt: json['read_at'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}
