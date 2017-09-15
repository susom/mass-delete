<?php

$em = new \Stanford\MassDelete\MassDelete();

require_once \ExternalModules\ExternalModules::getProjectHeaderPath();

?>
<h1>This is the <?= $em->getModuleName() ?> project homepage!</h1>
<?php


// Page title
renderPageTitle("<img src='".APP_PATH_IMAGES."application_view_icons.png' class='imgfix2'>Mass Delete a bunch of records");

##### VALIDATION #####
# Make sure user has permissions for project or is a super user
$these_rights = REDCap::getUserRights(USERID);
$my_rights = $these_rights[USERID];
if (!$my_rights['design'] && !SUPER_USER) {
	showError('Project Setup rights are required to access MASS DET plugin.');
	exit;
}
# Make sure the user's rights have not expired for the project
if ($my_rights['expiration'] != "" && $my_rights['expiration'] < TODAY) {
	showError('Your user account has expired for this project.  Please contact the project admin.');
	exit;
}

// TODO - get records by arm only...
// Get all records (across all arms/events) and DAGs
$record_data = REDCap::getData('array',NULL,REDCap::getRecordIdField(),NULL,NULL,FALSE,TRUE);
$records = array_keys($record_data);

// Are DAGs enabled (check for presence of 'redcap_data_access_group' on the first record)
$first_record = reset($record_data);
$first_event = reset($first_record);
$dag_enabled = isset($first_event['redcap_data_access_group']);

global $Proj;
$records = array();

// Get the current Instuments
if ($Proj->longitudinal && $Proj->multiple_arms) {
    // Multi-arm-mode

    // Get record list per arm
	$arms_records = Records::getRecordListPerArm();

	// Get the current arm
	$arm = isset($_POST['arm']) ? $_POST['arm'] : 1;

	// Build select
	$arms_options = array();
	foreach ($Proj->events as $arm_num => $arm_detail) {
		$arms_options[] = "<option value='$arm_num'" .
			($arm_num == $arm ? " selected='selected'":"") . ">" .
			$arm_detail['name'] .
			(isset($arms_records[$arm_num]) ? " - " . count($arms_records[$arm_num]) . " records" : " - 0 records") .
            "</option>";
	}
	$records = array_keys($arms_records[$arm]);
} else {
    // Non-multi-arm mode
	$record_data = REDCap::getData('array',NULL,REDCap::getRecordIdField());
	$records = array_keys($record_data);
}

$dags = REDCap::getGroupNames();
if ( !empty($dags) ) {
    // Get the dag of the curernt user
    $user_rights = REDCap::getUserRights(USERID);
    $this_group_id = $user_rights[USERID]['group_id'];
    $this_group_id = 1;

    $this_group_name = REDCap::getGroupNames(true, $this_group_id);
    if ($this_group_id == '') {
        // Not assigned - gets all records!
	} else {
		// User is in a DAG, so filter records
        $dag_records = array();
		$record_data = REDCap::getData('array',NULL,REDCap::getRecordIdField(),NULL,NULL,FALSE,TRUE);
        foreach ($record_data as $record => $events) {
            $first_event = reset($events);
            $record_dag = $first_event['redcap_data_access_group'];
            if ($record_dag == $this_group_name) {
                $dag_records[] = $record;
            }
        }

        $records = array_intersect($records, $dag_records);

	}
}

print "<pre>DAGS" . print_r($dags,true) . "</pre>";


// Handle Post
$post_records = array();
if (isset($_POST['Run'])) {

	// DELETE THE RECORD
	print "Posting!";

	$arm_id = null;
	if (isset($_POST['arm']) && $Proj->longitudinal && $Proj->multiple_arms) {
		$arm_id = $Proj->getArmIdFromArmNum($_POST['arm']);
		// Error: arm is incorrecct
		if (!$arm_id) die("Invalid Arm ID from " . $_POST['arm']);

		// Set event_id (for logging only) so that the logging denotes the correct arm
		$_POST['event_id'] = $Proj->getFirstEventIdArm($_POST['arm']);
	}

	$post_records = $_POST['records'];
	$record_list = array_intersect($post_records,$records);

	//public static function deleteRecord($fetched, $table_pk, $multiple_arms, $randomization, $status,
	//									$require_change_reason, $arm_id=null, $appendLoggingDescription="")

	foreach ($record_list as $record) {
	    $table_pk = REDCap::getRecordIdField();
        print "<br>Deleting $record";

	    Records::deleteRecord(
            $record,
            $Proj->table_pk,
            $Proj->multiple_arms,
            $Proj->project_id['randomization'],
            $Proj->project['status'],
            $Proj->project['require_change_reason'],
            $arm_id,
            " (" . $em->getModuleName() . ")"
        );
    }
}



