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
 * Editing form for the QCM Chill question type.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * QCM Chill question editing form definition.
 *
 * On top of the standard question fields the form only contains: one line of
 * plain text per choice with a "correct answer" checkbox, the negative marking
 * applied to each wrong choice selected, the all-or-nothing option and the
 * shuffling option.
 *
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_mcq_chill_edit_form extends question_edit_form {
    /** @var int number of choice rows shown when creating a question. */
    const NUM_CHOICES_START = 4;

    /** @var int number of choice rows added each time the "add" button is used. */
    const NUM_CHOICES_ADD = 2;

    /** @var string[] the settings stored in the qtype_mcq_chill_options table. */
    const OPTION_FIELDS = ['negativemarking', 'allornothing', 'shuffleanswers'];

    #[\Override]
    protected function definition_inner($mform) {
        $this->add_per_answer_fields(
            $mform,
            get_string('choiceno', 'qtype_mcq_chill', '{no}'),
            null,
            self::NUM_CHOICES_START,
            self::NUM_CHOICES_ADD
        );

        $mform->addElement('header', 'gradinghdr', get_string('gradingoptions', 'qtype_mcq_chill'));
        $mform->setExpanded('gradinghdr', true);

        $currentnegativemarking = null;
        if (isset($this->question->options->negativemarking)) {
            $currentnegativemarking = (float) $this->question->options->negativemarking;
        }
        $mform->addElement(
            'select',
            'negativemarking',
            get_string('negativemarking', 'qtype_mcq_chill'),
            self::get_negative_marking_options($currentnegativemarking)
        );
        $mform->addHelpButton('negativemarking', 'negativemarking', 'qtype_mcq_chill');
        $mform->setDefault('negativemarking', $this->get_default_value('negativemarking', '0.0'));

        $mform->addElement(
            'advcheckbox',
            'allornothing',
            get_string('allornothing', 'qtype_mcq_chill'),
            null,
            null,
            [0, 1]
        );
        $mform->addHelpButton('allornothing', 'allornothing', 'qtype_mcq_chill');
        $mform->setDefault('allornothing', $this->get_default_value('allornothing', 0));

        $mform->addElement(
            'advcheckbox',
            'shuffleanswers',
            get_string('shuffleanswers', 'qtype_mcq_chill'),
            null,
            null,
            [0, 1]
        );
        $mform->addHelpButton('shuffleanswers', 'shuffleanswers', 'qtype_mcq_chill');
        $mform->setDefault('shuffleanswers', $this->get_default_value('shuffleanswers', 1));
    }

    #[\Override]
    protected function get_per_answer_fields($mform, $label, $gradeoptions, &$repeatedoptions, &$answersoption) {
        $choice = [];
        $choice[] = $mform->createElement('text', 'answer', $label, ['size' => 50]);
        $choice[] = $mform->createElement(
            'advcheckbox',
            'fraction',
            '',
            get_string('correctanswer', 'qtype_mcq_chill'),
            null,
            [0, 1]
        );

        $repeated = [];
        $repeated[] = $mform->createElement('group', 'answergroup', $label, $choice, null, false);

        $repeatedoptions['answer']['type'] = PARAM_RAW;
        $repeatedoptions['fraction']['type'] = PARAM_INT;
        $repeatedoptions['fraction']['default'] = 0;
        $answersoption = 'answers';
        return $repeated;
    }

    #[\Override]
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);

        if (!empty($question->options)) {
            foreach (self::OPTION_FIELDS as $field) {
                if (isset($question->options->$field)) {
                    $question->$field = $question->options->$field;
                }
            }
            if (!empty($question->options->answers)) {
                $key = 0;
                foreach ($question->options->answers as $answer) {
                    $question->answer[$key] = $answer->answer;
                    $question->fraction[$key] = qtype_mcq_chill::is_correct_choice($answer->fraction) ? 1 : 0;
                    // The repeated elements set a flat default for each checkbox, which would otherwise
                    // take precedence over the stored value (same workaround as the core question types).
                    unset($this->_form->_defaultValues["fraction[{$key}]"]);
                    $key++;
                }
            }
        }

        if (isset($question->negativemarking)) {
            // Match the keys of the select element (see get_negative_marking_options()).
            $question->negativemarking = (string) qtype_mcq_chill::clean_negative_marking($question->negativemarking);
        }

        return $question;
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $numchoices = 0;
        $numcorrect = 0;
        foreach ($data['answer'] ?? [] as $key => $answer) {
            $iscorrect = !empty($data['fraction'][$key]);
            if (trim((string) $answer) === '') {
                if ($iscorrect) {
                    $errors["answergroup[{$key}]"] = get_string('errcorrectblank', 'qtype_mcq_chill');
                }
                continue;
            }
            $numchoices++;
            if ($iscorrect) {
                $numcorrect++;
            }
        }

        if ($numchoices < qtype_mcq_chill::MIN_CHOICES) {
            for ($key = $numchoices; $key < qtype_mcq_chill::MIN_CHOICES; $key++) {
                $errors["answergroup[{$key}]"] = get_string('notenoughchoices', 'qtype_mcq_chill', qtype_mcq_chill::MIN_CHOICES);
            }
        } else if ($numcorrect === 0) {
            $errors['answergroup[0]'] = get_string('errnocorrectanswer', 'qtype_mcq_chill');
        }

        return $errors;
    }

    #[\Override]
    public function qtype() {
        return 'mcq_chill';
    }

    /**
     * The choices offered for the negative marking, from none (0) to -100%.
     *
     * The keys are the fractions as strings, like the grade selects of the core
     * question types. A stored value that is not in the standard list (for
     * instance after an XML import) is added so that it is not silently lost.
     *
     * @param float|null $current the value currently stored for the question, if any.
     * @return array fraction => label.
     */
    public static function get_negative_marking_options(?float $current = null): array {
        $options = ['0.0' => get_string('none')];
        foreach (question_bank::fraction_options_full() as $fraction => $label) {
            if ((float) $fraction < 0) {
                $options[$fraction] = $label;
            }
        }

        if ($current !== null && $current < 0 && !self::has_fraction_option($options, $current)) {
            $options[(string) $current] = format_float(100 * $current, 5, true, true) . '%';
            uksort($options, function ($a, $b) {
                return (float) $b <=> (float) $a;
            });
        }

        return $options;
    }

    /**
     * Whether a fraction is already one of the select options.
     *
     * @param array $options fraction => label.
     * @param float $fraction the fraction to look for.
     * @return bool true if present.
     */
    protected static function has_fraction_option(array $options, float $fraction): bool {
        foreach (array_keys($options) as $key) {
            if (abs((float) $key - $fraction) < 0.0000005) {
                return true;
            }
        }
        return false;
    }
}
