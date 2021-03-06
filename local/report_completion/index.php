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

require_once('../../config.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->libdir.'/excellib.class.php');
require_once('select_form.php');
require_once($CFG->dirroot.'/blocks/iomad_company_admin/lib.php');
require_once($CFG->dirroot.'/local/iomad/pchart/pChart/pData.class');
require_once($CFG->dirroot.'/local/iomad/pchart/pChart/pChart.class');
require_once('lib.php');

// Params.
$courseid = optional_param('courseid', 0, PARAM_INT);
$participant = optional_param('participant', 0, PARAM_INT);
$dodownload = optional_param('dodownload', 0, PARAM_INT);
$firstname       = optional_param('firstname', 0, PARAM_CLEAN);
$lastname      = optional_param('lastname', '', PARAM_CLEAN);
$showsuspended = optional_param('showsuspended', 0, PARAM_INT);
$email  = optional_param('email', 0, PARAM_CLEAN);
$sort         = optional_param('sort', 'name', PARAM_ALPHA);
$dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
$page         = optional_param('page', 0, PARAM_INT);
$perpage      = optional_param('perpage', 30, PARAM_INT);        // How many per page.
$acl          = optional_param('acl', '0', PARAM_INT);           // Id of user to tweak mnet ACL (requires $access).
$search      = optional_param('search', '', PARAM_CLEAN);// Search string.
$departmentid = optional_param('departmentid', 0, PARAM_INTEGER);
$vantage      = optional_param('profile_field_VANTAGE', '', PARAM_CLEAN);
$compfromraw = optional_param_array('compfrom', null, PARAM_INT);
$comptoraw = optional_param_array('compto', null, PARAM_INT);
$completiontype = optional_param('completiontype', 0, PARAM_INT);
$charttype = optional_param('charttype', '', PARAM_CLEAN);
$showchart = optional_param('showchart', false, PARAM_BOOL);

require_login($SITE);
$context = context_system::instance();
iomad::require_capability('local/report_completion:view', $context);

if ($firstname) {
    $params['firstname'] = $firstname;
}
if ($lastname) {
    $params['lastname'] = $lastname;
}
if ($email) {
    $params['email'] = $email;
}
if ($sort) {
    $params['sort'] = $sort;
}
if ($dir) {
    $params['dir'] = $dir;
}
if ($page) {
    $params['page'] = $page;
}
if ($perpage) {
    $params['perpage'] = $perpage;
}
if ($search) {
    $params['search'] = $search;
}
if ($courseid) {
    $params['courseid'] = $courseid;
}
if ($departmentid) {
    $params['departmentid'] = $departmentid;
}
if ($departmentid) {
    $params['departmentid'] = $departmentid;
}
if ($vantage) {
    $params['vantage'] = $vantage;
}
if ($showsuspended) {
    $params['showsuspended'] = $showsuspended;
}
if ($charttype) {
    $params['charttype'] = $charttype;
}

if ($compfromraw) {
    if (is_array($compfromraw)) {
        $compfrom = mktime(0, 0, 0, $compfromraw['month'], $compfromraw['day'], $compfromraw['year']);
    } else {
        $compfrom = $compfromraw;
    }
    $params['compfrom'] = $compfrom;
} else {
    $compfrom = 0;
}

if ($comptoraw) {
    if (is_array($comptoraw)) {
        $compto = mktime(0, 0, 0, $comptoraw['month'], $comptoraw['day'], $comptoraw['year']);
    } else {
        $compto = $comptoraw;
    }
    $params['compto'] = $compto;
} else {
    $compto = 0;
}

// Url stuff.
$url = new moodle_url('/local/report_completion/index.php', $params);
$dashboardurl = new moodle_url('/local/iomad_dashboard/index.php');

// Page stuff:.
$strcompletion = get_string('pluginname', 'local_report_completion');
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title($strcompletion);
$PAGE->set_heading($SITE->fullname);
$PAGE->requires->css("/local/report_completion/styles.css");


// Set the companyid
$companyid = iomad::get_my_companyid($context);

// Work out department level.
$company = new company($companyid);
$parentlevel = company::get_company_parentnode($company->id);
$companydepartment = $parentlevel->id;

if (iomad::has_capability('block/iomad_company_admin:edit_all_departments', context_system::instance()) ||
    !empty($SESSION->currenteditingcompany)) {
    $userhierarchylevel = $parentlevel->id;
} else {
    $userlevel = company::get_userlevel($USER);
    $userhierarchylevel = $userlevel->id;
}
if ($departmentid == 0 ) {
    $departmentid = $userhierarchylevel;
}

// Get the company additional optional user parameter names.
$foundobj = iomad::add_user_filter_params($params, $companyid);
$idlist = $foundobj->idlist;
$foundfields = $foundobj->foundfields;

// Set the url.
company_admin_fix_breadcrumb($PAGE, $strcompletion, $url);

// Get the appropriate list of departments.
$selectparams = $params;
$selectparams['courseid'] = 0;
$selecturl = new moodle_url('/local/report_completion/index.php', $selectparams);
$subhierarchieslist = company::get_all_subdepartments($userhierarchylevel);
$select = new single_select($selecturl, 'departmentid', $subhierarchieslist, $departmentid);
$select->label = get_string('department', 'block_iomad_company_admin');
$select->formid = 'choosedepartment';
$fwselectoutput = html_writer::tag('div', $OUTPUT->render($select), array('id' => 'iomad_company_selector'));

// Get the appropriate list of departments.
$selectparams = $params;
$selecturl = new moodle_url('/local/report_completion/index.php', $selectparams);
$completiontypelist = array('0' => get_string('all'),
                            '1' => get_string('notstartedusers', 'local_report_completion'),
                            '2' => get_string('inprogressusers', 'local_report_completion'),
                            '3' => get_string('completedusers', 'local_report_completion'));
$select = new single_select($selecturl, 'completiontype', $completiontypelist, $completiontype);
$select->label = get_string('choosecompletiontype', 'block_iomad_company_admin');
$select->formid = 'choosecompletiontype';
$completiontypeselectoutput = html_writer::tag('div', $OUTPUT->render($select), array('id' => 'iomad_completiontype_selector'));

if (!(iomad::has_capability('block/iomad_company_admin:editusers', $context) or
      iomad::has_capability('block/iomad_company_admin:editallusers', $context))) {
    print_error('nopermissions', 'error', '', 'report on users');
}

$searchinfo = iomad::get_user_sqlsearch($params, $idlist, $sort, $dir, $departmentid);

// Create data for form.
$customdata = null;
$options = $params;
$options['dodownload'] = 1;

// Only print the header if we are not downloading.
if (empty($dodownload) && empty($showchart)) {
    echo $OUTPUT->header();
}

// Get the data.
if (!empty($companyid)) {
    if (empty($dodownload) && empty($showchart)) {
        echo $fwselectoutput;
    }
}

if (empty($dodownload) && empty($showchart)) {
    echo "<h3>".get_string('coursesummary', 'local_report_completion')."</h3>";
    if (!empty($courseid)) {
        // Navigation and header.
        echo $OUTPUT->single_button(new moodle_url('index.php', $options), get_string("downloadcsv", 'local_report_completion'));
        $options['charttype'] = 'summary';
        $options['dodownload'] = false;
        echo $OUTPUT->single_button(new moodle_url('index.php', $options), get_string("summarychart", 'local_report_completion'));
        $options['charttype'] = 'course';
        echo $OUTPUT->single_button(new moodle_url('index.php', $options), get_string("coursechart", 'local_report_completion'));
    } else {
        $options['charttype'] = 'summary';
        $options['dodownload'] = false;
        echo $OUTPUT->single_button(new moodle_url('index.php', $options), get_string("summarychart", 'local_report_completion'));
    }

}

// Set up the course overview table.
$coursecomptable = new html_table();
$coursecomptable->head = array(get_string('coursename', 'local_report_completion'),
                               get_string('numusers', 'local_report_completion'),
                               get_string('notstartedusers', 'local_report_completion'),
                               get_string('inprogressusers', 'local_report_completion'),
                               get_string('completedusers', 'local_report_completion'));
$coursecomptable->align = array('left', 'center', 'center', 'center', 'center', 'center');
$coursecomptable->width = '95%';
$chartdata = array();

if (!empty($dodownload)) {
    // Set up the Excel workbook.

    header("Content-Type: application/download\n");
    header("Content-Disposition: attachment; filename=\"coursereport.csv\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
    header("Pragma: public");

}
$courseinfo = iomad::get_course_summary_info ($departmentid, 0, $showsuspended);
$chartnumusers = array();
$chartnotstarted = array();
$chartinprogress = array();
$chartcompleted = array();
$chartname = array();

// Iterate over courses.
foreach ($courseinfo as $id => $coursedata) {
    $coursecomptable->data[] = array("<a href='".new moodle_url($url, array('courseid' => $coursedata->id,
                                                                            'departmentid' => $departmentid,
                                                                            'showchart' => 0,
                                                                            'charttype' => '')).
                                     "'>{$coursedata->coursename}</a>",
                                     $coursedata->numenrolled,
                                     $coursedata->numnotstarted,
                                     $coursedata->numstarted - $coursedata->numcompleted,
                                     $coursedata->numcompleted);
    if ($charttype == 'summary') {
        $chartname[] = $coursedata->coursename;
        $chartnumusers[] = $coursedata->numenrolled;
        $chartnotstarted[] = $coursedata->numnotstarted;
        $chartinprogress[] = $coursedata->numstarted - $coursedata->numcompleted;
        $chartcompleted[] = $coursedata->numcompleted;
    } else if ($charttype == 'course' && $courseid == $coursedata->id ) {
        $seriesdata = array($coursedata->numnotstarted,
                            $coursedata->numstarted - $coursedata->numcompleted,
                            $coursedata->numcompleted);
    }
}

if (!empty($charttype)) {
    if ($charttype == 'summary') {
        $chartdata = new pData;
        $chartdata->AddPoint($chartnotstarted,"Serie1");
        $chartdata->AddPoint($chartinprogress,"Serie2");
        $chartdata->AddPoint($chartcompleted,"Serie3");
        $chartdata->AddPoint($chartname,"Serie4");
        $chartdata->AddAllSeries();
        $chartdata->SetAbsciseLabelSerie("Serie4");
        $chartdata->SetSerieName(get_string('notstartedusers', 'local_report_completion'),"Serie1");
        $chartdata->SetSerieName(get_string('inprogressusers', 'local_report_completion'),"Serie2");
        $chartdata->SetSerieName(get_string('completedusers', 'local_report_completion'),"Serie3");
        $chartdata->SetYAxisName(get_string('numusers', 'local_report_completion'));
        $chartdata->SetXAxisName(get_string('course'));
    } else if ($charttype == 'course') {
        $chartdata = new pData;
        $chartdata->AddPoint($seriesdata, "Serie1");
        $chartdata->AddPoint(array(get_string('notstartedusers', 'local_report_completion'),
                                 get_string('inprogressusers', 'local_report_completion'),
                                 get_string('completedusers', 'local_report_completion')),"Serie2");
        $chartdata->AddAllSeries();
        $chartdata->SetAbsciseLabelSerie("Serie2");
    }

}

if (empty($dodownload) && empty($showchart)) {
    echo html_writer::table($coursecomptable);
    if (!empty($courseid)  && empty($charttype)) {
        echo $completiontypeselectoutput;
    }
}


if (empty($charttype)) {
    if (!empty($courseid)) {
        // Get the course completion information.
        if (empty($dodownload)) {
            if (empty($idlist['0'])) {
                // Only want the data for the page we are on.
                $coursedataobj = iomad::get_user_course_completion_data($searchinfo, $courseid, $page, $perpage, $completiontype);
                $coursedata = $coursedataobj->users;
                $totalcount = $coursedataobj->totalcount;
            }
        } else {
            if (empty($idlist['0'])) {
                $coursedataobj = iomad::get_user_course_completion_data($searchinfo, $courseid);
                $coursedata = $coursedataobj->users;
                $totalcount = $coursedataobj->totalcount;
            }
        }
    
        // Is there a global vantage number.
        if ($DB->get_record('user_info_field', array('shortname' => 'VANTAGE'))) {
            $vantage = true;
        } else {
            $vantage = false;
        }
    
        // Check if there is a certificate module.
        $hascertificate = false;
        if (empty($dodownload) && $certmodule = $DB->get_record('modules', array('name' => 'iomadcertificate'))) {
            require_once($CFG->dirroot.'/mod/iomadcertificate/lib.php');
            if ($certificateinfo = $DB->get_record('iomadcertificate', array('course' => $courseid))) {
                if ($certificatemodinstance = $DB->get_record('course_modules', array('course' => $courseid,
                                                                                      'module' => $certmodule->id,
                                                                                      'instance' => $certificateinfo->id))) {
                    $certificatecontext = context_module::instance($certificatemodinstance->id);
                    $hascertificate = true;
                }
            }
        }
        if (empty($dodownload)) {
            echo "<h3>".get_string('courseusers', 'local_report_completion').$courseinfo[$courseid]->coursename."</h3>";
        }
        $compusertable = new html_table();
    
        // Deal with table columns.
        if (!$vantage) {
            $columns = array('firstname',
                             'lastname',
                             'department',
                             'email',
                             'status',
                             'timeenrolled',
                             'timestarted',
                             'timecompleted',
                             'finalscore');
        } else {
            $columns = array('firstname',
                             'lastname',
                             'vantage',
                             'department',
                             'email',
                             'status',
                             'timeenrolled',
                             'timestarted',
                             'timecompleted',
                             'finalscore');
        }
    
        foreach ($columns as $column) {
            $string[$column] = get_string($column, 'local_report_completion');
            if ($sort != $column) {
                $columnicon = "";
                $columndir = "ASC";
            } else {
                $columndir = $dir == "ASC" ? "DESC":"ASC";
                $columnicon = $dir == "ASC" ? "down":"up";
                $columnicon = " <img src=\"" . $OUTPUT->pix_url('t/' . $columnicon) . "\" alt=\"\" />";
    
            }
            $$column = $string[$column].$columnicon;
        }
    
        // Set up the course worksheet.
        if (!empty($dodownload)) {
    
            echo $courseinfo[$courseid]->coursename."\n";
            if (!$vantage) {
                echo '"'.get_string('name', 'local_report_completion').'","'
                     .get_string('email', 'local_report_completion').'","'
                     .get_string('department', 'block_iomad_company_admin').'","'
                     .get_string('status', 'local_report_completion').'","'
                     .get_string('timeenrolled', 'local_report_completion').'","'
                     .get_string('timestarted', 'local_report_completion').'","'
                     .get_string('timecompleted', 'local_report_completion').'","'
                     .get_string('finalscore', 'local_report_completion')."\"\n";
            } else {
                echo '"'.get_string('name', 'local_report_completion').'","'
                     .get_string('vantage', 'local_report_completion').'","'
                     .get_string('email', 'local_report_completion').'","'
                     .get_string('department', 'block_iomad_company_admin').'","'
                     .get_string('status', 'local_report_completion').'","'
                     .get_string('timeenrolled', 'local_report_completion').'","'
                     .get_string('timestarted', 'local_report_completion').'","'
                     .get_string('timecompleted', 'local_report_completion').'","'
                     .get_string('finalscore', 'local_report_completion')."\"\n";
            }
            $xlsrow = 1;
        }
        // Set the initial parameters for the table header links.
        $linkparams = $params;
    
        $override = new object();
        $override->firstname = 'firstname';
        $override->lastname = 'lastname';
        $fullnamelanguage = get_string('fullnamedisplay', '', $override);
        if (($CFG->fullnamedisplay == 'firstname lastname') or
            ($CFG->fullnamedisplay == 'firstname') or
            ($CFG->fullnamedisplay == 'language' and $fullnamelanguage == 'firstname lastname' )) {
            // Work out for name sorting/direction and links.
            // Set the defaults.
               $linkparams['dir'] = 'ASC';
            $linkparams['sort'] = 'firstname';
            $firstnameurl = new moodle_url('index.php', $linkparams);
            $linkparams['sort'] = 'lastname';
            $lastnameurl = new moodle_url('index.php', $linkparams);
            $linkparams['sort'] = 'vantage';
            $vantageurl = new moodle_url('index.php', $linkparams);
            $linkparams['sort'] = 'department';
            $departmenturl = new moodle_url('index.php', $linkparams);
            $linkparams['sort'] = 'email';
            $emailurl = new moodle_url('index.php', $linkparams);
            $linkparams['sort'] = 'status';
            $statusurl = new moodle_url('index.php', $linkparams);
            $linkparams['sort'] = 'timestarted';
            $timestartedurl = new moodle_url('index.php', $linkparams);
            $linkparams['sort'] = 'timecompleted';
            $timecompletedurl = new moodle_url('index.php', $linkparams);
            $linkparams['sort'] = 'finalscore';
            $finalscoreurl = new moodle_url('index.php', $linkparams);
            $linkparams['sort'] = 'timeenrolled';
            $timeenrolledurl = new moodle_url('index.php', $linkparams);
    
            // Set the options if there is already a sort defined.
            if (!empty($params['sort'])) {
                if ($params['sort'] == 'firstname') {
                    $linkparams['sort'] = 'firstname';
                    if ($params['dir'] == 'ASC') {
                        $linkparams['dir'] = 'DESC';
                        $firstnameurl = new moodle_url('index.php', $linkparams);
                    } else {
                        $linkparams['dir'] = 'ASC';
                        $firstnameurl = new moodle_url('index.php', $linkparams);
                    }
                } else if ($params['sort'] == 'lastname') {
                    $linkparams['sort'] = 'lastname';
                    if ($params['dir'] == 'ASC') {
                        $linkparams['dir'] = 'DESC';
                        $lastnameurl = new moodle_url('index.php', $linkparams);
                    } else {
                        $linkparams['dir'] = 'ASC';
                        $lastnameurl = new moodle_url('index.php', $linkparams);
                    }
                } else if ($params['sort'] == 'vantage') {
                    $linkparams['sort'] = 'vantage';
                    if ($params['dir'] == 'ASC') {
                        $linkparams['dir'] = 'DESC';
                        $vantageurl = new moodle_url('index.php', $linkparams);
                    } else {
                        $linkparams['dir'] = 'ASC';
                        $vantageurl = new moodle_url('index.php', $linkparams);
                    }
                } else if ($params['sort'] == 'department') {
                    $linkparams['sort'] = 'department';
                    if ($params['dir'] == 'ASC') {
                        $linkparams['dir'] = 'DESC';
                        $departmenturl = new moodle_url('index.php', $linkparams);
                    } else {
                        $linkparams['dir'] = 'ASC';
                        $departmenturl = new moodle_url('index.php', $linkparams);
                    }
                } else if ($params['sort'] == 'email') {
                    $linkparams['sort'] = 'email';
                    if ($params['dir'] == 'ASC') {
                        $linkparams['dir'] = 'DESC';
                        $emailurl = new moodle_url('index.php', $linkparams);
                    } else {
                        $linkparams['dir'] = 'ASC';
                        $emailurl = new moodle_url('index.php', $linkparams);
                    }
                } else if ($params['sort'] == 'status') {
                    $linkparams['sort'] = 'status';
                    if ($params['dir'] == 'ASC') {
                        $linkparams['dir'] = 'DESC';
                        $statusurl = new moodle_url('index.php', $linkparams);
                    } else {
                        $linkparams['dir'] = 'ASC';
                        $statusurl = new moodle_url('index.php', $linkparams);
                    }
                } else if ($params['sort'] == 'timestarted') {
                    $linkparams['sort'] = 'timestarted';
                    if ($params['dir'] == 'ASC') {
                        $linkparams['dir'] = 'DESC';
                        $datestartedurl = new moodle_url('index.php', $linkparams);
                    } else {
                        $linkparams['dir'] = 'ASC';
                        $datestartedurl = new moodle_url('index.php', $linkparams);
                    }
                } else if ($params['sort'] == 'timeenrolled') {
                    $linkparams['sort'] = 'timeenrolled';
                    if ($params['dir'] == 'ASC') {
                        $linkparams['dir'] = 'DESC';
                        $dateenrolledurl = new moodle_url('index.php', $linkparams);
                    } else {
                        $linkparams['dir'] = 'ASC';
                        $dateenrolledurl = new moodle_url('index.php', $linkparams);
                    }
                } else if ($params['sort'] == 'timecompleted') {
                    $linkparams['sort'] = 'timecompleted';
                    if ($params['dir'] == 'ASC') {
                        $linkparams['dir'] = 'DESC';
                        $datecompletedurl = new moodle_url('index.php', $linkparams);
                    } else {
                        $linkparams['dir'] = 'ASC';
                        $datecompletedurl = new moodle_url('index.php', $linkparams);
                    }
                } else if ($params['sort'] == 'finalscore') {
                    $linkparams['sort'] = 'finalscore';
                    if ($params['dir'] == 'ASC') {
                        $linkparams['dir'] = 'DESC';
                        $finalscoreurl = new moodle_url('index.php', $linkparams);
                    } else {
                        $linkparams['dir'] = 'ASC';
                        $finalscoreurl = new moodle_url('index.php', $linkparams);
                    }
                }
            }
        }
        $fullnamedisplay = $OUTPUT->action_link($firstnameurl, $firstname) ." / ". $OUTPUT->action_link($lastnameurl, $lastname);
    
        if (!$vantage) {
            $compusertable->head = array ($fullnamedisplay,
                                          $OUTPUT->action_link($emailurl, $email),
                                          $OUTPUT->action_link($departmenturl, $department),
                                          $OUTPUT->action_link($timeenrolledurl, $timeenrolled),
                                          $OUTPUT->action_link($statusurl, $status),
                                          $OUTPUT->action_link($timestartedurl, $timestarted),
                                          $OUTPUT->action_link($timecompletedurl, $timecompleted),
                                          $OUTPUT->action_link($finalscoreurl, $finalscore));
        } else {
            $compusertable->head = array ($fullnamedisplay,
                                          $OUTPUT->action_link($vantageurl, $vantage),
                                          $OUTPUT->action_link($emailurl, $email),
                                          $OUTPUT->action_link($departmenturl, $department),
                                          $OUTPUT->action_link($timeenrolledurl, $timeenrolled),
                                          $OUTPUT->action_link($statusurl, $status),
                                          $OUTPUT->action_link($timestartedurl, $timestarted),
                                          $OUTPUT->action_link($timecompletedurl, $timecompleted),
                                          $OUTPUT->action_link($finalscoreurl, $finalscore));
        }
        $compusertable->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
        if ($hascertificate) {
            $compusertable->head[] = get_string('certificate', 'local_report_completion');
            $compusertable->align[] = 'center';
        }
        $compusertable->width = '95%';
    
        $userurl = '/local/report_users/userdisplay.php';
    
        // Paginate up the results.
    
        if (empty($idlist['0'])) {
            foreach ($coursedata as $userid => $user) {
                if (empty($user->timestarted)) {
                    $statusstring = get_string('notstarted', 'local_report_completion');
                } else {
                    $statusstring = get_string('started', 'local_report_completion');
                }
                if (!empty($user->timecompleted)) {
                    $statusstring = get_string('completed', 'local_report_completion');
                }
    
                // Get the completion date information.
                if (!empty($user->timestarted)) {
                    $starttime = date('d M Y', $user->timestarted);
                } else {
                    $starttime = "";
                }
                if (!empty($user->timeenrolled)) {
                    $enrolledtime = date('d M Y', $user->timeenrolled);
                } else {
                    $enrolledtime = "";
                }
                if (!empty($user->timecompleted)) {
                    $completetime = date('d M Y', $user->timecompleted);
                } else {
                    $completetime = "";
                }
    
                // Score information.
                if (!empty($user->result)) {
                    $scorestring = round($user->result, 0)."%";
                } else {
                    $scorestring = "0%";
                }
    
                $user->fullname = $user->firstname . ' ' . $user->lastname;
                // Deal with the certificate.
                if ($hascertificate) {
                    // Check if user has completed the course - if so, show the certificate.
                    if (!empty($user->timecompleted) ) {
                        // Get the course module.
                        $certtabledata = "<a href='".new moodle_url('/mod/iomadcertificate/view.php',
                                                                     array('id' => $certificatemodinstance->id,
                                                                           'action' => 'get',
                                                                           'userid' => $user->id,
                                                                           'sesskey' => sesskey()))."'>".
                                          get_string('downloadcert', 'local_report_users')."</a>";
                    } else {
                        $certtabledata = get_string('nocerttodownload', 'local_report_users');
                    }
                    if (!$vantage) {
                        $compusertable->data[] = array("<a href='".new moodle_url($userurl,
                                                                                  array('userid' => $user->id,
                                                                                        'courseid' => $courseid)).
                                                       "'>$user->fullname</a>",
                                                        $user->email,
                                                        $user->department,
                                                        $enrolledtime,
                                                        $statusstring,
                                                        $starttime,
                                                        $completetime,
                                                        $scorestring,
                                                        $certtabledata);
                    } else {
                        $compusertable->data[] = array("<a href='".new moodle_url($userurl,
                                                                                  array('userid' => $user->id,
                                                                                        'courseid' => $courseid)).
                                                       "'>$user->fullname</a>",
                                                        $user->vantage,
                                                        $user->email,
                                                        $user->department,
                                                        $enrolledtime,
                                                        $statusstring,
                                                        $starttime,
                                                        $completetime,
                                                        $scorestring,
                                                        $certtabledata);
                    }
                } else {
                    if (!$vantage) {
                        $compusertable->data[] = array("<a href='".new moodle_url($userurl,
                                                                                  array('userid' => $user->id,
                                                                                        'courseid' => $courseid)).
                                                       "'>$user->fullname</a>",
                                                        $user->email,
                                                        $user->department,
                                                        $enrolledtime,
                                                        $statusstring,
                                                        $starttime,
                                                        $completetime,
                                                        $scorestring);
                    } else {
                        $compusertable->data[] = array("<a href='".new moodle_url($userurl,
                                                                                  array('userid' => $user->id,
                                                                                        'courseid' => $courseid)).
                                                       "'>$user->fullname</a>",
                                                        $user->vantage,
                                                        $user->email,
                                                        $user->department,
                                                        $enrolledtime,
                                                        $statusstring,
                                                        $starttime,
                                                        $completetime,
                                                        $scorestring);
                    }
                }
                if (!empty($dodownload)) {
                    echo '"'.$user->fullname.
                         '","'.$user->email.
                         '","'.$user->department.
                         '","'.$statusstring.
                         '","'.$enrolledtime.
                         '","'.$starttime.
                         '","'.$completetime.
                         '","'.$scorestring.
                         "\"\n";
                }
            }
        }
        if (empty($dodownload)) {
            // Set up the filter form.
            $mform = new iomad_user_filter_form(null, array('companyid' => $companyid));
            $mform->set_data(array('departmentid' => $departmentid));
            $mform->set_data($params);
    
            // Display the user filter form.
            $mform->display();
    
            // Display the paging bar.
            if (empty($idlist['0'])) {
                echo $OUTPUT->paging_bar($totalcount, $page, $perpage, new moodle_url('/local/report_completion/index.php', $params));
            }
    
            // Display the user table.
            echo html_writer::table($compusertable);
            if (!empty($idlist['0'])) {
                echo "<h2>".$idlist['0']."</h2>";
            }
        }
    }
}
if (!empty($showchart)) {
    if ($charttype == "summary") {
        // Initialise the graph
        $Test = new pChart(700,230);
        $Test->setFontProperties($CFG->dirroot."/local/iomad/pchart/Fonts/tahoma.ttf",8);
        $Test->setGraphArea(65,30,125,200);
        $Test->drawFilledRoundedRectangle(7,7,203,223,5,240,240,240);
        $Test->drawRoundedRectangle(5,5,205,225,5,230,230,230);
        $Test->drawGraphArea(255,255,255,TRUE);
        $Test->drawScale($chartdata->GetData(),$chartdata->GetDataDescription(),SCALE_ADDALLSTART0,150,150,150,TRUE,0,2,TRUE);
        $Test->drawGrid(4,TRUE,230,230,230,50);
        $chartdata->RemoveSerie("Serie4");
        
        // Draw the 0 line
        $Test->setFontProperties("Fonts/tahoma.ttf",6);
        $Test->drawTreshold(0,143,55,72,TRUE,TRUE);
        
        // Draw the bar graph
        $Test->drawBarGraph($chartdata->GetData(),$chartdata->GetDataDescription(),80);

    // Finish the graph
    $Test->setFontProperties($CFG->dirroot."/local/iomad/pchart/Fonts/tahoma.ttf",8);
    $Test->drawLegend(135,150,$chartdata->GetDataDescription(),255,255,255);
    $Test->setFontProperties($CFG->dirroot."/local/iomad/pchart/Fonts/tahoma.ttf",10);
    $Test->drawTitle(0,22,"Sample size",50,50,50,210);
    $Test->Stroke("SmallStacked.png");
    exit;
    } else if ($charttype == "course") {
        // Initialise the graph
        $Test = new pChart(420,250);
        $Test->drawFilledRoundedRectangle(7,7,413,243,5,240,240,240);
        $Test->drawRoundedRectangle(5,5,415,245,5,230,230,230);
        $Test->createColorGradientPalette(195,204,56,223,110,41,5);

        // Draw the pie chart
        $Test->setFontProperties($CFG->dirroot."/local/iomad/pchart/Fonts/tahoma.ttf",8);
        $Test->AntialiasQuality = 0;
        $Test->drawPieGraph($chartdata->GetData(),$chartdata->GetDataDescription(),180,130,110,PIE_PERCENTAGE_LABEL,FALSE,50,20,5);
        $Test->drawPieLegend(330,15,$chartdata->GetData(),$chartdata->GetDataDescription(),250,250,250);

    // Finish the graph
    $Test->setFontProperties($CFG->dirroot."/local/iomad/pchart/Fonts/tahoma.ttf",8);
    $Test->drawLegend(135,150,$chartdata->GetDataDescription(),255,255,255);
    $Test->setFontProperties($CFG->dirroot."/local/iomad/pchart/Fonts/tahoma.ttf",10);
    $Test->drawTitle(0,22,"Course Completion",50,50,50,210);
    $Test->Stroke("SmallStacked.png");
    exit;
    }
}

if (empty($dodownload) && !empty($charttype)) {
    $params['showchart'] = true;
    echo "<center><img src='".new moodle_url('/local/report_completion/index.php', $params)."'></center>";
}

if (!empty($dodownload)) {
    exit;
}
echo $OUTPUT->footer();
