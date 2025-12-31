class WaInboxMessage {
  WaInboxMessage({
    required this.id,
    required this.senderNumber,
    required this.senderName,
    required this.message,
    required this.receivedAt,
    required this.status,
    required this.replyMessage,
  });

  final int id;
  final String? senderNumber;
  final String? senderName;
  final String message;
  final String? receivedAt;
  final String status;
  final String? replyMessage;

  factory WaInboxMessage.fromJson(Map<String, dynamic> json) {
    return WaInboxMessage(
      id: (json['id'] as num?)?.toInt() ?? 0,
      senderNumber: json['sender_number'] as String?,
      senderName: json['sender_name'] as String?,
      message: (json['message'] as String?) ?? '',
      receivedAt: json['received_at'] as String?,
      status: (json['status'] as String?) ?? '',
      replyMessage: json['reply_message'] as String?,
    );
  }
}
