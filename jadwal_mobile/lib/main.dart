import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import 'controllers/agenda_controller.dart';
import 'controllers/auth_controller.dart';
import 'controllers/layanan_controller.dart';
import 'controllers/notifications_controller.dart';
import 'controllers/wa_inbox_controller.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'services/api_client.dart';
import 'services/push_notification_service.dart';
import 'services/storage_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

  try {
    await Firebase.initializeApp();
  } catch (_) {
    // Firebase requires google-services.json. App still runs without push.
  }

  runApp(const JadwalApp());
}

class JadwalApp extends StatelessWidget {
  const JadwalApp({super.key});

  @override
  Widget build(BuildContext context) {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: const Color(0xFFD31E28),
      surface: const Color(0xFFF7F4F1),
    );

    return MultiProvider(
      providers: [
        Provider<ApiClient>(create: (_) => ApiClient()),
        Provider<StorageService>(create: (_) => StorageService()),
        Provider<PushNotificationService>(
          create: (_) => PushNotificationService(),
        ),
        ChangeNotifierProvider<AuthController>(
          create: (context) => AuthController(
            apiClient: context.read<ApiClient>(),
            storageService: context.read<StorageService>(),
            pushNotificationService: context.read<PushNotificationService>(),
          )..initialize(),
        ),
        ChangeNotifierProvider<NotificationsController>(
          create: (context) =>
              NotificationsController(context.read<ApiClient>()),
        ),
        ChangeNotifierProvider<AgendaController>(
          create: (context) => AgendaController(context.read<ApiClient>()),
        ),
        ChangeNotifierProvider<LayananController>(
          create: (context) => LayananController(context.read<ApiClient>()),
        ),
        ChangeNotifierProvider<WaInboxController>(
          create: (context) => WaInboxController(context.read<ApiClient>()),
        ),
      ],
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        title: 'Jadwal Watumalang',
        theme: ThemeData(
          colorScheme: colorScheme,
          useMaterial3: true,
          textTheme: GoogleFonts.merriweatherSansTextTheme(
            Theme.of(context).textTheme,
          ),
        ),
        home: Consumer<AuthController>(
          builder: (context, auth, _) {
            if (auth.isLoading) {
              return const Scaffold(
                body: Center(child: CircularProgressIndicator()),
              );
            }
            return auth.isAuthenticated
                ? const HomeScreen()
                : const LoginScreen();
          },
        ),
      ),
    );
  }
}