// Render Page
$cbx_array = array();
$max_length = 0;
foreach ($records as $record) {
	$cbx_array[] = "<input type='checkbox' name='records[]' value='$record' " .
		(in_array($record, $post_records) ? "checked" : "" ) .
		">$record";
	$max_length = max($max_length,strlen($record));
}
$max_length = $max_length + 3; // add space for checkbox


?>
<h3>This plugin will delete a bunch of records!</h3>
<form method='POST'>
    <div>
        <label for="arms">Select Arms: </label><select name='arms'><?php echo implode('',$arms_options) ?></select>


        <?php print $arms_select ?>
    </div>
    <fieldset>
        <legend>
            Select Records to Delete
            <span data-choice='all' class='sel jqbutton'/>All</span>
            <span data-choice='none' class='sel jqbutton'/>None</span>
            <span data-choice='custom' class='customList jqbutton'/>Custom List</span>
        </legend>
        <br/>
        <div class="wrapper">
            <ul><li><?php print implode("</li><li>",$cbx_array) ?></li></ul><br/>
        </div>
    </fieldset>
    <br/>
    <input type='submit' name='Run' class='jqbutton'/>
</form>
<style>
    button.sel {margin: 0px 10px; font-weight:bold; padding: 5px;}
    legend {font-weight: bold; font-size:larger;}
    fieldset {padding: 5px; max-width: 600px;}
    input.url { width: 600px;}
    form div {padding-bottom: 10px;}
    div.det_results {background: rgb(247,248,249); color: #333; padding:10px;}
    .wrapper {overflow:auto; max-height: 300px;}
    /*	.wrapper ul {width: <?php echo floor(500/$max_length) ?>em;} */
    .wrapper ul li {float:left; width: <?php echo $max_length ?>em; display:inline-block;}
    .wrapper br {clear:left;}
    .wrapper {margin-bottom: 1em;}
    .cr {width: 100%; height: 200px; overflow:auto;}
</style>
<script type='text/javascript'>
    $(document).ready( function() {
        $('span.sel').click( function() {
            var state = $(this).data('choice') == 'all';
            $('input[name="records[]"]').prop('checked',state);
            return false;
        });

        $('span.customList').click( function() {
            console.log("Here!");
            // Open up a pop-up with a list
            var data = "<p>Enter a comma-separated or return-separated list of record ids to select</p><textarea class='cr' name='custom_records' placeholder='Enter a comma-separated list of record_ids'></textarea>";
            initDialog("custom_records_dialog", data);
            $('#custom_records_dialog').dialog({ bgiframe: true, title: 'Enter Custom Record List',
                modal: true, width: 650,
                buttons: {
                    Close: function() {  },
                    Apply: function() {
                        // Parse out contents
                        var list = $('#custom_records_dialog textarea').val();
                        var items = $.map(list.split(/\n|,/), $.trim);
                        $(items).each(function(i, e) {
                            console.log (i, e);
                            $('input[value="' + e + '"]').prop('checked',true);
                        });
//					console.log($('#custom_records_dialog textarea').val());
                        $(this).dialog('close');
                    }
                }
            });
        });
    });

    function refreshArms() {
        console.log(this);
    }

</script>






print "<pre>" . print_r($Proj,true) . "</pre>";


print "<pre>" . print_r(REDCap::getInstrumentNames(),true) . "</pre>";


require_once \ExternalModules\ExternalModules::getProjectFooterPath();


#display an error from scratch
function showError($msg) {
	$HtmlPage = new HtmlPage();
	$HtmlPage->PrintHeaderExt();
	echo "<div class='red'>$msg</div>";
	//Display the project footer
	require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
}
