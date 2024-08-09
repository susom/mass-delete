<?php
namespace Stanford\MassDelete;

use RCView;
use \Exception;
use \Records;
require_once 'RepeatingForms.php';

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

	public function render_event_forms() {
        $this->renderSectionHeaderForms();
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
				  		RCView::fa('fas fa-times-circle fs15 mr-1') . $this->getModuleName() . ' Records') .
					RCView::div(array('class'=>'clear'), '')
			  	);

		print   RCView::p('', 'This section is used to delete a large number of records. You can either add a custom list of records or select from your record list for deletion.');
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

            // See if we are deleting forms in events or whole records
            $form_event = $_POST['form_event'];

            // Delete the whole record
            if ($Proj->longitudinal && $Proj->multiple_arms) {

                $arm = $_POST['arm'];
                $this->arm = $arm;
                $this->arm_id = $Proj->getArmIdFromArmNum($arm);
                $this->records = \Records::getRecordList($Proj->project_id, $this->group_id, false, false, $arm);
            } else {
                $this->records = \Records::getRecordList($Proj->project_id, $this->group_id);
            }

            // Determine which records we need to delete
            $post_records = $_POST['records'];
            $valid_records = array_intersect($post_records, $this->records);

            if (count($valid_records) != count($post_records)) {
                $this->errors[] = "Invalid records were requested for deletion.  Please try again.";
            } else {

                if (empty($form_event)) {

                    // DELETE THE RECORD
                    foreach ($valid_records as $record) {
                        \Records::deleteRecord(
                            $record,
                            $Proj->table_pk,
                            $Proj->multiple_arms,
			    #PHP81 Fix
                            $Proj->project['randomization'],			   			    
                            $Proj->project['status'],
                            $Proj->project['require_change_reason'],
                            $this->arm_id,
                            " (" . $this->getModuleName() . ")"
                        );
                    }

                    $this->notes[] = "<b>Deleted " . count($valid_records) . " record" .
                        (count($valid_records) > 1 ? "s" : "") .
                        "</b>";
                } else {

                    // Figure out the form and event that we are deleting
                    $deleted_forms = '';
                    foreach ($form_event as $one_form_event) {

                        // Split out the form and event
                        list($selected_event_name, $selected_event_id, $selected_form) = $this->splitFormEvent($one_form_event);
                        if (empty($selected_event_name)) {
                            $deleted_forms .= '   <li> ' . $selected_form;
                        } else {
                            $deleted_forms .= '   <li>[' . $selected_event_name . '] ' . $selected_form;
                        }

                        // See if this is a repeating
                        $repeating = $Proj->getRepeatingFormsEvents();

                        $repeat_form = false;
                        if (!empty($repeating) && !is_null($repeating[$selected_event_id])) {
                            // If this event is repeating, make sure this form is repeating or the whole event is repeating
                            $forms_in_event = array_keys($repeating[$selected_event_id]);
                            if (in_array($selected_form, $forms_in_event) or
                                ($repeating[$selected_event_id] == 'WHOLE')) {
                                $repeat_form = true;
                            }
                        }

                        $this->deleteForm($selected_event_id, $selected_form, $repeat_form, $valid_records);

                    }
                    $this->notes[] = "<b>Deleted forms " . $deleted_forms . "<br> for " . count((array)$valid_records) . " record" .
                        (count((array)$valid_records) > 1 ? "s" : "") .
                        "</b>";
                }
            }
        }
	}

	public function renderErrorPage($msg = '') {

		print $this->renderAlerts($this->errors);
		exit();
	}

    public function initEventForms() {

        $this->render_event_forms();
        $this->getFormEventList();

    }

    public function renderSectionHeaderForms() {

        print	RCView::div(array('style'=>'max-width:750px;margin-bottom:10px;'),
            RCView::div(array('style'=>'color: #800000;font-size: 16px;font-weight: bold;float:left;'),
                RCView::fa('fas fa-times-circle fs15 mr-1') . $this->getModuleName() . ' Forms') .
            RCView::div(array('class'=>'clear'), '')
        );

        print   RCView::p('', 'This section is used to delete a large number of forms (either repeating or non-repeating). You can select the event/form where you want to delete data. <b>If you want to delete the complete record, DO NOT select any forms.</b>');
    }


    public function getFormEventList() {
        global $Proj;

        $forms =  \RecordDashboard::renderSelectedFormsEvents();
        //$forms = $Proj->renderSelectedFormsEvents();
        print $forms;
    }

    public function splitFormEvent($selected_form_event) {

        global $Proj;

        // The format is 'ef-' . event name . '-' . form name for longitudinal projects
        // The format is 'ef--' . form name for class projects
        $pieces = explode("-", $selected_form_event);

        // Find the event_id. If no event_name is specified, there is only one event
        if (empty($pieces[1])) {
            $event_id = $Proj->firstEventId;
        } else {
            $event_id = null;
            $events = \REDCap::getEventNames(true);
            foreach ($events as $event_num => $event_name) {
                if ($event_name == $pieces[1]) {
                    $event_id = $event_num;
                    break;
                }
            }
        }

        return array($pieces[1], $event_id, $pieces[2]);

    }

    public function deleteForm($selected_event_id, $selected_form, $repeating_form, $record_list) {

        $proj_id = $this->getProjectId();
	    if ($repeating_form) {
            // Using the repeating class to delete instances
            try {
                $rf = new RepeatingForms($proj_id, $selected_form);
            } catch (Exception $ex) {
                //Plugin::log("Exception when creating class RepeatingForms");
                return;
            }
            foreach($record_list as $record_id) {
                $rf->loadData($record_id, $selected_event_id);
                $all_instances = $rf->getAllInstanceIds($record_id, $selected_event_id);
                foreach($all_instances as $instance_id) {
                    $log_id = $rf->deleteInstance($record_id, $instance_id, $selected_event_id);
                    //Plugin::log("Delete record $record_id, event_id $selected_event_id, instance $instance_id with log id $log_id");
;               }
            }

        } else {

            foreach($record_list as $record_id) {
                $log_id = Records::deleteForm($proj_id, $record_id, $selected_form, $selected_event_id, null);
            }
        }
    }

}

