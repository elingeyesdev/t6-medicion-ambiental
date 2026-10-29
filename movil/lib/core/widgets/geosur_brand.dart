import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class GeoSurBrand extends StatelessWidget {
  const GeoSurBrand({this.compact = false, super.key});

  final bool compact;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      SvgPicture.asset(
        'assets/marca/geosur-simbolo.svg',
        width: compact ? 34 : 48,
        height: compact ? 34 : 48,
        semanticsLabel: 'Símbolo GeoSur',
      ),
      const SizedBox(width: 10),
      Text(
        'GeoSur',
        style:
            (compact
                    ? Theme.of(context).textTheme.titleLarge
                    : Theme.of(context).textTheme.headlineMedium)
                ?.copyWith(fontWeight: FontWeight.w700),
      ),
    ],
  );
}
