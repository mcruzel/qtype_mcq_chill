<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Restore support for the QCM Chill question type.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/moodle2/restore_qtype_extrafields_plugin.class.php');

/**
 * Restore plugin class that provides the information needed to restore one QCM Chill question.
 *
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_mcq_chill_plugin extends restore_qtype_extrafields_plugin {
    /**
     * Process the qtype/mcq_chill element: the settings of the question.
     *
     * @param array $data the settings read from the backup.
     */
    public function process_mcq_chill($data) {
        $this->really_process_extra_question_fields($data);
    }

    /**
     * Map the answer ids stored in the attempt data of the question.
     *
     * The order of the choices is stored, at the start of an attempt, as a
     * list of question_answers ids in the "_order" variable.
     *
     * @param int $questionid the new question id.
     * @param int $sequencenumber the sequence number of the step.
     * @param array $response the response data.
     * @return array the recoded response data.
     */
    #[\Override]
    public function recode_response($questionid, $sequencenumber, array $response) {
        if (array_key_exists('_order', $response)) {
            $response['_order'] = $this->recode_choice_order($response['_order']);
        }
        return $response;
    }

    /**
     * Recode the choice order as stored in the question attempt step data.
     *
     * @param string $order the original order, as a comma-separated list of answer ids.
     * @return string the recoded order.
     */
    protected function recode_choice_order($order) {
        $neworder = [];
        foreach (explode(',', $order) as $id) {
            if ($newid = $this->get_mappingid('question_answer', $id)) {
                $neworder[] = $newid;
            }
        }
        return implode(',', $neworder);
    }
}
