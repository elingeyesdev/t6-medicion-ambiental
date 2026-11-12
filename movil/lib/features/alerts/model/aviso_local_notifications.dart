import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'alert_models.dart';

/// Notificaciones del sistema (bandeja Android/iOS) para avisos propios.
///
/// No es FCM remoto: requiere `google-services.json` + Firebase en el móvil.
/// Aquí se publican avisos no leídos detectados al consultar la API.
class AvisoLocalNotifications {
  AvisoLocalNotifications._();

  static final instance = AvisoLocalNotifications._();

  static const _channelId = 'geosur_avisos';
  static const _prefsKey = 'geosur_avisos_notificados';
  static const _prefsEnabledKey = 'geosur_avisos_notif_activas';

  final FlutterLocalNotificationsPlugin _plugin =
      FlutterLocalNotificationsPlugin();

  bool _ready = false;
  void Function(String avisoId)? onOpenAviso;

  Future<void> init({void Function(String avisoId)? onOpenAviso}) async {
    this.onOpenAviso = onOpenAviso;
    if (_ready || kIsWeb) return;

    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const ios = DarwinInitializationSettings();
    await _plugin.initialize(
      settings: const InitializationSettings(android: android, iOS: ios),
      onDidReceiveNotificationResponse: (response) {
        final id = response.payload;
        if (id != null && id.isNotEmpty) {
          this.onOpenAviso?.call(id);
        }
      },
    );

    if (Platform.isAndroid) {
      final androidPlugin = _plugin.resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin>();
      await androidPlugin?.requestNotificationsPermission();
      await androidPlugin?.createNotificationChannel(
        const AndroidNotificationChannel(
          _channelId,
          'Avisos GeoSur',
          description: 'Avisos ambientales de tu cuenta',
          importance: Importance.high,
        ),
      );
    } else if (Platform.isIOS) {
      await _plugin
          .resolvePlatformSpecificImplementation<
              IOSFlutterLocalNotificationsPlugin>()
          ?.requestPermissions(alert: true, badge: true, sound: true);
    }
    _ready = true;
  }

  Future<bool> get enabled async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_prefsEnabledKey) ?? true;
  }

  Future<void> setEnabled(bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_prefsEnabledKey, value);
  }

  Future<void> notifyUnread(Iterable<AvisoAmbiental> avisos) async {
    if (!_ready || kIsWeb) return;
    if (!await enabled) return;

    final prefs = await SharedPreferences.getInstance();
    final seen = prefs.getStringList(_prefsKey)?.toSet() ?? <String>{};
    final unread = avisos.where((a) => !a.leido && a.id.isNotEmpty).toList();
    if (unread.isEmpty) return;

    var changed = false;
    for (final aviso in unread) {
      if (seen.contains(aviso.id)) continue;
      final title = aviso.alerta.titulo;
      final body = aviso.alerta.resumen;
      final notifId = aviso.id.hashCode & 0x7fffffff;
      await _plugin.show(
        id: notifId,
        title: title,
        body: body,
        notificationDetails: NotificationDetails(
          android: AndroidNotificationDetails(
            _channelId,
            'Avisos GeoSur',
            channelDescription: 'Avisos ambientales de tu cuenta',
            importance: Importance.high,
            priority: Priority.high,
            styleInformation: BigTextStyleInformation(body),
          ),
          iOS: const DarwinNotificationDetails(),
        ),
        payload: aviso.id,
      );
      seen.add(aviso.id);
      changed = true;
    }
    if (changed) {
      final list = seen.toList(growable: false);
      await prefs.setStringList(
        _prefsKey,
        list.length <= 200 ? list : list.sublist(list.length - 200),
      );
    }
  }

  Future<void> clearSeenCache() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_prefsKey);
  }
}
