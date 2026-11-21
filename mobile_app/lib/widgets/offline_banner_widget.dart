import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import '../constants/theme.dart';
import '../constants/localization.dart';

class OfflineBannerWidget extends StatefulWidget {
  final Widget child;

  const OfflineBannerWidget({super.key, required this.child});

  @override
  State<OfflineBannerWidget> createState() => _OfflineBannerWidgetState();
}

class _OfflineBannerWidgetState extends State<OfflineBannerWidget> {
  bool _isOffline = false;
  bool _wasOffline = false;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _checkConnection();
    // Check connection every 10 seconds
    _timer = Timer.periodic(const Duration(seconds: 10), (_) => _checkConnection());
  }

  Future<void> _checkConnection() async {
    try {
      final result = await InternetAddress.lookup('alasafygroup.xyz')
          .timeout(const Duration(seconds: 4));
      final online = result.isNotEmpty && result[0].rawAddress.isNotEmpty;
      if (mounted) {
        if (!online && !_isOffline) {
          setState(() {
            _isOffline = true;
            _wasOffline = true;
          });
        } else if (online && _isOffline) {
          setState(() => _isOffline = false);
          // Show restored banner briefly
          Future.delayed(const Duration(seconds: 3), () {
            if (mounted) setState(() => _wasOffline = false);
          });
        }
      }
    } catch (_) {
      if (mounted && !_isOffline) {
        setState(() {
          _isOffline = true;
          _wasOffline = true;
        });
      }
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        if (_isOffline)
          AnimatedContainer(
            duration: const Duration(milliseconds: 300),
            width: double.infinity,
            color: AppTheme.danger,
            padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
            child: SafeArea(
              bottom: false,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.wifi_off, color: Colors.white, size: 18),
                  const SizedBox(width: 8),
                  Text(
                    tr('offline_title'),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
          )
        else if (_wasOffline)
          AnimatedContainer(
            duration: const Duration(milliseconds: 300),
            width: double.infinity,
            color: AppTheme.success,
            padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 16),
            child: const SafeArea(
              bottom: false,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.wifi, color: Colors.white, size: 18),
                  SizedBox(width: 8),
                  Text(
                    'تمت استعادة الاتصال بالإنترنت بنجاح',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
          ),
        Expanded(child: widget.child),
      ],
    );
  }
}
