import 'package:flutter/material.dart';
import '../constants/theme.dart';

class ProfitChartWidget extends StatefulWidget {
  final List<double> monthlyValues;
  final List<String> monthLabels;
  final String currency;

  const ProfitChartWidget({
    super.key,
    required this.monthlyValues,
    required this.monthLabels,
    this.currency = 'USD',
  });

  @override
  State<ProfitChartWidget> createState() => _ProfitChartWidgetState();
}

class _ProfitChartWidgetState extends State<ProfitChartWidget> {
  int? _selectedIndex;

  @override
  Widget build(BuildContext context) {
    if (widget.monthlyValues.isEmpty) {
      return const SizedBox.shrink();
    }

    final maxVal = widget.monthlyValues.reduce((a, b) => a > b ? a : b);
    final minVal = widget.monthlyValues.reduce((a, b) => a < b ? a : b);

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppTheme.bgCard,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.borderGold.withValues(alpha: 0.6), width: 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.15),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  const Icon(Icons.show_chart, color: AppTheme.gold, size: 22),
                  const SizedBox(width: 8),
                  const Text(
                    'نمو الأرباح المتراكمة (شهرياً)',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 15,
                      color: AppTheme.gold,
                    ),
                  ),
                ],
              ),
              if (_selectedIndex != null && _selectedIndex! < widget.monthlyValues.length)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppTheme.gold.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: AppTheme.gold, width: 1),
                  ),
                  child: Text(
                    '${widget.monthLabels[_selectedIndex!]}: \$${widget.monthlyValues[_selectedIndex!].toStringAsFixed(0)}',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.gold,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 16),
          SizedBox(
            height: 140,
            width: double.infinity,
            child: GestureDetector(
              onTapDown: (details) {
                final boxWidth = context.size?.width ?? 300;
                final pointWidth = boxWidth / (widget.monthlyValues.length > 1 ? widget.monthlyValues.length : 1);
                final index = (details.localPosition.dx / pointWidth).floor().clamp(0, widget.monthlyValues.length - 1);
                setState(() => _selectedIndex = index);
              },
              child: CustomPaint(
                painter: _LuxuryLineChartPainter(
                  values: widget.monthlyValues,
                  minVal: minVal == maxVal ? 0 : minVal * 0.8,
                  maxVal: maxVal == 0 ? 100 : maxVal * 1.15,
                  selectedIndex: _selectedIndex,
                ),
              ),
            ),
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: List.generate(widget.monthLabels.length, (i) {
              final isSel = _selectedIndex == i;
              return GestureDetector(
                onTap: () => setState(() => _selectedIndex = i),
                child: Text(
                  widget.monthLabels[i],
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: isSel ? FontWeight.bold : FontWeight.normal,
                    color: isSel ? AppTheme.gold : AppTheme.textMuted,
                  ),
                ),
              );
            }),
          ),
        ],
      ),
    );
  }
}

class _LuxuryLineChartPainter extends CustomPainter {
  final List<double> values;
  final double minVal;
  final double maxVal;
  final int? selectedIndex;

  _LuxuryLineChartPainter({
    required this.values,
    required this.minVal,
    required this.maxVal,
    this.selectedIndex,
  });

  @override
  void paint(Canvas canvas, Size size) {
    if (values.length < 2) return;

    final points = <Offset>[];
    final dx = size.width / (values.length - 1);

    for (int i = 0; i < values.length; i++) {
      final norm = (values[i] - minVal) / (maxVal - minVal);
      final y = size.height - (norm * size.height).clamp(0.0, size.height);
      points.add(Offset(i * dx, y));
    }

    // Path for gradient fill
    final fillPath = Path();
    fillPath.moveTo(points.first.dx, size.height);
    fillPath.lineTo(points.first.dx, points.first.dy);

    for (int i = 0; i < points.length - 1; i++) {
      final p1 = points[i];
      final p2 = points[i + 1];
      final cp1 = Offset(p1.dx + (p2.dx - p1.dx) / 2, p1.dy);
      final cp2 = Offset(p1.dx + (p2.dx - p1.dx) / 2, p2.dy);
      fillPath.cubicTo(cp1.dx, cp1.dy, cp2.dx, cp2.dy, p2.dx, p2.dy);
    }

    fillPath.lineTo(points.last.dx, size.height);
    fillPath.close();

    final fillPaint = Paint()
      ..shader = LinearGradient(
        colors: [
          AppTheme.gold.withValues(alpha: 0.35),
          AppTheme.gold.withValues(alpha: 0.0),
        ],
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
      ).createShader(Rect.fromLTWH(0, 0, size.width, size.height))
      ..style = PaintingStyle.fill;

    canvas.drawPath(fillPath, fillPaint);

    // Path for gold curve line
    final linePath = Path();
    linePath.moveTo(points.first.dx, points.first.dy);

    for (int i = 0; i < points.length - 1; i++) {
      final p1 = points[i];
      final p2 = points[i + 1];
      final cp1 = Offset(p1.dx + (p2.dx - p1.dx) / 2, p1.dy);
      final cp2 = Offset(p1.dx + (p2.dx - p1.dx) / 2, p2.dy);
      linePath.cubicTo(cp1.dx, cp1.dy, cp2.dx, cp2.dy, p2.dx, p2.dy);
    }

    final linePaint = Paint()
      ..color = AppTheme.gold
      ..strokeWidth = 3
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    canvas.drawPath(linePath, linePaint);

    // Draw glowing data points
    final pointPaint = Paint()..color = AppTheme.gold;
    final haloPaint = Paint()..color = AppTheme.gold.withValues(alpha: 0.35);

    for (int i = 0; i < points.length; i++) {
      final p = points[i];
      final isSel = selectedIndex == i;
      if (isSel) {
        canvas.drawCircle(p, 9, haloPaint);
        canvas.drawCircle(p, 5, pointPaint);
        final whitePaint = Paint()..color = Colors.white;
        canvas.drawCircle(p, 2.5, whitePaint);
      } else {
        canvas.drawCircle(p, 3.5, pointPaint);
      }
    }
  }

  @override
  bool shouldRepaint(covariant _LuxuryLineChartPainter oldDelegate) => true;
}
