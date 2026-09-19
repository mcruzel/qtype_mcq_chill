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
 * Editing form for the Drag-drop into text Chill question type.
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Drag-drop into text Chill question editing form definition.
 *
 * v1 compromise: the author types a plain sentence and ticks detected words
 * to turn them into gaps. Markers [[n]] are generated on save; they are not
 * typed by hand. The core HTML question-text editor is hidden in favour of
 * that textarea.
 *
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddto_chill_edit_form extends question_edit_form {
    /** @var int number of distractor rows shown when creating a question. */
    const NUM_CHOICES_START = 2;

    /** @var int number of distractor rows added each time the "add" button is used. */
    const NUM_CHOICES_ADD = 2;

    /** @var string[] the settings stored in the qtype_ddto_chill_options table. */
    const OPTION_FIELDS = ['negativemarking', 'allornothing', 'shuffleanswers'];

    /** @var string[] the negative markings offered, as fractions, from none to -100%. */
    const NEGATIVE_MARKING_OPTIONS = ['0.0', '-0.05', '-0.1', '-0.2', '-0.25', '-0.3333333', '-0.5', '-0.75', '-1.0'];

    /** @var stdClass|null reconstructed source of the question being edited, if any. */
    protected $reconstructed = null;

    #[\Override]
    protected function definition_inner($mform) {
        $mform->updateAttributes(['class' => 'mform qtype-ddto-chill-form']);
        $this->reconstructed = $this->reconstruct_current_question();

        $mform->addElement(
            'textarea',
            'sourcetext',
            get_string('sourcetext', 'qtype_ddto_chill'),
            [
                'rows' => 6,
                'cols' => 80,
                'placeholder' => get_string('sourcetextplaceholder', 'qtype_ddto_chill'),
                'class' => 'qtype-ddto-chill-sourcetext',
            ]
        );
        $mform->setType('sourcetext', PARAM_RAW);
        $mform->addHelpButton('sourcetext', 'sourcetext', 'qtype_ddto_chill');

        $mform->addElement(
            'hidden',
            'gapselection',
            $this->reconstructed ? implode(',', $this->reconstructed->gapindices) : ''
        );
        $mform->setType('gapselection', PARAM_SEQUENCE);

        $mform->addElement('header', 'gapshdr', get_string('gapwords', 'qtype_ddto_chill'));
        $mform->setExpanded('gapshdr', true);
        $mform->addElement(
            'static',
            'gapwordsintro',
            '',
            get_string('gapwordsintro', 'qtype_ddto_chill')
        );
        $mform->addHelpButton('gapwordsintro', 'gapwords', 'qtype_ddto_chill');

        $mform->addElement('html', $this->render_gap_list_html());

        $mform->registerNoSubmitButton('detectgaps');
        $mform->addElement('submit', 'detectgaps', get_string('detectgaps', 'qtype_ddto_chill'));

        $mform->addElement('header', 'distractorshdr', get_string('distractors', 'qtype_ddto_chill'));
        $mform->setExpanded('distractorshdr', true);
        $mform->addElement(
            'static',
            'distractorsintro',
            '',
            get_string('distractorsintro', 'qtype_ddto_chill')
        );

        $this->add_per_answer_fields(
            $mform,
            get_string('distractorno', 'qtype_ddto_chill', '{no}'),
            null,
            self::NUM_CHOICES_START,
            self::NUM_CHOICES_ADD
        );
        $this->init_dynamic_answer_rows();
        $this->init_gap_marking();

        $mform->addElement('header', 'gradinghdr', get_string('gradingoptions', 'qtype_ddto_chill'));
        $mform->setExpanded('gradinghdr', true);

        $currentnegativemarking = null;
        if (isset($this->question->options->negativemarking)) {
            $currentnegativemarking = (float) $this->question->options->negativemarking;
        }
        $mform->addElement(
            'select',
            'negativemarking',
            get_string('negativemarking', 'qtype_ddto_chill'),
            self::get_negative_marking_options($currentnegativemarking)
        );
        $mform->addHelpButton('negativemarking', 'negativemarking', 'qtype_ddto_chill');
        $mform->setDefault('negativemarking', $this->get_default_value('negativemarking', '0.0'));

        $mform->addElement(
            'advcheckbox',
            'allornothing',
            get_string('allornothing', 'qtype_ddto_chill'),
            null,
            null,
            [0, 1]
        );
        $mform->addHelpButton('allornothing', 'allornothing', 'qtype_ddto_chill');
        $mform->setDefault('allornothing', $this->get_default_value('allornothing', 0));

        $mform->addElement(
            'advcheckbox',
            'shuffleanswers',
            get_string('shuffleanswers', 'qtype_ddto_chill'),
            null,
            null,
            [0, 1]
        );
        $mform->addHelpButton('shuffleanswers', 'shuffleanswers', 'qtype_ddto_chill');
        $mform->setDefault('shuffleanswers', $this->get_default_value('shuffleanswers', 1));
    }

    /**
     * Reconstruct the source sentence of the question being edited, if any.
     *
     * @return stdClass|null
     */
    protected function reconstruct_current_question(): ?stdClass {
        if (empty($this->question->options->choices) || empty($this->question->questiontext)) {
            $source = $this->optional_param('sourcetext', '', PARAM_RAW);
            if ($source === '') {
                return null;
            }
            $words = qtype_ddto_chill::word_list($source);
            $selection = $this->optional_param('gapselection', '', PARAM_SEQUENCE);
            $gapindices = [];
            if ($selection !== '') {
                foreach (explode(',', $selection) as $bit) {
                    if ($bit !== '' && ctype_digit($bit)) {
                        $gapindices[] = (int) $bit;
                    }
                }
            } else {
                $isgap = optional_param_array('isgap', [], PARAM_INT);
                foreach ($isgap as $index => $value) {
                    if (!empty($value)) {
                        $gapindices[] = (int) $index;
                    }
                }
            }
            $result = new stdClass();
            $result->sourcetext = $source;
            $result->words = $words;
            $result->gapindices = $gapindices;
            $result->labels = [];
            foreach ($words as $i => $word) {
                $result->labels[] = ['text' => $word, 'gap' => in_array($i, $gapindices, true)];
            }
            return $result;
        }

        $text = $this->question->questiontext;
        if (is_array($text)) {
            $text = $text['text'] ?? '';
        }
        return qtype_ddto_chill::reconstruct_source($text, $this->question->options->choices);
    }

    /**
     * Markup of the word list (checkboxes), including a noscript fallback.
     *
     * @return string HTML.
     */
    protected function render_gap_list_html(): string {
        $labels = $this->reconstructed->labels ?? [];
        $items = '';
        foreach ($labels as $index => $label) {
            $boxattrs = [
                'type' => 'checkbox',
                'name' => 'isgap[' . $index . ']',
                'value' => '1',
                'class' => 'qtype-ddto-chill-isgap',
                'data-word-index' => $index,
            ];
            if (!empty($label['gap'])) {
                $boxattrs['checked'] = 'checked';
            }
            $items .= html_writer::tag(
                'label',
                html_writer::empty_tag('input', $boxattrs) . ' '
                    . s($label['text']) . ' — ' . get_string('makegap', 'qtype_ddto_chill'),
                ['class' => 'qtype-ddto-chill-gapword']
            );
        }

        return html_writer::div($items, 'qtype-ddto-chill-gaplist', ['id' => 'qtype-ddto-chill-gaplist']);
    }

    /**
     * Load the JS that adds and removes distractor rows without a page reload.
     */
    protected function init_dynamic_answer_rows(): void {
        global $PAGE;

        if (!$PAGE->has_set_url()) {
            return;
        }

        $startindex = self::NUM_CHOICES_START;
        if ($this->reconstructed && !empty($this->question->options->choices)) {
            $distractors = 0;
            foreach ($this->question->options->choices as $choice) {
                if (!qtype_ddto_chill::is_correct_choice($choice->fraction)) {
                    $distractors++;
                }
            }
            if ($distractors > 0) {
                $startindex = $distractors;
            }
        }

        $PAGE->requires->js_call_amd('qtype_ddto_chill/answer_rows', 'init', [[
            'groupName' => 'answergroup',
            'repeatCountName' => 'noanswers',
            'phpAddButtonName' => 'addanswers',
            'startIndex' => $startindex,
            'minChoices' => 0,
            'addLabel' => get_string('addanswer', 'qtype_ddto_chill'),
            'removeLabel' => get_string('removeanswer', 'qtype_ddto_chill'),
            'choiceLabel' => get_string('distractorno', 'qtype_ddto_chill', '{$a}'),
            'dragHandleLabel' => get_string('draghandle', 'qtype_ddto_chill'),
            'moveUpLabel' => get_string('movechoiceup', 'qtype_ddto_chill'),
            'moveDownLabel' => get_string('movechoicedown', 'qtype_ddto_chill'),
        ]]);
    }

    /**
     * Load the JS that detects words in the source sentence and hides the core editor.
     */
    protected function init_gap_marking(): void {
        global $PAGE;

        if (!$PAGE->has_set_url()) {
            return;
        }

        $PAGE->requires->js_call_amd('qtype_ddto_chill/gap_marking', 'init', [[
            'sourceName' => 'sourcetext',
            'selectionName' => 'gapselection',
            'listId' => 'qtype-ddto-chill-gaplist',
            'detectButtonName' => 'detectgaps',
            'makeGapLabel' => get_string('makegap', 'qtype_ddto_chill'),
            'questionTextItemId' => 'fitem_id_questiontext',
        ]]);
    }

    #[\Override]
    protected function get_per_answer_fields($mform, $label, $gradeoptions, &$repeatedoptions, &$answersoption) {
        $choice = [];
        $choice[] = $mform->createElement('text', 'answer', $label, [
            'size' => 50,
            'placeholder' => get_string('choiceplaceholder', 'qtype_ddto_chill'),
        ]);

        $repeated = [];
        $repeated[] = $mform->createElement('group', 'answergroup', $label, $choice, null, false);

        $repeatedoptions['answer']['type'] = PARAM_RAW;
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
        }

        $reconstructed = $this->reconstructed;
        if (!$reconstructed && !empty($question->questiontext) && !empty($question->options->choices)) {
            $text = is_array($question->questiontext) ? ($question->questiontext['text'] ?? '') : $question->questiontext;
            $reconstructed = qtype_ddto_chill::reconstruct_source($text, $question->options->choices);
        }
        if ($reconstructed) {
            $question->sourcetext = $reconstructed->sourcetext;
            $question->gapselection = implode(',', $reconstructed->gapindices);
        }

        if (!empty($question->options->choices)) {
            $key = 0;
            $question->answer = [];
            foreach ($question->options->choices as $choice) {
                if (qtype_ddto_chill::is_correct_choice($choice->fraction)) {
                    continue;
                }
                $question->answer[$key] = $choice->text;
                $key++;
            }
        }

        if (isset($question->negativemarking)) {
            $question->negativemarking = self::negative_marking_key(
                qtype_ddto_chill::clean_negative_marking($question->negativemarking)
            );
        }

        return $question;
    }

    #[\Override]
    public function validation($data, $files) {
        if (empty($data['questiontext']['text'])) {
            $data['questiontext']['text'] = 'x';
            $data['questiontext']['format'] = FORMAT_HTML;
        } else if (html_is_blank($data['questiontext']['text'])) {
            $data['questiontext']['text'] = 'x';
        }
        $errors = parent::validation($data, $files);
        unset($errors['questiontext']);

        $source = trim((string) ($data['sourcetext'] ?? ''));
        if ($source === '') {
            $errors['sourcetext'] = get_string('errnosourcetext', 'qtype_ddto_chill');
            return $errors;
        }

        $fromform = (object) $data;
        $indices = qtype_ddto_chill::gap_indices_from_form($fromform);
        if (empty($indices)) {
            $errors['gapwordsintro'] = get_string('errnogap', 'qtype_ddto_chill');
        }

        return $errors;
    }

    #[\Override]
    public function qtype() {
        return 'ddto_chill';
    }

    /**
     * The choices offered for the negative marking, from none (0) to -100%.
     *
     * @param float|null $current the value currently stored for the question, if any.
     * @return array fraction => label.
     */
    public static function get_negative_marking_options(?float $current = null): array {
        $options = [];
        foreach (self::NEGATIVE_MARKING_OPTIONS as $fraction) {
            if ((float) $fraction == 0) {
                $options[$fraction] = get_string('none');
            } else {
                $options[$fraction] = format_float(100 * (float) $fraction, 5, true, true) . '%';
            }
        }

        if ($current !== null && $current < 0 && !self::has_fraction_option($options, $current)) {
            $options[self::negative_marking_key($current)] = format_float(100 * $current, 5, true, true) . '%';
            uksort($options, function ($a, $b) {
                return (float) $b <=> (float) $a;
            });
        }

        return $options;
    }

    /**
     * The key of the negative marking select that denotes a stored value.
     *
     * @param float $value the stored fraction.
     * @return string the matching key of the select, or the value with up to 7 decimals when not listed.
     */
    public static function negative_marking_key(float $value): string {
        foreach (self::NEGATIVE_MARKING_OPTIONS as $key) {
            if (abs((float) $key - $value) < 0.0000005) {
                return $key;
            }
        }
        return rtrim(rtrim(number_format($value, 7, '.', ''), '0'), '.');
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
