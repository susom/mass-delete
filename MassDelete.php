<?php
namespace Stanford\MassDelete;

use RCView;

class MassDelete extends \ExternalModules\AbstractExternalModule
{

	public $records;

	public $canDelete = false;             // Does user have access to module

	public $errors = array();
	public $notes = array();

	public $my_rights = array();			// Array of user's userrights
	public $group_id = null;				// The current user's group ID

	public $arm = null;						// Active arm number
	public $arm_id = null;					// Arm ID

	public function init_page() {
		$this->insertCSS();
		$this->validateUserRights();
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

	public function setGroupId() {
		if ( !empty( \REDCap::getGroupNames() ) && !empty($this->my_rights['group_id']) ) {
			$this->group_id = $this->my_rights['group_id'];
			$this->notes['dag_filter'] = "Filtering records for your group: " . \REDCap::getGroupNames(FALSE, $this->group_id);
		}
	}

	public function render_page() {
		$this->renderSectionHeader();
		if ($this->canDelete) $this->handleDelete();
		$this->renderErrorsAndNotes();
		if( !isset($_POST['result']) && $this->canDelete ) $this->renderPageTabs();
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
		$tabs = array();
	    $em_url = $this->getUrl('page_mass_delete.php');

		// Tab to view list of existing webhooks
		$tabs[ $em_url . '&view=custom-list'] = '<i class="fas stream"></i> ' .
		  RCView::span(array('style'=>'vertical-align:middle;'), 'Custom List');
  
		// Tab to view log
		$tabs[ $em_url . '&view=record-list'] = '<i class="fas check-square"></i> ' .
		  RCView::span(array('style'=>'vertical-align:middle;'), 'Select Records');

		// Default to custom-list
        if (empty($_GET['view'])) $_GET['view'] = 'custom-list';

	    ?>
        <div id="sub-nav" style="margin:5px 0 20px;">
            <ul>
			    <?php
			    foreach ($tabs as $this_url=>$this_label)
			    {
				    // Get view for current page:
				    $qs = parse_url($this_url, PHP_URL_QUERY);
				    parse_str($qs, $these_param_pairs);
				    $this_view = $these_param_pairs['view'];
				    ?>
                    <li <?php if ($this_view == $_GET['view']) echo 'class="active"'?>>
                        <a href="<?php echo $this_url ?>" style="font-size:13px;color:#393733;padding:6px 9px 5px 10px;"><?php echo $this_label ?></a>
                    </li>
				    <?php
			    } ?>
            </ul>
        </div>
        <div class="clear"></div>
        <?php
    }

	public function fetchRecords($arm_id, $dag = null){
		global $Proj;

		// Get all records
		$records = \Records::getRecordList($Proj->project_id, $dag, false, false, $arm_id );
		$records = array_values($records);
		$records = array_chunk($records, 1000);

		echo json_encode(array("records" => $records) ) ;
		
	}

	public function validateUserRights($right = 'record_delete') {
        $this->canDelete = $this->checkUserRight($right);
        if (!empty($this->errors)) {
            self::renderErrorPage();
        }
	}

	/**
     * Return True/False of user has specified right
	 * @param $right
	 * @return bool
	 */
	public function checkUserRight($right) {

		# Check if Impersonification is active (trumps super-user)
		if(\UserRights::isImpersonatingUser()){
			$current_user = $_SESSION['impersonate_user'][PROJECT_ID]['impersonating'];
		} else {
			$current_user = defined("USERID") ? USERID : null;

			# If SuperUser - then let them through
			if (defined("SUPER_USER") && SUPER_USER) return true;
		}

		if (empty($current_user)) {
            $this->errors[] = "Missing required user id";
		    return false;
		}

		# Make sure user has permissions for project
		$userRights = \REDCap::getUserRights($current_user);
		$my_rights = isset( $userRights[$current_user] ) ? $userRights[$current_user] : null;
		if (!$my_rights[$right]) {
			$this->errors[] = "You must have '$right' privilege in user-rights to use this feature.";
            return false;
		}

		# Make sure the user's rights have not expired for the project
		if ($my_rights['expiration'] != "" && $my_rights['expiration'] < TODAY) {
			$this->errors[] = 'Your user account has expired for this project.  Please contact the project admin.';
            return false;
		}

        # Save rights
		$this->my_rights = $my_rights;
		return true;
	}

	/**
     * Only show the link to the EM if record-delete is set
	 * @param $project_id
	 * @param $link
	 * @return null
	 */
	public function redcap_module_link_check_display($project_id, $link) {
	    if ($this->checkUserRight('record_delete')) {
	        return $link;
	    } else {
	        return null;
	    }
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

