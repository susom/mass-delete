<?php
namespace Stanford\MassDelete;
/** @var MassDelete $module */

$module->validateUserRights();

if ($module->canDelete) {
	if ($_REQUEST['type'] == 'triggerFetchRecords') {
		$module->fetchRecords(
			$_POST["arm_id"],
			$_POST["group_id"]
		);
	}
}
