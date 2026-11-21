import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'constants/theme.dart';
import 'constants/localization.dart';
import 'services/api_service.dart';
import 'services/fcm_service.dart';
import 'screens/splash_screen.dart';
import 'widgets/offline_banner_widget.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await AppTheme.initTheme();
  await AppLocale.initLocale();
  await FcmService.initialize();
  runApp(const AlAsafyApp());
}

class AlAsafyApp extends StatelessWidget {
  const AlAsafyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<ThemeMode>(
      valueListenable: AppTheme.themeModeNotifier,
      builder: (context, currentMode, _) {
        return ValueListenableBuilder<Locale>(
          valueListenable: AppLocale.localeNotifier,
          builder: (context, currentLocale, _) {
            return MaterialApp(
              title: AppLocale.text('app_title'),
              debugShowCheckedModeBanner: false,
              theme: AppTheme.lightTheme,
              darkTheme: AppTheme.darkTheme,
              themeMode: currentMode,
              locale: currentLocale,
              supportedLocales: const [
                Locale('ar', 'SA'),
                Locale('en', 'US'),
              ],
              localizationsDelegates: const [
                GlobalMaterialLocalizations.delegate,
                GlobalWidgetsLocalizations.delegate,
                GlobalCupertinoLocalizations.delegate,
              ],
              home: const OfflineBannerWidget(child: SplashScreen()),
            );
          },
        );
      },
    );
  }
}
