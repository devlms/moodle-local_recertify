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

namespace local_recertify\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\report\{column, filter};
use lang_string;

/**
 * Report builder entity for archived certifications.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certification extends base {
    /**
     * Tables used by the entity.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['local_recertify_history'];
    }

    /**
     * Entity title.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entitycertification', 'local_recertify');
    }

    /**
     * Add the columns, filters and conditions.
     *
     * @return base
     */
    public function initialise(): base {
        $alias = $this->get_table_alias('local_recertify_history');
        foreach (['timecompleted' => 'completed', 'timeexpired' => 'expired'] as $field => $label) {
            $this->add_column((new column($field, new lang_string($label, 'local_recertify'), $this->get_entity_name()))
                ->add_joins($this->get_joins())
                ->set_type(column::TYPE_TIMESTAMP)
                ->add_field("{$alias}.{$field}")
                ->set_is_sortable(true)
                ->add_callback(static fn(?int $value): string => $value ? userdate($value) : ''));
            $filter = (new filter(
                date::class,
                $field,
                new lang_string($label, 'local_recertify'),
                $this->get_entity_name(),
                "{$alias}.{$field}"
            ))->add_joins($this->get_joins());
            $this->add_filter($filter)->add_condition($filter);
        }
        $this->add_column((new column('finalgrade', new lang_string('finalgrade', 'local_recertify'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$alias}.finalgrade")
            ->set_is_sortable(true)
            ->add_callback(static fn(?float $value): string => $value === null ? '' : format_float($value, 2)));
        $filter = (new filter(
            number::class,
            'finalgrade',
            new lang_string('finalgrade', 'local_recertify'),
            $this->get_entity_name(),
            "{$alias}.finalgrade"
        ))->add_joins($this->get_joins());
        $this->add_filter($filter)->add_condition($filter);
        return $this;
    }
}
