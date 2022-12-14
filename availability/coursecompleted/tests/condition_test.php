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
 * Unit tests for the coursecompleted condition.
 *
 * @package   availability_coursecompleted
 * @copyright 2017 iplusacademy (www.iplusacademy.org)
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_coursecompleted;


use \availability_coursecompleted\condition;
use \availability_coursecompleted\frontend;
use \completion_info;
use core_availability\tree;
use core_availability\info_module;
use core_availability\mock_info;
use core_availability\mock_condition;
use core_completion;

/**
 * Unit tests for the coursecompleted condition.
 *
 * @package   availability_coursecompleted
 * @copyright 2017 iplusacademy (www.iplusacademy.org)
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \availability_coursecompleted
 */
class condition_test extends \advanced_testcase {

    /**
     * Tests constructing and using coursecompleted condition as part of tree.
     * @covers \availability_coursecompleted\condition
     */
    public function test_in_tree() {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create course with coursecompleted turned on.
        set_config('enableavailability', true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);
        $userid = $this->getDataGenerator()->create_user()->id;
        $this->getDataGenerator()->enrol_user($userid, $course->id);
        $info = new mock_info($course, $userid);

        $structure1 = (object)['op' => '|', 'show' => true, 'c' => [(object)['type' => 'coursecompleted', 'id' => '1']]];
        $structure2 = (object)['op' => '|', 'show' => true, 'c' => [(object)['type' => 'coursecompleted', 'id' => '0']]];
        $tree1 = new tree($structure1);
        $tree2 = new tree($structure2);

        $this->assertFalse($tree1->check_available(false, $info, true, $USER->id)->is_available());
        $this->assertTrue($tree2->check_available(false, $info, true, $USER->id)->is_available());
        $this->assertFalse($tree1->check_available(false, $info, true, $userid)->is_available());
        $this->assertTrue($tree2->check_available(false, $info, true, $userid)->is_available());

        $this->setuser($userid);
        $this->assertFalse($tree1->check_available(false, $info, true, $USER->id)->is_available());
        $this->assertTrue($tree2->check_available(false, $info, true, $USER->id)->is_available());
        $this->assertFalse($tree1->check_available(false, $info, true, $userid)->is_available());
        $this->assertTrue($tree2->check_available(false, $info, true, $userid)->is_available());

        // Change course completed.
        $this->setAdminUser();
        $ccompletion = new \completion_completion(['course' => $course->id, 'userid' => $userid]);
        $ccompletion->mark_complete();

        $this->assertTrue($tree1->check_available(false, $info, true, $userid)->is_available());
        $this->assertFalse($tree2->check_available(false, $info, true, $userid)->is_available());
        $this->setuser($userid);
        $this->assertTrue($tree1->check_available(false, $info, true, $userid)->is_available());
        $this->assertFalse($tree2->check_available(false, $info, true, $userid)->is_available());
    }

    /**
     * Tests the constructor including error conditions.
     * @covers \availability_coursecompleted\condition
     */
    public function test_constructor() {
        // This works with no parameters.
        $structure = (object)[];
        try {
            $completed = new condition($structure);
            $this->fail();
        } catch (\exception $e) {
            $this->assertEquals('', $e->getMessage());
        }
        $this->assertNotEmpty($completed);

        // This works with '1'.
        $structure->id = '1';
        try {
            $completed = new condition($structure);
            $this->fail();
        } catch (\exception $e) {
            $this->assertEquals('', $e->getMessage());
        }
        $this->assertNotEmpty($completed);

        // This works with '0'.
        $structure->id = '0';
        try {
            $completed = new condition($structure);
            $this->fail();
        } catch (\exception $e) {
            $this->assertEquals('', $e->getMessage());
        }
        $this->assertNotEmpty($completed);

        // This fails with null.
        $structure->id = null;
        try {
            $completed = new condition($structure);
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Invalid value for course completed condition', $e->getMessage());
        }

        // Invalid ->id.
        $structure->id = false;
        try {
            $completed = new condition($structure);
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Invalid value for course completed condition', $e->getMessage());
        }

        // Invalid string. Should be checked 'longer string'.
        $structure->id = 1;
        try {
            $completed = new condition($structure);
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Invalid value for course completed condition', $e->getMessage());
        }
    }

    /**
     * Tests the save() function.
     * @covers \availability_coursecompleted\condition
     */
    public function test_save() {
        $structure = (object)['id' => '1'];
        $cond = new condition($structure);
        $structure->type = 'coursecompleted';
        $this->assertEquals($structure, $cond->save());
    }

