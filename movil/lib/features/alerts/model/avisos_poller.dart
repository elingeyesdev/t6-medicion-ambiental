import 'dart:async';

import 'package:flutter/widgets.dart';

import '../../../core/network/geocruz_api_client.dart';
import 'alerts_repository.dart';
import 'aviso_local_notifications.dart';

/// Consulta periódica de avisos propios y dispara notificaciones locales.
class AvisosPoller with WidgetsBindingObserver {
  AvisosPoller({
    required GeoCruzApiClient api,
    this.interval = const Duration(minutes: 3),
  }) : _repository = AlertsRepository(api);

  final AlertsRepository _repository;
  final Duration interval;

  Timer? _timer;
  bool _running = false;
  bool _tickInFlight = false;

  void start() {
    if (_running) return;
    _running = true;
    WidgetsBinding.instance.addObserver(this);
    unawaited(tick());
    _timer = Timer.periodic(interval, (_) => unawaited(tick()));
  }

  void stop() {
    if (!_running) return;
    _running = false;
    _timer?.cancel();
    _timer = null;
    WidgetsBinding.instance.removeObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(tick());
    }
  }

  Future<void> tick() async {
    if (!_running || _tickInFlight) return;
    _tickInFlight = true;
    try {
      final page = await _repository.listAvisos(page: 1);
      await AvisoLocalNotifications.instance.notifyUnread(page.items);
    } on GeoCruzApiException catch (error) {
      if (error.statusCode == 401) {
        stop();
      }
    } catch (_) {
      // Silencioso: el sondeo no debe molestar la UI.
    } finally {
      _tickInFlight = false;
    }
  }
}
