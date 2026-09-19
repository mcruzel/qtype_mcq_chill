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

namespace qtype_ddto_chill;

use qtype_ddto_chill;
use qtype_ddto_chill_edit_form;
use question_bank;
use question_possible_response;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/ddto_chill/questiontype.php');
require_once($CFG->dirroot . '/question/type/ddto_chill/edit_ddto_chill_form.php');

/**
 * Unit tests for the Drag-drop into text Chill question type class.
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_ddto_chill
 * @covers     \qtype_ddto_chill_edit_form
 */
final class questiontype_test extends \advanced_testcase {
    /** @var qtype_ddto_chill the question type being tested. */
    protected $qtype;

    protected function setUp(): void {
        parent::setUp();
        $this->qtype = question_bank::get_qtype('ddto_chill');
    }

    protected function tearDown(): void {
        $this->qtype = null;
        parent::tearDown();
    }

    public function test_name(): void {
        $this->assertEquals('ddto_chill', $this->qtype->name());
    }

    public function test_extra_question_fields(): void {
        $this->assertEquals(
            ['qtype_ddto_chill_options', 'negativemarking', 'allornothing', 'shuffleanswers'],
            $this->qtype->extra_question_fields()
        );
        $this->assertEquals('questionid', $this->qtype->questionid_column_name());
    }

    public function test_tokenize_and_build_from_source(): void {
        $source = 'The cat sat on the mat';
        $this->assertEquals(['The', 'cat', 'sat', 'on', 'the', 'mat'], qtype_ddto_chill::word_list($source));

        $built = qtype_ddto_chill::build_from_source($source, [1, 5], ['dog', 'hat']);
        $this->assertEquals('The [[1]] sat on the [[2]]', $built->questiontext);
        $this->assertEquals(['cat', 'mat', 'dog', 'hat'], $built->answers);
        $this->assertEquals([1.0, 1.0, 0.0, 0.0], $built->fractions);
        $this->assertEquals(2, $built->numgaps);
    }

    public function test_reconstruct_source(): void {
        $choices = [
            1 => (object) ['text' => 'cat'],
            2 => (object) ['text' => 'mat'],
        ];
        $reconstructed = qtype_ddto_chill::reconstruct_source('The [[1]] sat on the [[2]]', $choices);
        $this->assertEquals('The cat sat on the mat', trim($reconstructed->sourcetext));
        $this->assertEquals(['The', 'cat', 'sat', 'on', 'the', 'mat'], $reconstructed->words);
        $this->assertEquals([1, 5], $reconstructed->gapindices);
    }

    /**
     * Cases for test_clean_negative_marking.
     *
     * @return array[]
     */
    public static function clean_negative_marking_provider(): array {
        return [
            'valid fraction' => ['-0.5', -0.5],
            'below the floor' => [-1.5, -1.0],
            'positive value' => [0.5, 0.0],
            'not numeric' => ['abc', 0.0],
        ];
    }

    /**
     * Test the normalisation of the negative marking.
     *
     * @dataProvider clean_negative_marking_provider
     * @param mixed $value the raw value.
     * @param float $expected the cleaned value.
     */
    public function test_clean_negative_marking($value, float $expected): void {
        $this->assertEqualsWithDelta($expected, qtype_ddto_chill::clean_negative_marking($value), 0.0000001);
    }

    public function test_get_possible_responses(): void {
        $qdata = test_question_maker::get_question_data('ddto_chill', 'catmat');
        $possible = $this->qtype->get_possible_responses($qdata);
        $this->assertCount(2, $possible);
        $this->assertEqualsWithDelta(0.5, $possible[1][1]->fraction, 0.0000001);
        $this->assertEqualsWithDelta(-0.5, $possible[1][3]->fraction, 0.0000001);
        $this->assertEquals(question_possible_response::no_response(), $possible[1][null]);
    }

