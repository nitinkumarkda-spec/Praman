// PRAMAN — attachments (port of js/files.js): PDF / JPG / JPEG / PNG, max 5 MB each, up to 10 files.
// Files are copied into the app's documents folder; the "attachments" table keeps the metadata.
// Seeded samples (inspection photo / report / stay order) are drawn / generated on the fly.
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../app/db.dart';
import '../app/fa.dart';
import '../app/format.dart';
import '../app/store.dart';
import '../app/theme.dart';
import 'common.dart';

const Map<String, String> kAllowedExt = {'pdf': 'application/pdf', 'jpg': 'image/jpeg', 'jpeg': 'image/jpeg', 'png': 'image/png'};
const int kMaxFileSize = 5 * 1024 * 1024;
const int kMaxFiles = 10;
const String kAttachRules = 'PDF, JPG, JPEG, PNG · max 5 MB each · up to $kMaxFiles files';

class PickedFile {
  final String name;
  final int size;
  final String path;
  final String mime;
  const PickedFile(this.name, this.size, this.path, this.mime);
  bool get isImage => mime.startsWith('image/');
}

String _ext(String name) {
  final m = RegExp(r'\.([a-z0-9]+)$', caseSensitive: false).firstMatch(name);
  return m == null ? '' : m.group(1)!.toLowerCase();
}

/// P.attachmentsFor(where)
List<Rec> attachmentsFor(Map<String, Object?> where) {
  final l = P.db.where('attachments', where);
  l.sort((a, b) => (toInt(a['attachment_id']) ?? 0).compareTo(toInt(b['attachment_id']) ?? 0));
  return l;
}

/// Store picked files for a task; returns the attachment rows. Rolls back on failure.
Future<List<Rec>> saveAttachments(List<PickedFile> files, {required int taskId, required int roundId, required int propertyId, required int by}) async {
  final saved = <Rec>[];
  if (files.isEmpty) return saved;
  final docs = await getApplicationDocumentsDirectory();
  final dir = Directory('${docs.path}/attachments');
  if (!await dir.exists()) await dir.create(recursive: true);
  final rnd = math.Random();
  try {
    for (final f in files) {
      final key = 'att-${DateTime.now().millisecondsSinceEpoch}-${rnd.nextInt(1 << 30).toRadixString(36)}';
      final safe = f.name.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_');
      final dest = '${dir.path}/$key-$safe';
      await File(f.path).copy(dest);
      saved.add(P.db.insert('attachments', {
        'task_id': taskId, 'round_id': roundId, 'property_id': propertyId, 'context': 'TASK_RESPONSE',
        'file_name': f.name, 'mime': kAllowedExt[_ext(f.name)], 'size': f.size, 'uploaded_by': by,
        'storage': 'file', 'path': dest, 'data': null,
      }));
    }
  } catch (e) {
    removeAttachments(saved);
    rethrow;
  }
  return saved;
}

void removeAttachments(List<Rec> rows) {
  final ids = rows.map((r) => toInt(r['attachment_id'])).toSet();
  for (final r in rows) {
    final p = r['path'];
    if (p is String) {
      try {
        File(p).deleteSync();
      } catch (_) {}
    }
  }
  P.db.removeWhere('attachments', (a) => ids.contains(toInt(a['attachment_id'])));
}

bool _isPdf(Rec a) => str(a['mime']).contains('pdf');

/* ======================= chips + viewer ======================= */

class AttachmentChips extends StatelessWidget {
  final List<Rec> rows;
  const AttachmentChips(this.rows, {super.key});

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) return const SizedBox.shrink();
    return Wrap(spacing: 6, runSpacing: 6, children: [
      for (final a in rows)
        Material(
          color: _isPdf(a) ? const Color(0xFFFEF2F2) : const Color(0xFFEFF6FF),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8), side: BorderSide(color: _isPdf(a) ? const Color(0xFFFECACA) : const Color(0xFFBFDBFE))),
          child: InkWell(
            borderRadius: BorderRadius.circular(8),
            onTap: () => openAttachment(context, a),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(_isPdf(a) ? FA.filePdf : FA.fileImage, size: 12, color: _isPdf(a) ? C.red600 : C.blue600),
                const SizedBox(width: 6),
                ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 170),
                  child: Text(str(a['file_name']), maxLines: 1, overflow: TextOverflow.ellipsis, style: T.s(12, weight: FontWeight.w600, color: C.text)),
                ),
                const SizedBox(width: 6),
                Text(fmtSize(a['size']), style: T.s(11, color: C.muted)),
              ]),
            ),
          ),
        ),
    ]);
  }
}

Future<void> openAttachment(BuildContext context, Rec a) {
  return showDialog<void>(context: context, builder: (c) => _AttachmentViewer(a));
}

