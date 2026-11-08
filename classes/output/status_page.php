<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_recertify\output;

use local_recertify\local\{schedule, settings};
use moodle_url;
use renderer_base;
use stdClass;

/**
 * The recertification status of every learner in a course.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_page implements \renderable, \templatable {
    /** @var array State => [sort order, badge classes, tile tone]. */
    private const STATES = [
        'expired' => [0, 'bg-danger text-white', 'danger'],
        'expiring' => [1, 'bg-warning text-dark', 'warning'],
        'valid' => [2, 'bg-success text-white', 'success'],
        'none' => [3, 'bg-secondary text-dark', 'secondary'],
    ];

    /** @var float Share of the timeline, in percent, for learners whose certification has expired. */
    private const EXPIREDZONE = 12;

    /** @var string[] Tiles, in the order shown. */
    private const TILES = ['valid', 'expiring', 'expired', 'none'];

    /**
     * Constructor.
     *
     * @param stdClass $course
     * @param stdClass $cfg from settings::get()
     * @param stdClass[] $users learners in the course
     * @param int $now
     * @param string $filter a state, or '' for everyone
     * @param bool $canmanage
     */
    public function __construct(
        /** @var stdClass */
        private stdClass $course,
        /** @var stdClass */
        private stdClass $cfg,
        /** @var stdClass[] */
        private array $users,
        /** @var int */
        private int $now,
        /** @var string */
        private string $filter,
        /** @var bool */
        private bool $canmanage
    ) {
    }

    /**
     * "Today", "In 5 days", "3 days ago" and so on, by whole days.
     *
     * @param int $time
     * @param int $now
     * @return string
     */
    public static function relative(int $time, int $now): string {
        $days = (int) floor(($time - $now) / DAYSECS);
        if ($days === 0) {
            return get_string('rel:today', 'local_recertify');
        }
        if ($days === 1) {
            return get_string('rel:tomorrow', 'local_recertify');
        }
        if ($days === -1) {
            return get_string('rel:yesterday', 'local_recertify');
        }
        return get_string($days > 0 ? 'rel:in' : 'rel:ago', 'local_recertify', abs($days));
    }

    /**
     * Export for local_recertify/status.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $filter = isset(self::STATES[$this->filter]) ? $this->filter : '';
        $url = new moodle_url('/local/recertify/status.php', ['courseid' => $this->course->id]);
        $counts = array_fill_keys(array_keys(self::STATES), 0);
        $due = 0;
        $rows = [];
        $points = [];
        $period = max(1, (int) $this->cfg->perioddays);
        foreach ($this->users as $user) {
            $status = schedule::status($this->cfg, (int) $user->id, $this->now);
            $counts[$status->state]++;
            if ($status->expires && $status->expires <= $this->now + 7 * DAYSECS) {
                $due++;
            }
            // Where the learner sits on the expiry timeline: expired on the left, then today to a full period ahead.
            if ($status->state === 'expired' || ($status->expires && $status->expires <= $this->now)) {
                $points[] = ['pos' => self::EXPIREDZONE / 2, 'tone' => 'danger', 'label' => fullname($user)];
            } else if ($status->expires) {
                $daysleft = ($status->expires - $this->now) / DAYSECS;
                $pos = self::EXPIREDZONE + (100 - self::EXPIREDZONE) * min(1, $daysleft / $period);
                $tone = $status->state === 'expiring' ? 'warning' : 'success';
                $points[] = ['pos' => $pos, 'tone' => $tone, 'label' => fullname($user) . ', ' .
                    userdate($status->expires, get_string('strftimedate', 'langconfig'))];
            }
            if ($filter && $status->state !== $filter) {
                continue;
            }
            [$order, $badge] = self::STATES[$status->state];
            $rows[] = [
                'sort' => [$order, $status->expires ?: PHP_INT_MAX, fullname($user)],
                'name' => fullname($user),
                'picture' => $output->user_picture($user, ['size' => 35, 'link' => false, 'alttext' => false]),
                'statekey' => $status->state,
                'state' => get_string('state:' . $status->state, 'local_recertify'),
                'badge' => 'badge ' . $badge,
                'completed' => $status->timecompleted
                    ? userdate($status->timecompleted, get_string('strftimedate', 'langconfig')) : '',
                'expires' => $status->expires ? userdate($status->expires, get_string('strftimedate', 'langconfig')) : '',
                'expiresin' => $status->expires ? self::relative($status->expires, $this->now) : '',
                'past' => $status->certifications,
            ];
        }
        usort($rows, fn($a, $b) => $a['sort'] <=> $b['sort']);
        foreach ($rows as &$row) {
            unset($row['sort']);
        }

        $tiles = [];
        foreach (self::TILES as $state) {
            $tiles[] = [
                'key' => $state,
                'label' => get_string('tile:' . $state, 'local_recertify'),
                'count' => $counts[$state],
                'tone' => self::STATES[$state][2],
                'url' => (new moodle_url($url, ['state' => $state]))->out(false),
                'active' => $state === $filter,
            ];
        }

        // Close dots go on separate lanes so none hides another.
        usort($points, fn($a, $b) => $a['pos'] <=> $b['pos']);
        $lanes = [];
        foreach ($points as &$point) {
            $lane = 0;
            while (isset($lanes[$lane]) && $point['pos'] - $lanes[$lane] < 1.6) {
                $lane++;
            }
            $lanes[$lane] = $point['pos'];
            $point['lane'] = min($lane, 3);
            $point['pos'] = round($point['pos'], 2);
        }
        unset($point);
        $reminderdays = settings::reminder_days($this->cfg);
        $window = $reminderdays ? max($reminderdays) : 0;

        $enabled = (bool) $this->cfg->enabled;
        $key = ($enabled ? 'nextweek' : 'nextweekoff') . ($due === 0 ? '0' : ($due === 1 ? '1' : ''));
        $days = settings::reminder_days($this->cfg);
        $last = array_pop($days);
        $reminders = $days ? get_string('andlist', 'local_recertify', ['list' => implode(', ', $days), 'last' => $last])
            : (string) $last;

        return [
            'enabled' => $enabled,
            'canmanage' => $this->canmanage,
            'period' => (int) $this->cfg->perioddays,
            'reminders' => $reminders,
            'periodtext' => get_string(
                'period',
                'local_recertify',
                ['period' => (int) $this->cfg->perioddays, 'reminders' => $reminders]
            ),
            'tiles' => $tiles,
            'points' => $points,
            'haspoints' => (bool) $points,
            'expiredzone' => self::EXPIREDZONE,
            'windowwidth' => round((100 - self::EXPIREDZONE) * min(1, $window / $period), 2),
            'windowdays' => $window,
            'periodend' => get_string('rel:in', 'local_recertify', $period),
            'nextweek' => get_string($key, 'local_recertify', $due),
            'hasdue' => $enabled && $due > 0,
            'rows' => array_values($rows),
            'hasrows' => (bool) $rows,
            'filtered' => $filter !== '',
            'allurl' => $url->out(false),
        ];
    }
}