    public function test_question_saving(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $formdata = test_question_maker::get_question_form_data('ddto_chill', 'catmat');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $formdata->category = "{$cat->id},{$cat->contextid}";

        $question = new \stdClass();
        $question->qtype = 'ddto_chill';
        $question->createdby = 0;
        $question->idnumber = null;
        $question->status = $formdata->status;
        $saved = $this->qtype->save_question($question, $formdata);

        $loaded = question_bank::load_question($saved->id);
        $this->assertInstanceOf(\qtype_ddto_chill_question::class, $loaded);
        $this->assertEqualsWithDelta(-0.5, $loaded->negativemarking, 0.0000001);
        $this->assertEquals(0, $loaded->allornothing);
        $this->assertEquals(1, $loaded->shuffleanswers);
        $this->assertEquals('The [[1]] sat on the [[2]]', $loaded->questiontext);
        $this->assertCount(4, $loaded->choices);
        $this->assertEquals('cat', $loaded->choices[1]->text);
        $this->assertEquals(1.0, $loaded->choices[1]->fraction);
        $this->assertEquals('hat', $loaded->choices[4]->text);
        $this->assertEquals([1 => 1, 2 => 2], $loaded->places);
    }

    public function test_delete_question(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $question = $generator->create_question('ddto_chill', 'catmat', ['category' => $cat->id]);

        $this->assertEquals(1, $DB->count_records('qtype_ddto_chill_options', ['questionid' => $question->id]));
        $this->assertEquals(4, $DB->count_records('qtype_ddto_chill_choices', ['questionid' => $question->id]));

        question_delete_question($question->id);

        $this->assertEquals(0, $DB->count_records('qtype_ddto_chill_options', ['questionid' => $question->id]));
        $this->assertEquals(0, $DB->count_records('qtype_ddto_chill_choices', ['questionid' => $question->id]));
    }

    public function test_xml_import(): void {
        $xml = '  <question type="ddto_chill">
    <name>
      <text>DDTO Chill cat mat</text>
    </name>
    <questiontext format="html">
      <text>The [[1]] sat on the [[2]]</text>
    </questiontext>
    <generalfeedback format="html">
      <text>The cat sat on the mat.</text>
    </generalfeedback>
    <defaultgrade>1</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <negativemarking>-0.5</negativemarking>
    <allornothing>0</allornothing>
    <shuffleanswers>1</shuffleanswers>
    <answer fraction="100" format="html">
      <text>cat</text>
      <choicegroup>1</choicegroup>
    </answer>
    <answer fraction="100" format="plain_text">
      <text>mat</text>
      <choicegroup>1</choicegroup>
    </answer>
    <answer fraction="0" format="html">
      <text><![CDATA[<b>dog</b>]]></text>
      <choicegroup>1</choicegroup>
    </answer>
    <answer fraction="0" format="plain_text">
      <text>hat</text>
      <choicegroup>1</choicegroup>
    </answer>
  </question>';
        $importer = new \qformat_xml();
        $q = $importer->try_importing_using_qtypes($this->parse_xml($xml)['question'], null, null, 'ddto_chill');

        $this->assertEquals('ddto_chill', $q->qtype);
        $this->assertEquals('DDTO Chill cat mat', $q->name);
        $this->assertEquals('The [[1]] sat on the [[2]]', $q->questiontext);
        $this->assertEquals('-0.5', $q->negativemarking);
        $this->assertEquals(['cat', 'mat', 'dog', 'hat'], $q->answer);
        $this->assertEquals([1, 1, 0, 0], $q->fraction);
    }

    public function test_xml_import_of_an_unusable_question(): void {
        $xml = '<question type="ddto_chill">
    <name><text>Unusable</text></name>
    <questiontext format="html"><text>No gaps here.</text></questiontext>
    <answer fraction="100"><text>cat</text></answer>
  </question>';
        $importer = new \qformat_xml();
        $this->expectOutputRegex('~' . preg_quote(get_string('tooshort', 'qtype_ddto_chill'), '~') . '~');
        $q = $importer->try_importing_using_qtypes($this->parse_xml($xml)['question'], null, null, 'ddto_chill');
        $this->assertFalse($q);
        $this->assertGreaterThanOrEqual(1, $importer->importerrors);
    }

    public function test_xml_export(): void {
        $qdata = test_question_maker::get_question_data('ddto_chill', 'catmat');
        $qdata->id = 123;
        $qdata->contextid = \context_system::instance()->id;

        $exporter = new \qformat_xml();
        $xml = $exporter->writequestion($qdata);

        $this->assertStringContainsString('<question type="ddto_chill">', $xml);
        $this->assertStringContainsString('<negativemarking>-0.5</negativemarking>', $xml);
        $this->assertStringContainsString('<allornothing>0</allornothing>', $xml);
        $this->assertStringContainsString('<shuffleanswers>1</shuffleanswers>', $xml);
        $this->assertStringContainsString('<text>The [[1]] sat on the [[2]]</text>', $xml);
        $this->assertStringContainsString('<text>cat</text>', $xml);
        $this->assertStringContainsString('<choicegroup>1</choicegroup>', $xml);
        $this->assertStringContainsString('fraction="100"', $xml);
        $this->assertStringContainsString('fraction="0"', $xml);
    }