    /**
     * Tests the get_description and get_standalone_description functions.
     * @covers \availability_coursecompleted\condition
     * @covers \availability_coursecompleted\frontend
     */
    public function test_get_description() {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableavailability', true);
        $userid = $this->getDataGenerator()->create_user()->id;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();

        $frontend = new frontend();
        $name = 'availability_coursecompleted\frontend';
        $this->assertFalse(\phpunit_util::call_internal_method($frontend, 'allow_add', [$course], $name));

        $data = (object) ['id' => $course->id, 'criteria_activity' => [$assign->cmid => 1]];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($data);
        $this->assertTrue(\phpunit_util::call_internal_method($frontend, 'allow_add', [$course], $name));
        $this->assertTrue(\phpunit_util::call_internal_method($frontend, 'allow_add', [$course, null, $sections[0]], $name));
        $this->assertTrue(\phpunit_util::call_internal_method($frontend, 'allow_add', [$course, null, $sections[1]], $name));

        $info = new mock_info();
        $nau = 'Not available unless: ';
        $completed = new condition((object)['type' => 'coursecompleted', 'id' => '1']);
        $information = $completed->get_description(true, false, $info);
        $this->assertEquals($information, get_string('getdescription', 'availability_coursecompleted'));
        $information = $completed->get_description(true, true, $info);
        $this->assertEquals($information, get_string('getdescriptionnot', 'availability_coursecompleted'));
        $information = $completed->get_standalone_description(true, false, $info);
        $this->assertEquals($information, $nau . get_string('getdescription', 'availability_coursecompleted'));
        $information = $completed->get_standalone_description(true, true, $info);
        $this->assertEquals($information, $nau . get_string('getdescriptionnot', 'availability_coursecompleted'));
        $this->setuser($userid);
        $information = $completed->get_description(true, false, $info);
        $this->assertEquals($information, get_string('getdescription', 'availability_coursecompleted'));
        $information = $completed->get_description(true, true, $info);
        $this->assertEquals($information, get_string('getdescriptionnot', 'availability_coursecompleted'));
        $information = $completed->get_standalone_description(true, false, $info);
        $this->assertEquals($information, $nau . get_string('getdescription', 'availability_coursecompleted'));
        $information = $completed->get_standalone_description(true, true, $info);
        $this->assertEquals($information, $nau . get_string('getdescriptionnot', 'availability_coursecompleted'));
    }

    /**
     * Tests a page before and after completion.
     * @covers \availability_coursecompleted\condition
     * @covers \availability_coursecompleted\frontend
     */
    public function test_page() {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create course with coursecompleted turned on.
        set_config('enableavailability', true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => true]);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id);
        $page = $generator->get_plugin_generator('mod_page')->create_instance(['course' => $course]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($page->cmid);
        $PAGE->set_url('/course/modedit.php', ['update' => $page->cmid]);
        frontend::include_all_javascript($course, $cm);
        $info = new info_module($cm);
        $cond = new condition((object)['type' => 'coursecompleted', 'id' => '1']);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(false, $info, false, $user->id));
        $this->assertTrue($cond->is_available(true, $info, false, $user->id));
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));
        $ccompletion = new \completion_completion(['course' => $course->id, 'userid' => $user->id]);
        $ccompletion->mark_complete();
        rebuild_course_cache($course->id, true);
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, false, $user->id));
        $this->assertTrue($cond->is_available(false, $info, false, $user->id));
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        // No id.
        $cond = new condition((object)['type' => 'coursecompleted']);
        $this->assertFalse($cond->is_available(false, $info, false, $user->id));
        $this->assertFalse($cond->is_available_for_all());
        $this->assertFalse($cond->update_dependency_id(null, 1, 2));
        $this->assertEquals($cond->__toString(), '{coursecompleted:False}');
        $this->assertEquals($cond->get_standalone_description(true, true, $info),
            'Not available unless: You completed this course.');
    }

    /**
     * Tests using course completion condition in front end.
     * @covers \availability_coursecompleted\condition
     */
    public function test_other() {
        $this->assertEqualsCanonicalizing((object)['type' => 'coursecompleted', 'id' => '3'], condition::get_json('3'));
        $this->assertEqualsCanonicalizing((object)['type' => 'coursecompleted', 'id' => '0'], condition::get_json('0'));
    }

    /**
     * Test behat funcs
     * @covers \behat_availability_coursecompleted
     */
    public function test_behat() {
        global $CFG;
        require_once($CFG->dirroot . '/availability/condition/coursecompleted/tests/behat/behat_availability_coursecompleted.php');
        $this->resetAfterTest();
        set_config('enableavailability', true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);
        $class = new \behat_availability_coursecompleted();
        $class->i_mark_course_completed_for_user($course->fullname, $user->username);
        $this->expectExceptionMessage("A user with username 'otheruser' does not exist");
        $class->i_mark_course_completed_for_user($course->fullname, 'otheruser');
    }
}
