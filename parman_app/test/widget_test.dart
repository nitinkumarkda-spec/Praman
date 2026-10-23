// Unit tests for the parts that do not need a device: validation, formatting and the demo seed data.
import 'package:flutter_test/flutter_test.dart';
import 'package:parman_app/app/db.dart';
import 'package:parman_app/app/format.dart';
import 'package:parman_app/app/validation.dart';

void main() {
  test('validation rules match the web form', () {
    expect(Validator.check('', 'required', 'Name'), 'Name is required.');
    expect(Validator.check('', 'required', 'Zone', choice: true), 'Please select Zone.');
    expect(Validator.check('Praman@123', 'required|password', 'Password'), isNull);
    expect(Validator.check('abc', 'required|password', 'Password'), isNotNull);
    expect(Validator.check('9800000001', 'mobile', 'Mobile'), isNull);
    expect(Validator.check('12345', 'mobile', 'Mobile'), isNotNull);
    expect(Validator.check('ENGINEERING_CELL', 'code', 'Code'), isNull);
    expect(Validator.check('150.50', 'decimal:2|positive', 'Area'), isNull);
    expect(Validator.check('150.505', 'decimal:2', 'Area'), isNotNull);
  });

  test('formatting helpers', () {
    expect(fmtNum(1234.5, 2), '1,234.50');
    expect(fmtNum(1500000), '1,500,000');
    expect(label('AUCTION_READY'), 'Auction Ready');
    expect(fmtDate('2026-10-23 09:05:00'), '23-10-2026');
    expect(fmtDateTime('2026-10-23 15:05:00'), '23-10-2026 03:05 PM');
    expect(initials('Suresh Chand Sharma'), 'SC');
  });

  test('seed data is the same as the web demo', () {
    final db = Db()..seed();
    expect(db.count('roles'), 8);
    expect(db.count('users'), 22);
    expect(db.count('schemes'), 3);
    expect(db.count('properties'), 10);
    expect(db.find('properties', 1)!['current_status'], 'LISTED');
    expect(db.find('properties', 3)!['current_status'], 'UNDER_VERIFICATION');
    expect(db.find('properties', 5)!['current_status'], 'AUCTION_READY');
    expect(db.find('properties', 6)!['current_status'], 'AUCTION_READY');
    expect(db.find('properties', 7)!['current_status'], 'NOT_READY');
    expect(db.find('properties', 8)!['current_status'], 'UNDER_VERIFICATION');
    expect(db.where('verification_rounds', {'property_id': 8}).length, 2);
    expect(db.count('attachments'), 3);
  });

  test('verification engine: send + all approve = auction ready', () {
    final db = Db()..seed();
    final sent = db.verificationSend(1, 3, null);
    expect(sent.ok, isTrue);
    final round = db.first('verification_rounds', {'property_id': 1, 'round_status': 'OPEN'})!;
    String? outcome;
    for (final t in db.where('verification_tasks', {'round_id': round['round_id']})) {
      final uid = toInt(db.where('verification_task_assignees', {'task_id': t['task_id']}).first['user_id'])!;
      outcome = db.verificationRespond(toInt(t['task_id'])!, 'APPROVED', 'ok', uid).outcome ?? outcome;
    }
    expect(outcome, 'READY');
    expect(db.find('properties', 1)!['current_status'], 'AUCTION_READY');
  });
}