    public function test_xml_import_then_save(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $xml = '<question type="ddto_chill">
    <name><text>Imported</text></name>
    <questiontext format="html"><text>The [[1]] sat on the [[2]]</text></questiontext>
    <generalfeedback format="html"><text></text></generalfeedback>
    <defaultgrade>2</defaultgrade>
    <penalty>0</penalty>
    <hidden>0</hidden>
    <negativemarking>-0.3333333</negativemarking>
    <allornothing>1</allornothing>
    <shuffleanswers>0</shuffleanswers>
    <answer fraction="100" format="plain_text"><text>cat</text></answer>
    <answer fraction="100" format="plain_text"><text>mat</text></answer>
    <answer fraction="0" format="html"><text><![CDATA[<i>dog</i>]]></text></answer>
  </question>';
        $importer = new \qformat_xml();
        $fromform = $importer->try_importing_using_qtypes($this->parse_xml($xml)['question'], null, null, 'ddto_chill');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $fromform->category = "{$cat->id},{$cat->contextid}";
        $fromform->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;

        $question = new \stdClass();
        $question->qtype = 'ddto_chill';
        $question->createdby = 0;
        $question->idnumber = null;
        $question->status = $fromform->status;
        $saved = $this->qtype->save_question($question, $fromform);

        $loaded = question_bank::load_question($saved->id);
        $this->assertEqualsWithDelta(-0.3333333, $loaded->negativemarking, 0.0000001);
        $this->assertEquals(1, $loaded->allornothing);
        $this->assertEquals(0, $loaded->shuffleanswers);
        $this->assertEquals(['cat', 'mat', 'dog'], array_column($loaded->choices, 'text'));
        $this->assertEquals([1.0, 1.0, 0.0], array_column($loaded->choices, 'fraction'));
    }

    public function test_form_validation(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $questiondata = test_question_maker::get_question_data('ddto_chill', 'catmat');
        $form = $this->get_editing_form($cat, $questiondata);

        $data = (array) test_question_maker::get_question_form_data('ddto_chill', 'catmat');
        $data['category'] = "{$cat->id},{$cat->contextid}";
        $data['idnumber'] = '';

        $this->assertEmpty($form->validation($data, []));

        $invalid = $data;
        $invalid['sourcetext'] = '';
        $errors = $form->validation($invalid, []);
        $this->assertArrayHasKey('sourcetext', $errors);

        $invalid = $data;
        $invalid['gapselection'] = '';
        $invalid['isgap'] = [];
        $errors = $form->validation($invalid, []);
        $this->assertArrayHasKey('gapwordsintro', $errors);
    }

    public function test_negative_marking_key(): void {
        $this->assertSame('0.0', qtype_ddto_chill_edit_form::negative_marking_key(0.0));
        $this->assertSame('-0.5', qtype_ddto_chill_edit_form::negative_marking_key(-0.5));
        $this->assertSame('-0.15', qtype_ddto_chill_edit_form::negative_marking_key(-0.15));
    }

    /**
     * Build the editing form of a question.
     *
     * @param \stdClass $cat the question category.
     * @param \stdClass $questiondata the question data.
     * @return qtype_ddto_chill_edit_form
     */
    protected function get_editing_form(\stdClass $cat, \stdClass $questiondata): qtype_ddto_chill_edit_form {
        global $PAGE;
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $questiondata->formoptions = (object) [
            'canmove' => true, 'cansaveasnew' => true, 'canedit' => true, 'repeatelements' => true,
        ];
        $questiondata->beingcopied = false;
        $contexts = new \core_question\local\bank\question_edit_contexts(\context::instance_by_id($cat->contextid));
        return $this->qtype->create_editing_form('question.php', $questiondata, $cat, $contexts, true);
    }

    /**
     * Parse a Moodle XML fragment into the array structure used by the XML question format.
     *
     * @param string $xml the XML.
     * @return array the parsed structure.
     */
    protected function parse_xml(string $xml): array {
        global $CFG;
        if (class_exists(\core\xml_parser::class)) {
            return (new \core\xml_parser())->parse($xml);
        }
        require_once($CFG->libdir . '/xmlize.php');
        return xmlize($xml);
    }
}
