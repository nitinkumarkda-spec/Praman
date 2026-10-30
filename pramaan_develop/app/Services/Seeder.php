<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Core\Password;
use App\Models\LoginAttempt;
use App\Models\PlotType;
use App\Models\Property;
use App\Models\Role;
use App\Models\Round;
use App\Models\Scheme;
use App\Models\SchemeUser;
use App\Models\Sector;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;

/**
 * Sample data: the same roles, users, masters, schemes, properties and
 * verification history as the HTML prototype, spread over the last 30 days.
 * Every seeded user's password is Praman@123.
 */
final class Seeder
{
    public const PASSWORD = 'Praman@123';
    private const DAY = 1440;

    private const TABLES = ['site_inspections', 'sale_checks', 'attachments', 'task_assignees', 'tasks', 'notifications', 'audit_logs', 'login_attempts',
        'property_users', 'rounds', 'properties', 'scheme_users', 'sectors', 'schemes', 'plot_types', 'statuses', 'users', 'roles'];

    public static function run(): void
    {
        @set_time_limit(300);
        self::truncate();
        self::seedConfig();
        try {
            self::seed();
        } finally {
            Clock::reset();
        }
        @unlink(BASE_PATH . '/storage/cache/housekeeping.txt');
    }

    private static function truncate(): void
    {
        Database::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $t) {
            Database::pdo()->exec('TRUNCATE TABLE ' . \App\Core\Table::q($t));
        }
        Database::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
        // uploaded files belong to the old data
        $dir = Uploads::dir();
        if (is_dir($dir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                if ($f->getFilename() === '.gitignore') {
                    continue;
                }
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
        }
    }

    /** App settings and landing slides: only filled when empty, so a data reset keeps the Super Admin's settings. */
    public static function seedConfig(): void
    {
        Settings::seed();
        if (\App\Models\Slide::count() === 0) {
            foreach ([
                ['kishore_sagar.jpg', 'Kishore Sagar, Kota', 'Photo: Kushagra140, CC BY-SA 4.0, via Wikimedia Commons'],
                ['garh_palace.jpg', 'Garh Palace, Kota', 'Photo: Shrikant Bansod, CC BY-SA 4.0, via Wikimedia Commons'],
                ['river_front.jpg', 'Chambal River Front, Kota', 'Photo: Imakanksha, CC BY-SA 4.0, via Wikimedia Commons'],
                ['seven_wonders.jpg', 'Seven Wonders Park, Kota', 'Photo: Arvind Sunda07, CC BY-SA 4.0, via Wikimedia Commons'],
            ] as $i => $x) {
                \App\Models\Slide::insert(['image' => $x[0], 'title' => $x[1], 'credit' => $x[2], 'sort_order' => $i + 1, 'is_active' => 1]);
            }
        }
    }

    private static function at(float $minutesAgo): void
    {
        Clock::travel($minutesAgo);
    }

    private static function actor(int $uid): array
    {
        $u = User::find($uid);
        return ['user_id' => $u['user_id'], 'name' => $u['name'], 'designation' => $u['designation']];
    }

