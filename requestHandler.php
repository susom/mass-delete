<?php
/** @var \ Stanford\MassDelete $module */

if ($_REQUEST['type'] == 'triggerFetchRecords') {
    $module->fetchRecords(
        $_POST["arm_id"],
        $_POST["group_id"]
    );
}