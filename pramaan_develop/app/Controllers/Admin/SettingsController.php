<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Models\Slide;
use App\Services\Audit;
use App\Services\Settings;

/** Super Admin → App Settings: branding, landing page, limits and the landing slides. */
final class SettingsController extends Controller
{
    private const SLIDE_MAX = 3 * 1024 * 1024;
    private const SLIDE_MAX_COUNT = 10;

    public function save(): void
    {
        $data = [];
        $rules = [];
        foreach (Settings::DEFS as $k => [, $label, $type, $rule]) {
            if ($type === 'bool') {
                $data[$k] = $this->request->input($k) === '1' ? '1' : '0';
                continue;
            }
            $rules[$k] = [$label, $rule];
        }
        $clean = $this->validate($rules);
        foreach ($rules as $k => $_) {
            $data[$k] = $clean[$k] ?? '';
        }
        if ((int) $data['reverify_alert_days'] >= (int) $data['validity_days']) {
            $this->fail('The re-verification alert must start before the validity ends (fewer days than the validity).', 'reverify_alert_days');
        }
        $old = Settings::all();
        $changed = array_filter($data, static fn ($v, $k) => ($old[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH);
        Settings::save($data, Auth::id());
        if ($changed) {
            Audit::log('SETTING', null, 'UPDATED', array_intersect_key($old, $changed), $changed);
        }
        $this->done($changed ? count($changed) . ' setting(s) saved.' : 'No changes to save.', '/admin/settings', $changed ? 'success' : 'info');
    }

    /** Edit captions / order / active flag of all slides in one go. */
    public function slidesSave(): void
    {
        $slides = Slide::where([], 'sort_order');
        $active = 0;
        $rows = [];
        foreach ($slides as $s) {
            $id = $s['slide_id'];
            $v = new Validator([
                'title' => $this->request->str('title_' . $id), 'credit' => $this->request->str('credit_' . $id), 'sort_order' => $this->request->str('order_' . $id),
            ], [
                'title' => ['Slide title', 'required|min:3|max:120|text'], 'credit' => ['Photo credit', 'max:200|text'], 'sort_order' => ['Order', 'required|integer|between:0,99'],
            ]);
            if (!$v->passes()) {
                [$f, $msg] = $v->firstError();
                $this->fail('Slide ' . $s['title'] . ': ' . $msg, ($f === 'sort_order' ? 'order' : $f) . '_' . $id);
            }
            $c = $v->clean();
            $on = $this->request->input('active_' . $id) === '1' ? 1 : 0;
            $active += $on;
            $rows[$id] = ['title' => $c['title'], 'credit' => $c['credit'] ?: null, 'sort_order' => (int) $c['sort_order'], 'is_active' => $on];
        }
        if ($slides && $active < 1) {
            $this->fail('Keep at least one slide active — the landing page needs a photo.');
        }
        Database::transaction(static function () use ($rows): void {
            foreach ($rows as $id => $r) {
                Slide::update($id, $r + ['updated_by' => Auth::id()]);
            }
        });
        Audit::log('SLIDE', null, 'UPDATED', null, ['slides' => count($rows), 'active' => $active]);
        $this->done('Landing page slides saved.', '/admin/settings');
    }

    /** Add a slide: JPG / PNG, re-encoded to JPEG by GD so no hidden payload survives. */
    public function slideAdd(): void
    {
        $d = $this->validate(['title' => ['Slide title', 'required|min:3|max:120|text'], 'credit' => ['Photo credit', 'max:200|text']]);
        if (Slide::count() >= self::SLIDE_MAX_COUNT) {
            $this->fail('At most ' . self::SLIDE_MAX_COUNT . ' slides are allowed. Delete one first.');
        }
        $f = $_FILES['image'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $this->fail('Choose a JPG or PNG photo.', 'image');
        }
        if ((int) $f['size'] > self::SLIDE_MAX) {
            $this->fail('The photo is larger than 3 MB.', 'image');
        }
        $info = @getimagesize($f['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || $info[0] < 600 || $info[1] < 400) {
            $this->fail('Use a JPG or PNG photo of at least 600 × 400 pixels.', 'image');
        }
        $src = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($f['tmp_name']) : @imagecreatefromjpeg($f['tmp_name']);
        if (!$src) {
            $this->fail('The photo could not be read.', 'image');
        }
        // fit to the 10:9 slider frame, max 900 px wide
        [$w, $h] = [imagesx($src), imagesy($src)];
        $tw = min(900, $w);
        $th = (int) round($tw * 9 / 10);
        $scale = max($tw / $w, $th / $h);
        $cw = (int) round($tw / $scale);
        $ch = (int) round($th / $scale);
        $dst = imagecreatetruecolor($tw, $th);
        imagecopyresampled($dst, $src, 0, 0, (int) (($w - $cw) / 2), (int) (($h - $ch) / 2), $tw, $th, $cw, $ch);
        $name = 'slide_' . bin2hex(random_bytes(8)) . '.jpg';
        $ok = imagejpeg($dst, BASE_PATH . '/public/assets/img/slides/' . $name, 82);
        imagedestroy($src);
        imagedestroy($dst);
        if (!$ok) {
            $this->fail('The photo could not be saved.');
        }
        $order = Slide::nextOrder();
        $id = Slide::insert(['image' => $name, 'title' => $d['title'], 'credit' => $d['credit'] ?: null, 'sort_order' => $order, 'is_active' => 1, 'created_by' => Auth::id()]);
        Audit::log('SLIDE', $id, 'CREATED', null, ['title' => $d['title']]);
        $this->done('Slide "' . $d['title'] . '" added to the landing page.', '/admin/settings');
    }

    public function slideDelete(): void
    {
        $s = Slide::find($this->id());
        if (!$s) {
            $this->fail('Slide not found.');
        }
        if ($s['is_active'] && Slide::count(['is_active' => 1]) <= 1) {
            $this->fail('This is the only active slide. Activate another one first.');
        }
        Slide::delete($s['slide_id']);
        $file = BASE_PATH . '/public/assets/img/slides/' . basename($s['image']);
        if (str_starts_with($s['image'], 'slide_') && is_file($file)) {
            @unlink($file); // uploaded photos only; the bundled ones stay
        }
        Audit::log('SLIDE', $s['slide_id'], 'DELETED', ['title' => $s['title']], null);
        $this->done('Slide "' . $s['title'] . '" deleted.');
    }
}
