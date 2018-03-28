<?php

$massDelete = new \Stanford\MassDelete\MassDelete();


require_once \ExternalModules\ExternalModules::getProjectHeaderPath();

// Page title
$massDelete->validateUserRights('design');
$massDelete->determineRecords();
$massDelete->handlePost();


// Render Page
renderPageTitle("<img src='".APP_PATH_IMAGES."application_view_icons.png' class='imgfix2'>&nbsp;Mass Delete a bunch of records");

if (!empty($massDelete->errors)) {
	print "<div class='alert alert-danger'><ul><li>" . implode("</li><li>", $massDelete->errors) . "</li></ul></div>";
}

if (!empty($massDelete->notes)) {
    print "<div class='alert alert-success'><ul><li>" . implode("</li><li>", $massDelete->notes) . "</li></ul></div>";
}

?>
<form class="delete_records" method='POST'>
<!--<div class="container">-->
    <!--<div class="row">-->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4>Mark Records for Deletion</h4>
					<?php if (!empty($massDelete->arm_options)) { ?>
                        <div>
                            <label for="arms">Arm:&nbsp;</label><select name="arm"><?php echo implode('',$massDelete->arm_options) ?></select>
                        </div>
					<?php } ?>

                </div>
                <div class="panel-body">
                    <div class="wrapper">
                        <ul><li><?php print implode("</li><li>",$massDelete->record_checkboxes) ?></li></ul>
                    </div>
                </div>
                <div class="panel-footer">
                    <span data-choice='all'    class="btn btn-default sel"/>All</span>
                    <span data-choice='none'   class='btn btn-default sel'/>None</span>
                    <span data-choice='custom' class='btn btn-default customList'/>Custom List</span>
                    <span id="delete" data-choice='delete' class="btn btn-danger">Delete Selected Records</span>
                </div>
            </div>
    <!--</div>   name="Run" value="Delete Selected Records" -->
<!--</div>-->
</form>

<style>
    div.alert { border: inherit !important; }
    .wrapper {overflow:auto; max-height: 300px;}
    .wrapper ul li {float:left; width: <?php echo $massDelete->max_length ?>em; display:inline-block;}
    .wrapper br {clear:left;}
    .wrapper {margin-bottom: 1em;}
    .cr {width: 100%; height: 200px; overflow:auto;}
    .delete_btn { background-color: red; }
    .delete_btn:hover { background-color: red; background-image: none; }
</style>
<script type='text/javascript'>
    $(document).ready( function() {
        $('span.sel').click( function() {
            var state = $(this).data('choice') == 'all';
            $('input[name="records[]"]').prop('checked',state);
            return false;
        });

        $('#delete').click( function() {
            var num_selected = $('input[name="records[]"]:checked').length;
            if (num_selected == 0) {
                simpleDialog('<b>You must first at least one record</b>');
                return false;
            }

            initDialog("confirmDeletion");
            $('#confirmDeletion')
                .html("Are you sure you want to delete the " + num_selected + " selected records!  This is permanent")
                .dialog({
                    title: "Confirm Deletion",
                    bgiframe:true,
                    modal:true,
                    width:550,
                    close: function() { $(this).dialog('destroy'); },
                    open: function(){ fitDialog(this) },
                    buttons: {
                        'Cancel': function() { $(this).dialog('destroy'); },
                        'Delete': function() {
                            $(this).dialog('destroy');
                            showProgress(1);
                            var input = $("<input>")
                                .attr("type", "hidden")
                                .attr("name", "delete").val("true");
                            $('form.delete_records').append(input).submit();
                        }
                    },
                    create:function () {
                        var b = $(this).closest(".ui-dialog")
                            .find(".ui-dialog-buttonset .ui-button:last").addClass("delete_btn");
                    }
                });

            console.log (num_selected);
            // var state = $(this).data('choice') == 'all';
            // $('input[name="records[]"]').prop('checked',state);
            return false;
        });


        $('span.customList').click( function() {
            // Open up a pop-up with a list
            var data = "<p>Enter a comma-separated or return-separated list of record ids to select</p><textarea class='cr' name='custom_records' placeholder='Enter a comma-separated list of record_ids'></textarea>";
            initDialog("custom_records_dialog", data);
            $('#custom_records_dialog').dialog({ bgiframe: true, title: 'Enter Custom Record List',
                modal: true, width: 650,
                buttons: {
                    'Close': function() {  },
                    'Apply': function() {
                        // Parse out contents
                        var list = $('#custom_records_dialog textarea').val();
                        var items = $.map(list.split(/\n|,/), $.trim);
                        var notFound = [];
                        var countChecked = 0;
                        var countTotal = 0;
                        $(items).each(function(i, e) {
                            // console.log (i, e);
                            // Skip empties
                            if (e == '') return true;

                            // Verify the record is valid
                            var record = $('input[value="' + e + '"]');
                            if (record.length) {
                                record.prop('checked',true);
                                countChecked++;
                            } else {
                                // Not found
                                notFound.push(e);
                            }
                            countTotal++;
                        });
                        $(this).dialog('close');
                        // console.log(notFound);
                        var msg = '<b>' + countChecked + ' of ' + countTotal + ' records checked from custom list</b>';
                        if (notFound.length > 0) msg += '<br>&nbsp;- Unable to find:<br><ul><li>' + notFound.join('</li><li>') + '</li></ul>';
                        simpleDialog(msg, "Record Selection Results");
                    }
                }
            });
        });
    });

    $('select[name="arm"]').on('change',function() {
        $(this).closest('form').submit();
    });


</script>



<?php



// print "<pre>" . print_r($Proj,true) . "</pre>";
// print "<pre>" . print_r(REDCap::getInstrumentNames(),true) . "</pre>";

require_once \ExternalModules\ExternalModules::getProjectFooterPath();

// #display an error from scratch
// function showError($msg) {
// 	$HtmlPage = new HtmlPage();
// 	$HtmlPage->PrintHeaderExt();
// 	echo "<div class='red'>$msg</div>";
// 	//Display the project footer
// 	require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
// }