    private static function seed(): void
    {
        self::at(30 * self::DAY);

        // ---- roles (verification sections) ----
        $roles = [
            ['AUCTION_CELL', 'Auction Cell', 'नीलामी शाखा', 'Allotment by auction — allottee / cancellation record', 5],
            ['LOTTERY_CELL', 'Lottery Cell', 'लॉटरी शाखा', 'Allotment by lottery — allottee / cancellation record', 5],
            ['SALE_CELL', 'Sale Cell', 'विक्रय शाखा', 'Sale register — sold / reserved / vacant on record', 5],
            ['LAND_FOR_LAND_CELL', 'Land-for-Land Cell', 'भूमि के बदले भूमि शाखा', 'Land given against acquired land', 5],
            ['PUNARVAS_CELL', 'Punarvas Cell', 'पुनर्वास शाखा', 'Rehabilitation allotments', 5],
            ['ENGINEERING_CELL', 'Engineering Cell', 'अभियांत्रिकी शाखा', 'Site inspection — vacancy, encroachment, measurements', 10],
            ['TOWN_PLANNING_CELL', 'Town Planning Cell', 'नगर नियोजन शाखा', 'Master plan / layout / road widening check', 7],
            ['LEGAL_CELL', 'Legal Cell', 'विधि शाखा', 'Litigation / stay order check', 7],
        ];
        foreach ($roles as $i => $r) {
            Role::insert(['role_code' => $r[0], 'role_name' => $r[1], 'role_name_hi' => $r[2], 'description' => $r[3], 'sla_days' => $r[4],
                'is_mandatory' => 1, 'sort_order' => $i + 1, 'is_active' => 1, 'created_by' => null]);
        }

        // ---- users ----
        $users = [
            ['superadmin', 'KDA-IT-001', 'System Administrator', 'Programmer (IT Cell)', '9800000001', 'SUPER_ADMIN', null],
            ['dataentry', 'KDA-DE-001', 'Ramesh Kumar Meena', 'Data Entry Operator', '9800000002', 'DATA_ENTRY', null],
            ['oic', 'KDA-OIC-001', 'Suresh Chand Sharma', 'OIC — Sale / Auction', '9800000003', 'OIC', null],
            ['auction1', 'KDA-AUC-001', 'Anil Kumar Jain', 'Assistant, Auction Cell', '9800000011', 'ROLE_USER', 1],
            ['auction2', 'KDA-AUC-002', 'Pooja Saini', 'Section Incharge, Auction', '9800000012', 'ROLE_USER', 1],
            ['lottery1', 'KDA-LOT-001', 'Mahesh Gurjar', 'Assistant, Lottery Cell', '9800000021', 'ROLE_USER', 2],
            ['lottery2', 'KDA-LOT-002', 'Kavita Rathore', 'Section Incharge, Lottery', '9800000022', 'ROLE_USER', 2],
            ['sale1', 'KDA-SAL-001', 'Deepak Verma', 'Clerk, Sale Cell', '9800000031', 'ROLE_USER', 3],
            ['sale2', 'KDA-SAL-002', 'Neha Agarwal', 'Section Incharge, Sale', '9800000032', 'ROLE_USER', 3],
            ['lfl1', 'KDA-LFL-001', 'Bhanwar Lal Bairwa', 'Assistant, Land-for-Land', '9800000041', 'ROLE_USER', 4],
            ['lfl2', 'KDA-LFL-002', 'Sunita Mehra', 'Section Incharge, LFL', '9800000042', 'ROLE_USER', 4],
            ['punarvas1', 'KDA-PUN-001', 'Rajendra Prasad', 'Assistant, Punarvas Cell', '9800000051', 'ROLE_USER', 5],
            ['punarvas2', 'KDA-PUN-002', 'Asha Kanwar', 'Section Incharge, Punarvas', '9800000052', 'ROLE_USER', 5],
            ['eng1', 'KDA-ENG-001', 'Vikas Choudhary', 'Junior Engineer', '9800000061', 'ROLE_USER', 6],
            ['eng2', 'KDA-ENG-002', 'Manoj Kumar Yadav', 'Assistant Engineer', '9800000062', 'ROLE_USER', 6],
            ['tp1', 'KDA-TP-001', 'Shweta Joshi', 'Assistant Town Planner', '9800000071', 'ROLE_USER', 7],
            ['tp2', 'KDA-TP-002', 'Gaurav Singhal', 'Deputy Town Planner', '9800000072', 'ROLE_USER', 7],
            ['legal1', 'KDA-LEG-001', 'Advocate Ritu Sharma', 'Legal Assistant', '9800000081', 'ROLE_USER', 8],
            ['legal2', 'KDA-LEG-002', 'Hemant Gupta', 'Law Officer', '9800000082', 'ROLE_USER', 8],
            ['eng3', 'KDA-ENG-003', 'Rohit Meena', 'Assistant Engineer (Civil)', '9800000063', 'ROLE_USER', 6],
            ['eng4', 'KDA-ENG-004', 'Sanjay Gurjar', 'Junior Engineer (Survey)', '9800000064', 'ROLE_USER', 6],
            ['legal3', 'KDA-LEG-003', 'Neelam Shekhawat', 'Legal Assistant', '9800000083', 'ROLE_USER', 8],
        ];
        $panelName = ['SUPER_ADMIN' => 'Super Admin', 'DATA_ENTRY' => 'Data Entry', 'OIC' => 'OIC', 'ROLE_USER' => 'Role User'];
        foreach ($users as $u) {
            $id = User::insert([
                'username' => $u[0], 'emp_code' => $u[1], 'name' => $u[2], 'designation' => $u[3], 'mobile' => $u[4], 'email' => $u[0] . '@kda.local',
                'password_hash' => Password::hash(self::PASSWORD), 'panel' => $u[5], 'role_id' => $u[6], 'is_active' => 1, 'must_change_password' => 0,
                'failed_attempts' => 0, 'created_by' => 1,
            ]);
            Notify::send($id, ['type' => 'ACCOUNT', 'tone' => 'success', 'icon' => 'fa-hand-sparkles', 'title' => 'Welcome to ' . Settings::get('app_name'),
                'message' => 'Your ' . $panelName[$u[5]] . ' account is ready.', 'link' => '/change-password']);
        }
        Role::updateWhere(['created_by' => 1], ['created_by' => null]);
        Audit::log('SYSTEM', null, 'DATABASE_SEEDED', null, ['roles' => count($roles), 'users' => count($users)], null, self::actor(1));

        // ---- masters ----
        foreach ([['PLOT', 'Plot', 'भूखंड', 'Open residential / commercial plot'], ['SHOP', 'Shop', 'दुकान', 'Built shop'], ['KIOSK', 'Kiosk', 'कियोस्क', 'Small kiosk / booth'],
                     ['FARMHOUSE', 'Farmhouse', 'फार्महाउस', 'Farmhouse land'], ['FLAT', 'Flat', 'फ्लैट', 'Flat in a building'], ['OTHER', 'Other', 'अन्य', 'Any other property']] as $i => $t) {
            PlotType::insert(['code' => $t[0], 'name' => $t[1], 'name_hi' => $t[2], 'description' => $t[3], 'sort_order' => $i + 1, 'is_active' => 1, 'is_system' => 0, 'created_by' => 1]);
        }
        foreach ([['LISTED', 'Listed', 'info', 'fa-list', 'Registered, not yet sent for verification'],
                     ['UNDER_VERIFICATION', 'Under Verification', 'warning', 'fa-hourglass-half', 'Round open — sections are verifying'],
                     ['AUCTION_READY', 'Auction Ready', 'success', 'fa-circle-check', 'All sections approved'],
                     ['NOT_READY', 'Not Ready', 'danger', 'fa-circle-xmark', 'A section rejected — needs correction and re-send'],
                     ['EXPIRED', 'Validity Expired', 'danger', 'fa-calendar-xmark', 'Auction Ready validity has passed — needs periodic re-verification'],
                     ['SENT_FOR_AUCTION', 'Sent for Auction', 'primary', 'fa-gavel', 'Handed over to the auction cell'],
                     ['SOLD', 'Sold', 'dark', 'fa-handshake', 'Sold in auction'], ['WITHHELD', 'Withheld', 'muted', 'fa-pause', 'Kept on hold by order'],
                     ['ARCHIVED', 'Archived', 'muted', 'fa-box-archive', 'No longer active']] as $i => $s) {
            Status::insert(['code' => $s[0], 'name' => $s[1], 'color' => $s[2], 'icon' => $s[3], 'description' => $s[4], 'sort_order' => $i + 1, 'is_active' => 1, 'is_system' => 1, 'created_by' => 1]);
        }

        $de = self::actor(2);
        $oic = self::actor(3);

        // ---- schemes (created by Data Entry, 20-16 days ago) ----
        $schemes = [
            ['Ravindra Nagar Awasiya Yojana', 'RNY-2019', 'RESIDENTIAL', 2019, ['Sector-1', 'Sector-2', 'Sector-3', 'Sector-4']],
            ['Vigyan Nagar Commercial Complex', 'VNC-2021', 'COMMERCIAL', 2021, ['Block-A', 'Block-B']],
            ['Kunhadi Industrial Area', 'KIA-2016', 'INDUSTRIAL', 2016, ['Phase-I', 'Phase-II', 'Phase-III']],
        ];
        $roleUsers = User::where(['panel' => 'ROLE_USER'], 'user_id');
        foreach ($schemes as $i => $s) {
            self::loginAt(2, (20 - $i * 2) * self::DAY + 30, 0);
            self::at((20 - $i * 2) * self::DAY);
            $sid = Scheme::insert(['scheme_name' => $s[0], 'scheme_code' => $s[1], 'scheme_type' => $s[2], 'year_of_scheme' => $s[3], 'is_active' => 1, 'created_by' => 2]);
            foreach ($s[4] as $j => $name) {
                Sector::insert(['scheme_id' => $sid, 'sector_name' => $name, 'sort_order' => $j + 1, 'is_active' => 1, 'created_by' => 2]);
            }
            // user 1 of each role (both for Engineering)
            foreach ($roleUsers as $u) {
                if (str_ends_with($u['username'], '1') || (int) $u['role_id'] === 6) {
                    SchemeUser::insert(['scheme_id' => $sid, 'role_id' => $u['role_id'], 'user_id' => $u['user_id'], 'is_active' => 1, 'created_by' => 2]);
                }
            }
            Audit::log('SCHEME', $sid, 'CREATED', null, ['scheme_name' => $s[0], 'scheme_code' => $s[1], 'scheme_type' => $s[2],
                'year_of_scheme' => $s[3], 'sectors' => implode(', ', $s[4])], null, $de);
        }
        self::at(15 * self::DAY);
        Audit::log('SCHEME', 1, 'UPDATED', ['year_of_scheme' => 2018], ['year_of_scheme' => 2019], null, $de);

        // ---- properties (10-6 days ago) ----
        $props = [
            [1, 1, '101', 'PLOT', 150, 'SQM'], [1, 1, '102', 'PLOT', 180, 'SQM'], [1, 2, '205-A', 'PLOT', 2400, 'SQFT'],
            [1, 3, '310', 'FLAT', 95.5, 'SQM'], [2, 5, 'SH-12', 'SHOP', 320, 'SQFT'], [2, 6, 'K-04', 'KIOSK', 12, 'SQM'],
            [3, 7, 'IND-21', 'PLOT', 1000, 'SQM'], [3, 8, 'IND-45', 'PLOT', 1500, 'SQM'], [1, 4, '412', 'PLOT', 200, 'SQM'],
            [2, 5, 'SH-15', 'SHOP', 280, 'SQFT'],
        ];
        self::loginAt(2, 10 * self::DAY + 20, 0);
        foreach ($props as $i => $x) {
            self::at(10 * self::DAY - $i * 3 * 60);
            $res = PropertyService::create([
                'scheme_id' => $x[0], 'sector_id' => $x[1],
                'plot_no' => $x[2], 'property_type' => $x[3], 'area_value' => $x[4], 'area_unit' => $x[5], 'area_sqm' => PropertyService::toSqm($x[4], $x[5]),
            ], 2);
            $p = $res['property'];
            Audit::log('PROPERTY', $p['property_id'], 'CREATED', null, ['property_code' => $p['property_code'], 'plot_no' => $p['plot_no'],
                'property_type' => $p['property_type'], 'area_value' => $p['area_value'], 'area_unit' => $p['area_unit'], 'status' => 'LISTED'], $p['property_id'], $de);
        }
        self::at(5 * self::DAY);
        Audit::log('PROPERTY', 2, 'UPDATED', ['area_value' => 175, 'remarks' => null], ['area_value' => 180, 'remarks' => 'Area corrected as per site map'], 2, $de);
        Property::update(2, ['remarks' => 'Area corrected as per site map']);

        // ---- verification: OIC sends 8 days ago, sections respond over the following days ----
        $clock = 8 * self::DAY;
        $respondAll = static function (int $propertyId, array $except = []) use (&$clock): void {
            $p = Property::find($propertyId);
            foreach (Task::where(['round_id' => $p['current_round_id']], 'task_id') as $t) {
                if (isset($except[$t['role_id']])) {
                    continue;
                }
                $uid = (int) TaskAssignee::where(['task_id' => $t['task_id']], 'assignee_id')[0]['user_id'];
                $clock -= 97;
                self::at($clock);
                Audit::as(self::actor($uid), static fn () => Verification::respond($t['task_id'], 'APPROVED', 'Record clear - no objection.', $uid));
            }
        };
        self::loginAt(3, 8 * self::DAY + 25, 0);
        self::at(8 * self::DAY);
        Audit::as($oic, static function (): void {
            foreach ([3, 4, 5, 6, 7, 8] as $id) {
                Verification::send($id, 3, null);
            }
        });
        // P3: 5 of 8 approved, Engineering + TP + Legal pending
        $respondAll(3, [6 => 1, 7 => 1, 8 => 1]);
        // P5, P6: all approved -> AUCTION_READY
        $respondAll(5);
        $respondAll(6);
        self::at(0);
        foreach ([[5, -2], [6, 6]] as [$pid, $days]) {
            $p = Property::find($pid);
            Property::update($pid, ['valid_till' => today($days)]);
            Round::update($p['ready_round_id'], ['valid_till' => today($days)]);
        }
        // P7: Engineering rejects -> NOT_READY
        $respondAll(7, [6 => 1, 8 => 1]);
        self::at(2 * self::DAY + 180);
        $p7 = Property::find(7);
        $eng = Task::first(['round_id' => $p7['current_round_id'], 'role_id' => 6]);
        Audit::as(self::actor(14), static fn () => Verification::respond($eng['task_id'], 'REJECTED',
            'Encroachment - temporary tin shed approx. 8 sq m on the front side.', 14, ['attachments' => ['site_inspection_photo.jpg', 'inspection_report.pdf']]));
        \App\Models\SiteInspection::insert(['task_id' => $eng['task_id'], 'area_value' => 992, 'area_unit' => 'SQM', 'area_sqm' => 992, 'recorded_value' => 1000,
            'recorded_unit' => 'SQM', 'difference' => 8, 'difference_pct' => 0.8, 'variance' => 'LESS', 'encroachment' => 'YES',
            'encroachment_details' => 'Temporary tin shed of about 8 sq m on the front side.', 'measured_by' => 14, 'measured_at' => now()]);
        self::sampleFile($eng, 14, 'site_inspection_photo.jpg', 'image', ['SITE INSPECTION — Plot IND-21', 'Kunhadi Industrial Area / Phase-I', 'Encroachment: tin shed ~8 sq m (front side)', 'Inspected by: Vikas Choudhary, JE']);
        self::sampleFile($eng, 14, 'inspection_report.pdf', 'pdf', ['KDA Engineering Cell - Site Inspection Report', 'Plot IND-21, Kunhadi Industrial Area, Phase-I',
            'Finding: temporary tin shed approx. 8 sq m on front side.', 'Recommendation: remove encroachment before auction.', 'Junior Engineer - Vikas Choudhary']);
        // P8: Legal rejects -> NOT_READY, then OIC re-sends (round R2 open)
        self::at(3 * self::DAY);
        $p8 = Property::find(8);
        $legal = Task::first(['round_id' => $p8['current_round_id'], 'role_id' => 8]);
        Audit::as(self::actor(18), static fn () => Verification::respond($legal['task_id'], 'REJECTED', 'Stay order in civil suit no. 45/2023 - pending.', 18,
            ['attachments' => ['stay_order_cs_45_2023.pdf']]));
        self::sampleFile($legal, 18, 'stay_order_cs_45_2023.pdf', 'pdf', ['Civil Court, Kota - Civil Suit No. 45/2023', 'Interim stay order in respect of Plot IND-45,',
            'Kunhadi Industrial Area, Phase-II.', 'Status as on date: stay operative.', 'Legal Cell, KDA']);
        self::loginAt(3, self::DAY + 15, 0);
        self::at(self::DAY);
        Audit::as($oic, static fn () => Verification::send(8, 3, 'Stay vacated by court order dated last month - record corrected.'));

        // ---- logins / logouts over the last week (incl. today) ----
        foreach ([[1, 6 * self::DAY + 600, 45], [14, 6 * self::DAY + 300, 30], [4, 5 * self::DAY + 200, 20], [16, 4 * self::DAY + 420, 25], [18, 3 * self::DAY + 60, 15],
                     [15, 2 * self::DAY + 240, 0], [8, self::DAY + 500, 20], [3, 300, 60], [2, 240, 0], [14, 150, 35], [18, 95, 0], [1, 40, 0]] as $x) {
            self::loginAt($x[0], $x[1], $x[2]);
        }
        self::at(2 * self::DAY + 100);
        LoginAttempt::insert(['username' => 'eng2', 'ip_address' => '127.0.0.1', 'success' => 0, 'attempted_at' => now()]);
        self::at(70);
        LoginAttempt::insert(['username' => 'tp1', 'ip_address' => '127.0.0.1', 'success' => 0, 'attempted_at' => now()]);

        // Super Admin security alerts
        self::at(2 * self::DAY + 95);
        Notify::send(Notify::panelUsers('SUPER_ADMIN'), ['type' => 'ACCOUNT', 'tone' => 'warning', 'icon' => 'fa-user-shield', 'title' => 'Failed login attempt — eng2',
            'message' => 'A wrong password was entered for eng2. The account locks after 5 failed attempts.', 'link' => '/admin/audit-logs?area=AUTH&quick=area:AUTH']);
        self::at(65);
        Notify::send(Notify::panelUsers('SUPER_ADMIN'), ['type' => 'ACCOUNT', 'tone' => 'warning', 'icon' => 'fa-user-shield', 'title' => 'Failed login attempt — tp1',
            'message' => 'A wrong password was entered for tp1 today.', 'link' => '/admin/audit-logs?area=AUTH&quick=area:AUTH']);
        self::at(30);
        Notify::send(Notify::panelUsers('SUPER_ADMIN'), ['type' => 'SYSTEM', 'tone' => 'info', 'icon' => 'fa-chart-line', 'title' => 'Daily activity summary',
            'message' => 'Verification is active; 2 properties are Auction Ready and 2 need re-verification. Open Activity & Audit for details.', 'link' => '/admin/audit-logs']);
        self::at(0);
        \App\Models\Notification::markReadBefore(now(-4 * self::DAY));
    }

