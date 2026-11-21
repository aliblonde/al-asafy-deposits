import 'package:flutter_test/flutter_test.dart';
import 'package:al_asafy_investor/main.dart';

void main() {
  testWidgets('App smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const AlAsafyApp(initialLoggedIn: false));
    expect(find.byType(AlAsafyApp), findsOneWidget);
  });
}
