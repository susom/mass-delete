<?php
namespace Stanford\MassDelete;

use RCView;

class MassDelete extends \ExternalModules\AbstractExternalModule
{

	public $records;

	public $errors = array();
	public $notes = array();

	public $my_rights = array();			// Array of user's userrights
	public $group_id = null;				// The current user's group ID

	public $arm = null;						// Active arm number
	public $arm_id = null;					// Arm ID

    public function __construct()
	{
		parent::__construct();
	}

	public function init_page() {
		$this->checkForRedirect();
		$this->insertCSS();
		$this->validateUserRights('record_delete');
		$this->setGroupId();
		$this->insertJS();
		$this->render_page();
	}

	public function insertCSS() {
		?>
		<link rel="stylesheet" href="<?= $this->getUrl("style.css") ?>">
		<?php
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

	public function setGroupId() {
		if ( !empty( \REDCap::getGroupNames() ) && !empty($this->my_rights['group_id']) ) {
			$this->group_id = $this->my_rights['group_id'];
			$this->notes['dag_filter'] = "Filtering records for your group: " . \REDCap::getGroupNames(FALSE, $this->group_id);
		}
	}

	public function render_page() {
		$this->renderSectionHeader();
		$this->handleDelete();
		$this->renderErrorsAndNotes();
		if( !isset($_POST['result']) ) {
			$this->renderPageTabs();
		}
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
			print $this->renderAlerts($this->notes, "success");
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

	public function fetchRecords($arm_id, $dag = null){
		global $Proj;

		// Get all records
		$records = \Records::getRecordList($Proj->project_id, $dag, false, false, $arm_id );
		$records = array_values($records);
		$records = array_chunk($records, 1000);

		echo json_encode(array("records" => $records) ) ;
		
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
			self::renderErrorPage();

		}

		# Make sure the user's rights have not expired for the project
		if ($my_rights['expiration'] != "" && $my_rights['expiration'] < TODAY) {
			$this->errors[] = 'Your user account has expired for this project.  Please contact the project admin.';
		}
		$this->my_rights = $my_rights;
	}

	public function handleDelete() {
		if (isset($_POST['delete']) && $_POST['delete'] == 'true') {

			global $Proj;

			if ($Proj->longitudinal && $Proj->multiple_arms) {

				$arm = $_POST['arm'];
				$this->arm = $arm;
				$this->arm_id = $Proj->getArmIdFromArmNum($arm);
				$this->records = \Records::getRecordList($Proj->project_id, $this->group_id, false, false, $arm);
			}
			else {
				$this->records = \Records::getRecordList($Proj->project_id, $this->group_id);
			}

			// DELETE THE RECORD
			$post_records = $_POST['records'];
			$valid_records = array_intersect($post_records,$this->records);

			if (count($valid_records) != count($post_records)) {
				$this->errors[] = "Invalid records were requested for deletion.  Please try again.";
			} else {

				foreach($valid_records as $record) {
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
			}

		}
		
	}

	public function renderErrorPage($msg = '') {

		print $this->renderAlerts($this->errors);
		exit();
	}

}