    private static function loginAt(int $uid, float $minutesAgo, float $logoutAfter): void
    {
        $a = self::actor($uid);
        $u = User::find($uid);
        self::at($minutesAgo);
        LoginAttempt::insert(['username' => $u['username'], 'ip_address' => '127.0.0.1', 'success' => 1, 'attempted_at' => now()]);
        Audit::log('AUTH', $uid, 'LOGIN', null, ['message' => 'Login successful'], null, $a);
        User::update($uid, ['last_login_at' => now(), 'last_login_ip' => '127.0.0.1']);
        if ($logoutAfter) {
            self::at($minutesAgo - $logoutAfter);
            Audit::log('AUTH', $uid, 'LOGOUT', null, ['message' => 'User logged out'], null, $a);
        }
    }

    /** Tiny sample PDF / JPEG for the seeded rejections. */
    private static function sampleFile(array $task, int $by, string $name, string $kind, array $lines): void
    {
        $bytes = $kind === 'pdf' ? self::pdf($lines) : self::jpeg($lines);
        if ($bytes === null) {
            return;
        }
        $round = Round::find($task['round_id']);
        Uploads::storeBytes($bytes, $name, $kind === 'pdf' ? 'application/pdf' : 'image/jpeg',
            ['task_id' => $task['task_id'], 'round_id' => $task['round_id'], 'property_id' => $round['property_id'], 'by' => $by, 'at' => now()]);
    }

