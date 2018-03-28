<?php
namespace Stanford\MassDelete;

class MassDelete extends \ExternalModules\AbstractExternalModule
{

	public $project_id;

	public $records;
	public $record_checkboxes = array();	// Array to hold checkboxes for display

	public $errors = array();
	public $notes = array();
	public $my_rights = array();			// Array of user's userrights
	public $group_id = null;				// The current user's group ID

	public $arm_options = array();			// Array for select of arms/records
	public $arm = null;						// Active arm number
	public $arm_id = null;					// Arm ID

    public function __construct()
	{
		parent::__construct();
	}

	// TODO: What is the bast way to render a project-level header but be able to inject js and CSS...

	// public function renderHeader() {
	// 	// Render the viewer
	// 	$html = new \HtmlPage();
	//
	// 	// Include cs file with file-modification-based timestamp (to aid with caching issues during development)
	// 	$cs_file = "css/log_view.css";
	// 	$cs_file_full = $cs_file . "?random=" . filemtime( __DIR__ . DS . $cs_file);
	//
	// 	$html->addStylesheet2( $this->getUrl($cs_file_full), "" );
	// 	$html->PrintHeaderExt();
	//
	// 	// Include js file with file-modification-based timestamp (to aid with caching issues during development)
	// 	$js_file = "js/log_view.js";
	// 	$js_file_full = $js_file . "?random=" . filemtime( __DIR__ . DS . $js_file );
	// 	$js_url = $this->getUrl($js_file_full);
	// 	print "<script type='text/javascript' src='$js_url'></script>";
	//
	// 	}

	public function validateUserRights($right = 'design') {
		# Make sure user has permissions for project or is a super user
		$these_rights = \REDCap::getUserRights(USERID);
		$my_rights = $these_rights[USERID];
		if (!$my_rights[$right] && !SUPER_USER) {
			$this->errors[] = "Project Setup rights are required to access MASS DET plugin.";
			$this->renderErrorPage();
		}

		# Make sure the user's rights have not expired for the project
		if ($my_rights['expiration'] != "" && $my_rights['expiration'] < TODAY) {
			$this->errors[] = 'Your user account has expired for this project.  Please contact the project admin.';
			$this->renderErrorPage();
		}
		$this->my_rights = $my_rights;
	}


	public function determineRecords() {
		// Check DAG
		if ( !empty( \REDCap::getGroupNames() ) && !empty($this->my_rights['group_id']) ) {
			$this->group_id = $this->my_rights['group_id'];
			$this->notes['dag_filter'] = "Filtering records for your group: " . \REDCap::getGroupNames(FALSE, $this->group_id);
		}

		// Check ARM
		global $Proj;
		if ($Proj->longitudinal && $Proj->multiple_arms) {
			// Get the current arm or default to 1
			$arm = isset($_POST['arm']) ? $_POST['arm'] : 1;

			// Verify the current arm is valid
			if (!isset($Proj->events[$arm])) {
				$notes[] = "Unable to find arm $arm in this project - setting the first arm as active";
				unset($_POST['delete']);           // In case this is a delete post, prevent the delete from happening
				$events = $Proj->events;
				$arm = key($events);            // Take the frist arm number as the current
			}
			$this->arm = $arm;
			$this->arm_id = $Proj->getArmIdFromArmNum($arm);

			// Loop through all arms to build select box
			$this->arm_options = array();
			foreach ($Proj->events as $arm_num => $arm_detail) {
				$arm_records = \Records::getRecordList($Proj->project_id, $this->group_id, false, false, $arm_num);
				if ($arm == $arm_num) {
					// Pull the current records
					$this->records = $arm_records;
					$selected = "selected='selected'";
					$notes[] = "Showing records from arm $arm (" . $arm_detail['name'] . ")";
				} else {
					$selected = "";
				}
				$this->arm_options[] = "<option value='$arm_num' $selected> {$arm_detail['name']} - " .
					count($arm_records) . " records</option>";
			}
		} else {
			// Get all records
			$this->records = \Records::getRecordList($Proj->project_id, $this->group_id);
		}

		// Obtain custom record label & secondary unique field labels for ALL records.
		$extra_record_labels = \Records::getCustomRecordLabelsSecondaryFieldAllRecords($this->records, true, $this->arm_id);

		// BUILD CHECKBOXES
		$cbx_array = array();
		$max_length = 0;
		foreach ($this->records as $record) {
			$label = $record .  ( empty($extra_record_labels[$record]) ? "" : " " . $extra_record_labels[$record]);
			$max_length = max($max_length,strlen($label));

			$cbx_array[] = "<input type='checkbox' name='records[]' value='$record'/> $label";
		}

		unset($extra_record_labels);

		$max_length = $max_length + 3; // add space for checkbox

		$this->max_length = $max_length;
		$this->record_checkboxes = $cbx_array;

	}

	public function handlePost() {
		if (isset($_POST['delete']) && $_POST['delete'] == 'true') {


			// DELETE THE RECORD
			$post_records = $_POST['records'];
			$valid_records = array_intersect($post_records,$this->records);

			if (count($valid_records) != count($post_records)) {
				$this->errors[] = "Invalid records were requested for deletion.  Please try again.";
				self::renderErrorPage();
			} else {
				// Continue to process
				global $Proj;
				foreach ($valid_records as $record) {
					// print "<br>Deleting $record";
					\Records::deleteRecord(
						$record,
						$Proj->table_pk,
						$Proj->multiple_arms,
						$Proj->project_id['randomization'],
						$Proj->project['status'],
						$Proj->project['require_change_reason'],
						$this->arm_id,
						" (" . $this->getModuleName() . ")"
					);
				}
				$this->notes[] = "<b>Deleted " . count($valid_records) . " record" .
					(count($valid_records > 1) ? "s" : "") .
					"</b>";

				// Refresh the record lists as they may have changed since the deletion
				$this->determineRecords();
			}
		}
	}

	public function renderErrorPage($msg = '') {
		if (!empty($msg)) print "<h3>ERROR:</h3>$msg";

    	if (!empty($this->errors)) {
			print "<div class='alert alert-danger'><ul><li>" . implode("</li><li>", $this->errors) . "</li></ul></div>";
		}
		exit();
	}

}

