<?php
namespace Stanford\MassDelete;
/** @var MassDelete $module */

$module->init_page();

global $Proj;
$hasArms = $Proj->longitudinal && $Proj->multiple_arms;

$view = isset($_GET['view']) ? htmlspecialchars($_GET['view'], ENT_QUOTES) : "na";

?>

<div class="container">

    <?php if( isset($_POST['result'])):  ?>
        <p><a href="<?= $module->getUrl("page_mass_delete.php") ?> "><button class="btn btn-primary"><i class="fas fa-arrow-circle-left"></i> Click here to go back.</button></a></p>
    <?php else : ?>
        <div id="<?= $view ?>" class="row" >
            <div class="col">
                <form method="POST" class="delete_records">
                <input type="hidden" name="result" value="delete" >
                <input type="hidden" name="group_id" value="<?= $module->group_id ?>">
                <input type="hidden" name="mode" value="<?= $view ?>">
                <?php if($hasArms): ?>
                    <div class="form-group">
                        <label>Project arm</label>
                        <select id="arm-select" class="custom-select" name="arm">
                            <option disabled selected>Click to select project arm</option>
                            <?php
                                foreach ($Proj->events as $arm_num => $arm_detail) {
                                    echo "<option value='$arm_num'> {$arm_detail['name']}";                
                                }                            
                            ?>
                        </select>
                        <small id="selectHelpBlock" class="form-text text-muted">
                            This option has to be defined for longitudinal projects with multiple arms.
                        </small>
                    </div>
                <?php endif; ?>                       
                <?php if( $view == 'custom-list' ): ?>
                    <div class="form-group">
                        <div class="input-group">
                            <textarea <?= $hasArms ? "disabled" : "" ?> class="form-control list-input-step"  rows="10" aria-label="With textarea"></textarea>
                        </div>
                        <small id="validateHelpBlock" class="form-text text-muted">
                            Enter a comma-separated or return-separated list of numeric record ids
                        </small>
                        <small id="invalidInputBlock" class="invalid-feedback">
                            Invalid list entered
                        </small>
                        <small id="validInputBlock" class="valid-feedback">
                            Valid list entered
                        </small>
                    </div>
                    <div class="form-group">
                        <ul id="custom-output"></ul>
                    </div>
                <?php elseif( $view == 'record-list' ): ?>
                    <div id="fetchWrapper" class="form-group">
                        <p>Fetch all records for this project and select those which you would like to delete.</p>
                        <button <?= $hasArms ? 'disabled' : '' ?> id="btn-fetch-records" type="button" class="btn btn-primary list-input-step">
                            <span class="spinner-border spinner-border-sm hidden" role="status" aria-hidden="true"></span>
                            <span class="after-spinner-text">Fetch Records</span>
                        </button>
                        <small id="fetchHelpBlock" class="form-text text-muted">
                            This process may take some time if you have many records.
                        </small>                        
                    </div>
                    <div id="select-nav" class="form-group clearfix hidden">
                        <div class="btn-group float-right" role="group" aria-label="Basic example">
                            <button data-choice="all" type="button" class="btn btn-sm btn-secondary sel">All</button>
                            <button data-choice="none" type="button" class="btn btn-sm btn-secondary sel">None</button>
                        </div>
                    </div>
                    <div class="form-group">                 
                        <div class="card loading hidden">
                            <div class="card-body">
                                <div id="record-list-wrapper" class="wrapper">
                                    <ul id="record-output"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php $module->initEventForms(); ?>

                    <div class="form-group">
                        <button
                                id="btn-delete-selection"
                                class="btn btn-danger"
                                disabled>
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>

                </form>
            </div>
        </div>
    <?php endif; ?>

</div>