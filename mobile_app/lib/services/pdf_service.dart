import 'package:intl/intl.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

class PdfService {
  static Future<void> generateAndShareStatement({
    required Map<String, dynamic> investor,
    required List transactions,
    String? currencyFilter,
  }) async {
    final pdf = pw.Document();
    final font = await PdfGoogleFonts.tajawalRegular();
    final fontBold = await PdfGoogleFonts.tajawalBold();
    final numberFormat = NumberFormat('#,##0.00');
    final todayStr = DateFormat('yyyy/MM/dd HH:mm').format(DateTime.now());

    pdf.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4,
        theme: pw.ThemeData.withFont(base: font, bold: fontBold),
        textDirection: pw.TextDirection.rtl,
        margin: const pw.EdgeInsets.all(24),
        build: (pw.Context context) {
          return [
            // Header
            pw.Container(
              padding: const pw.EdgeInsets.all(12),
              decoration: pw.BoxDecoration(
                border: pw.Border.all(color: PdfColors.amber800, width: 2),
                borderRadius: const pw.BorderRadius.all(pw.Radius.circular(8)),
              ),
              child: pw.Column(
                children: [
                  pw.Text(
                    'مجموعة العسافي للاستثمارات',
                    style: pw.TextStyle(font: fontBold, fontSize: 18, color: PdfColors.amber900),
                  ),
                  pw.SizedBox(height: 4),
                  pw.Text(
                    'كشف حساب استثماري رسمي - AL-ASAFY GROUP',
                    style: pw.TextStyle(font: font, fontSize: 12, color: PdfColors.grey700),
                  ),
                  pw.Divider(color: PdfColors.amber700, thickness: 1),
                  pw.Row(
                    mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                    children: [
                      pw.Text('المستثمر: ${investor['full_name'] ?? '—'}', style: pw.TextStyle(font: fontBold, fontSize: 11)),
                      pw.Text('الهاتف: ${investor['phone'] ?? '—'}', style: pw.TextStyle(font: font, fontSize: 10)),
                      pw.Text('المدينة: ${investor['city'] ?? '—'}', style: pw.TextStyle(font: font, fontSize: 10)),
                      pw.Text('تاريخ الطباعة: $todayStr', style: pw.TextStyle(font: font, fontSize: 10)),
                    ],
                  ),
                ],
              ),
            ),

            pw.SizedBox(height: 16),

            // Table of Transactions
            pw.Table(
              border: pw.TableBorder.all(color: PdfColors.grey300, width: 0.5),
              columnWidths: {
                0: const pw.FlexColumnWidth(2), // Date
                1: const pw.FlexColumnWidth(3), // Description
                2: const pw.FlexColumnWidth(1.5), // Receipt
                3: const pw.FlexColumnWidth(1.2), // Currency
                4: const pw.FlexColumnWidth(2), // Amount
              },
              children: [
                // Table Header
                pw.TableRow(
                  decoration: const pw.BoxDecoration(color: PdfColors.grey200),
                  children: [
                    pw.Padding(padding: const pw.EdgeInsets.all(6), child: pw.Text('التاريخ', style: pw.TextStyle(font: fontBold, fontSize: 10))),
                    pw.Padding(padding: const pw.EdgeInsets.all(6), child: pw.Text('نوع الحركة / الملاحظة', style: pw.TextStyle(font: fontBold, fontSize: 10))),
                    pw.Padding(padding: const pw.EdgeInsets.all(6), child: pw.Text('رقم الوصل', style: pw.TextStyle(font: fontBold, fontSize: 10))),
                    pw.Padding(padding: const pw.EdgeInsets.all(6), child: pw.Text('العملة', style: pw.TextStyle(font: fontBold, fontSize: 10))),
                    pw.Padding(padding: const pw.EdgeInsets.all(6), child: pw.Text('المبلغ', style: pw.TextStyle(font: fontBold, fontSize: 10))),
                  ],
                ),
                // Table Rows
                ...transactions.map((tx) {
                  final isCredit = tx['direction'] == 'credit';
                  final amount = double.tryParse(tx['amount'].toString()) ?? 0;
                  final amountStr = (isCredit ? '+ ' : '- ') + numberFormat.format(amount);

                  return pw.TableRow(
                    children: [
                      pw.Padding(padding: const pw.EdgeInsets.all(5), child: pw.Text(tx['date'] ?? '—', style: pw.TextStyle(font: font, fontSize: 9))),
                      pw.Padding(padding: const pw.EdgeInsets.all(5), child: pw.Text(tx['note'] ?? tx['type'] ?? 'معاملة', style: pw.TextStyle(font: font, fontSize: 9))),
                      pw.Padding(padding: const pw.EdgeInsets.all(5), child: pw.Text(tx['receipt_no'] ?? '—', style: pw.TextStyle(font: font, fontSize: 9))),
                      pw.Padding(padding: const pw.EdgeInsets.all(5), child: pw.Text(tx['currency'] ?? '', style: pw.TextStyle(font: font, fontSize: 9))),
                      pw.Padding(
                        padding: const pw.EdgeInsets.all(5),
                        child: pw.Text(
                          amountStr,
                          style: pw.TextStyle(
                            font: fontBold,
                            fontSize: 9,
                            color: isCredit ? PdfColors.green800 : PdfColors.red800,
                          ),
                        ),
                      ),
                    ],
                  );
                }),
              ],
            ),

            pw.SizedBox(height: 24),

            // Footer
            pw.Divider(color: PdfColors.grey400),
            pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                pw.Text('هذا الكشف صادر إلكترونياً من تطبيق مجموعة العسافي للاستثمارات.', style: pw.TextStyle(font: font, fontSize: 8, color: PdfColors.grey600)),
                pw.Text('خدمة العملاء: 07731111861', style: pw.TextStyle(font: fontBold, fontSize: 8, color: PdfColors.amber900)),
              ],
            ),
          ];
        },
      ),
    );

    await Printing.layoutPdf(
      onLayout: (PdfPageFormat format) async => pdf.save(),
      name: 'كشف_حساب_العسافي_${investor['full_name'] ?? 'مستثمر'}.pdf',
    );
  }
}
