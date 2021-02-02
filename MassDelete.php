<?php
namespace Stanford\MassDelete;

use RCView;

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

	public function init_page() {
		$this->checkForRedirect();
		$this->validateUserRights('record_delete');
		$this->insertJS();
		$this->render_page();
	}

	public function insertJS() { 
		?>
			<script src="<?= $this->getUrl("js/mass_delete.js") ?>"></script>
			<script>
				Stanford_MassDelete.requestHandlerUrl = "<?= $this->getUrl("requestHandler.php") ?>";
			</script>
		<?php
	}

	public function checkForRedirect() {
		if(isset($_GET['prefix'])) {
		  if(strpos($_SERVER['REQUEST_URI'], 'index.php') === false) {

			$view = 'custom-list';
			$page = 'page_mass_delete';

			\HttpClient::redirect('index.php?prefix='. $_GET['prefix'] .'&type=module&page='.$page.'&view='.$view.'&pid='.$_GET['pid'].'');
			
		  }
		}
	}

	public function render_page() {
		$this->renderSectionHeader();
		$this->renderErrorsAndNotes();
		$this->renderPageTabs();
	}

	public function renderSectionHeader(){
      
		print	RCView::div(array('style'=>'max-width:750px;margin-bottom:10px;'),
					RCView::div(array('style'=>'color: #800000;font-size: 16px;font-weight: bold;float:left;'),
				  		RCView::fa('fas fa-times-circle fs15 mr-1') . $this->getModuleName()) .
					RCView::div(array('class'=>'clear'), '')
			  	);

		print   RCView::p('', 'This module is used to delete a large number of records. You can either add a custom list of records or select from your record list for deletion.');
	  }

	public function renderErrorsAndNotes(){

		if (!empty($this->errors)) {
			print $this->renderAlerts($this->errors);
        }

        if (!empty($this->notes)) {
			print $this->renderAlerts($this->errors, "success");
		}

	}

	public function renderAlerts($contents, $type = 'danger') {
		$alerts = "";
		foreach($contents as $content) {
			$alerts .= "<div class='alert alert-$type'>$content</div>";
		}
		return $alerts;
	}

    public function renderPageTabs() {

		// Get URL parameters to ensure dynamic redirection
		$prefix = $_GET['prefix'];
		$pid = $_GET['pid'];
		$em_url = 'ExternalModules/index.php?type=module';

		$page = 'page_mass_delete';
  
		// Determine tabs to display
		$tabs = array();
  
		// Tab to view list of existing webhooks
		$tabs[ $em_url . '&prefix=' .$prefix. '&page='.$page.'&view=custom-list'] = '<i class="fas stream"></i> ' .
		  RCView::span(array('style'=>'vertical-align:middle;'), 'Custom List');
  
		// Tab to view log
		$tabs[ $em_url . '&prefix=' .$prefix. '&page='.$page.'&view=record-list'] = '<i class="fas check-square"></i> ' .
		  RCView::span(array('style'=>'vertical-align:middle;'), 'Select Records');		
  
		RCView::renderTabs($tabs);
  
	}	

	public function validateUserRights($right = 'design') {

		$current_user = USERID;
		# Check if Impersonification is active
		if(\UserRights::isImpersonatingUser()){
			$current_user = $_SESSION['impersonate_user'][PROJECT_ID]['impersonating'];
		}
		$my_rights = \REDCap::getUserRights($current_user)[$current_user];

		# Make sure user has permissions for project
		if (!$my_rights[$right]) {
			$this->errors[] = "You must have 'Delete Records' privilege in user-rights to use this feature.";
		}

		# Make sure the user's rights have not expired for the project
		if ($my_rights['expiration'] != "" && $my_rights['expiration'] < TODAY) {
			$this->errors[] = 'Your user account has expired for this project.  Please contact the project admin.';
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