    private static function pdf(array $lines): string
    {
        $esc = static fn ($t) => preg_replace('/([\\\\()])/', '\\\\$1', $t);
        $stream = 'BT /F1 13 Tf 50 780 Td 18 TL ';
        foreach ($lines as $i => $l) {
            $stream .= ($i ? 'T* ' : '') . '(' . $esc($l) . ') Tj ';
        }
        $stream .= 'ET';
        $objs = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream", '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $out = "%PDF-1.4\n";
        $offs = [];
        foreach ($objs as $i => $o) {
            $offs[] = strlen($out);
            $out .= ($i + 1) . " 0 obj\n" . $o . "\nendobj\n";
        }
        $x = strlen($out);
        $out .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offs as $o) {
            $out .= str_pad((string) $o, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        return $out . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $x . "\n%%EOF";
    }

    private static function jpeg(array $lines): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        $im = imagecreatetruecolor(640, 400);
        for ($y = 0; $y < 400; $y++) { // gradient sky / ground
            $k = $y / 400;
            imageline($im, 0, $y, 639, $y, imagecolorallocate($im, (int) (138 + (217 - 138) * $k), (int) (167 + (199 - 167) * $k), (int) (200 + (154 - 200) * $k)));
        }
        imagefilledrectangle($im, 380, 170, 570, 320, imagecolorallocate($im, 107, 114, 128));
        imagefilledpolygon($im, [370, 175, 475, 120, 580, 175], imagecolorallocate($im, 156, 163, 175));
        imagesetthickness($im, 5);
        imagerectangle($im, 365, 112, 587, 328, imagecolorallocate($im, 185, 28, 28));
        imagefilledrectangle($im, 0, 0, 639, 119, imagecolorallocate($im, 15, 43, 82));
        $white = imagecolorallocate($im, 255, 255, 255);
        foreach ($lines as $i => $l) {
            imagestring($im, $i ? 4 : 5, 20, 14 + $i * 24, preg_replace('/[^\x20-\x7E]/', '-', $l), $white);
        }
        ob_start();
        imagejpeg($im, null, 70);
        imagedestroy($im);
        return (string) ob_get_clean();
    }
}