/// Write a PDF / image to a temp file and open it with the phone's viewer app.
Future<void> openExternally(Rec a) async {
  try {
    String? path;
    if (a['storage'] == 'file') {
      path = str(a['path']);
    } else if (_isPdf(a)) {
      final bytes = a['data'] != null ? base64Decode(str(a['data'])) : utf8.encode(samplePdf((a['lines'] as List? ?? const []).map((e) => e.toString()).toList()));
      final tmp = await getTemporaryDirectory();
      final f = File('${tmp.path}/${str(a['file_name']).replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_')}');
      await f.writeAsBytes(bytes, flush: true);
      path = f.path;
    }
    if (path == null) {
      P.toast('info', 'This sample photo is shown inside the app only.');
      return;
    }
    final r = await OpenFilex.open(path);
    if (r.type != ResultType.done) P.toast('warning', r.message.isEmpty ? 'No app found to open this file.' : r.message);
  } catch (e) {
    P.toast('error', 'Could not open the file: $e');
  }
}

class _AttachmentViewer extends StatelessWidget {
  final Rec a;
  const _AttachmentViewer(this.a);

  @override
  Widget build(BuildContext context) {
    final uploader = P.db.find('users', a['uploaded_by']);
    final pdf = _isPdf(a);
    final lines = (a['lines'] as List? ?? const []).map((e) => e.toString()).toList();
    Widget body;
    if (!pdf && a['storage'] == 'file') {
      body = ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.file(File(str(a['path'])), fit: BoxFit.contain, errorBuilder: (c, e, s) => _missing()));
    } else if (!pdf) {
      body = ClipRRect(borderRadius: BorderRadius.circular(8), child: AspectRatio(aspectRatio: 640 / 400, child: CustomPaint(painter: SitePhotoPainter(lines))));
    } else if (lines.isNotEmpty) {
      body = _PaperPreview(lines);
    } else {
      body = Padding(
        padding: const EdgeInsets.symmetric(vertical: 28),
        child: Column(children: [
          const Icon(FA.filePdf, size: 42, color: C.red600),
          const SizedBox(height: 10),
          Text('PDF document', style: T.s(15, weight: FontWeight.w600)),
          Text('Opens in your phone\'s PDF viewer.', style: T.small),
        ]),
      );
    }
    final canOpen = pdf || a['storage'] == 'file';
    return Dialog(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
      insetPadding: const EdgeInsets.all(14),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 8, 12),
          decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: C.border))),
          child: Row(children: [
            Icon(pdf ? FA.filePdf : FA.fileImage, size: 16, color: pdf ? C.red600 : C.blue600),
            const SizedBox(width: 10),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(str(a['file_name']), maxLines: 1, overflow: TextOverflow.ellipsis, style: T.s(14, weight: FontWeight.w600)),
                Text('${fmtSize(a['size'])} · ${uploader?['name'] ?? ''} · ${fmtDateTime(a['created_at'])}', style: T.tiny),
              ]),
            ),
            IconButton(onPressed: () => Navigator.of(context).pop(), icon: const Icon(FA.xmark, size: 16, color: C.slate600)),
          ]),
        ),
        Flexible(child: SingleChildScrollView(padding: const EdgeInsets.all(14), child: body)),
        if (canOpen)
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
            child: Btn(pdf ? 'Open in PDF viewer' : 'Open with…', icon: FA.arrowUpRightFromSquare, variant: 'light', block: true, onPressed: () => openExternally(a)),
          ),
      ]),
    );
  }

  Widget _missing() => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(children: [
          const Icon(FA.triangleExclamation, size: 24, color: C.amber600),
          const SizedBox(height: 8),
          Text('File not found on this phone.', style: T.small),
        ]),
      );
}

/// Paper-like preview of a sample PDF (text lines in Helvetica-ish style).
class _PaperPreview extends StatelessWidget {
  final List<String> lines;
  const _PaperPreview(this.lines);
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(22, 26, 22, 40),
      decoration: BoxDecoration(color: Colors.white, border: Border.all(color: C.border), boxShadow: kShadow),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        for (var i = 0; i < lines.length; i++)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Text(lines[i], style: TextStyle(fontFamily: 'sans-serif', fontSize: i == 0 ? 14 : 13, fontWeight: i == 0 ? FontWeight.w700 : FontWeight.w400, color: const Color(0xFF111827))),
          ),
      ]),
    );
  }
}

/// The seeded "site inspection photo" (same drawing as sampleJpeg() in db.js).
class SitePhotoPainter extends CustomPainter {
  final List<String> lines;
  SitePhotoPainter(this.lines);

  @override
  void paint(Canvas canvas, Size size) {
    final sx = size.width / 640, sy = size.height / 400;
    canvas.save();
    canvas.scale(sx, sy);
    final rect = const Rect.fromLTWH(0, 0, 640, 400);
    canvas.drawRect(rect, Paint()..shader = const LinearGradient(colors: [Color(0xFF8AA7C8), Color(0xFFD9C79A)], begin: Alignment.topLeft, end: Alignment.bottomRight).createShader(rect));
    canvas.drawRect(const Rect.fromLTWH(380, 170, 190, 150), Paint()..color = const Color(0xFF6B7280));
    final roof = Path()
      ..moveTo(370, 175)
      ..lineTo(475, 120)
      ..lineTo(580, 175)
      ..close();
    canvas.drawPath(roof, Paint()..color = const Color(0xFF9CA3AF));
    canvas.drawRect(const Rect.fromLTWH(365, 112, 222, 216), Paint()
      ..color = const Color(0xFFB91C1C)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 5);
    canvas.drawRect(const Rect.fromLTWH(0, 0, 640, 120), Paint()..color = const Color(0xD90F2B52));
    for (var i = 0; i < lines.length; i++) {
      final tp = TextPainter(
        text: TextSpan(text: lines[i], style: TextStyle(fontFamily: 'sans-serif', color: Colors.white, fontSize: i == 0 ? 22 : 16, fontWeight: i == 0 ? FontWeight.w700 : FontWeight.w400)),
        textDirection: TextDirection.ltr,
      )..layout(maxWidth: 600);
      tp.paint(canvas, Offset(20, i == 0 ? 16 : 50 + (i - 1) * 22.0));
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant SitePhotoPainter oldDelegate) => false;
}

/* ======================= upload widget ======================= */

/// "Drag & drop" zone of the web app → tap to pick files; list with thumbnails and remove buttons.
class Uploader extends StatelessWidget {
  final String label;
  final List<PickedFile> files;
  final VoidCallback onChanged;
  const Uploader({super.key, required this.label, required this.files, required this.onChanged});

  Future<void> _pick() async {
    FilePickerResult? r;
    try {
      r = await FilePicker.platform.pickFiles(allowMultiple: true, type: FileType.custom, allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png']);
    } catch (e) {
      P.toast('error', 'Could not open the file picker: $e');
      return;
    }
    if (r == null) return;
    final errs = <String>[];
    for (final f in r.files) {
      final x = _ext(f.name);
      final path = f.path;
      if (!kAllowedExt.containsKey(x)) {
        errs.add('"${f.name}": only PDF, JPG, JPEG or PNG files are allowed.');
        continue;
      }
      if (f.size > kMaxFileSize) {
        errs.add('"${f.name}" is ${fmtSize(f.size)} — the limit is 5 MB.');
        continue;
      }
      if (f.size == 0) {
        errs.add('"${f.name}" is empty.');
        continue;
      }
      if (path == null) {
        errs.add('"${f.name}" could not be read.');
        continue;
      }
      if (files.any((e) => e.name.toLowerCase() == f.name.toLowerCase())) {
        errs.add('"${f.name}" is already added (a file with the same name).');
        continue;
      }
      if (files.length >= kMaxFiles) {
        errs.add('You can attach at most $kMaxFiles files.');
        continue;
      }
      files.add(PickedFile(f.name, f.size, path, kAllowedExt[x]!));
    }
    if (errs.isNotEmpty) P.toast('error', errs.take(3).join(' '));
    onChanged();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text.rich(TextSpan(children: [TextSpan(text: label, style: T.label), TextSpan(text: '  (optional)', style: T.tiny)])),
        const SizedBox(height: 6),
        Material(
          color: files.isEmpty ? C.navy50 : Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: const BorderSide(color: C.borderStrong, width: 1.2)),
          child: InkWell(
            borderRadius: BorderRadius.circular(12),
            onTap: _pick,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
              child: Row(children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: C.border)),
                  alignment: Alignment.center,
                  child: const Icon(FA.cloudArrowUp, size: 18, color: C.navy700),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text.rich(TextSpan(children: [
                      TextSpan(text: 'Tap to add files', style: T.s(14, weight: FontWeight.w600)),
                      TextSpan(text: '  or ', style: T.s(13, color: C.muted)),
                      TextSpan(text: 'browse', style: T.s(13, weight: FontWeight.w600, color: C.blue600)),
                    ])),
                    Text(kAttachRules, style: T.tiny),
                  ]),
                ),
              ]),
            ),
          ),
        ),
        for (var i = 0; i < files.length; i++)
          Container(
            margin: const EdgeInsets.only(top: 8),
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: C.border)),
            child: Row(children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: SizedBox(
                  width: 42,
                  height: 42,
                  child: files[i].isImage
                      ? Image.file(File(files[i].path), fit: BoxFit.cover, errorBuilder: (c, e, s) => const Icon(FA.fileImage, color: C.blue600))
                      : Container(color: const Color(0xFFFEF2F2), alignment: Alignment.center, child: const Icon(FA.filePdf, size: 18, color: C.red600)),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(files[i].name, maxLines: 1, overflow: TextOverflow.ellipsis, style: T.s(13.5, weight: FontWeight.w600)),
                  Text(fmtSize(files[i].size), style: T.tiny),
                ]),
              ),
              IconBtn(FA.trashCan, variant: 'light-danger', tooltip: 'Remove', onPressed: () {
                files.removeAt(i);
                onChanged();
              }),
            ]),
          ),
      ]),
    );
  }
}
