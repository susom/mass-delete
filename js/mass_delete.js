$(function() {
    'use strict';

        var arm_id;
        var group_id;
        var selection;
        var record_list;

        var btn_delete_selection = $('#btn-delete-selection');
        var txtarea_custom_list = $('.list-input-step');

        // A $( document ).ready() block.
        $( document ).ready(function() {    
            group_id = $('input[name="group_id"]').val();

            //  input validation on change: accept only comma - or return separated values
            txtarea_custom_list.on('change keyup paste', function(){

                var content = $('.list-input-step').val();
                var list = $.map(content.split(/\n|,/), $.trim).filter(Boolean);

                if(list.length > 0 ) {

                    var isValid=true;

                    // Retrieve list of current records so we can compare against records entered
                    fetchRecords();

                    for (let index = 0; index < list.length; index++) {
                        //  Set to invalid if the record does not exist in the project
                        if ($.inArray(list[index], record_list) < 0) {
                            $('.list-input-step').removeClass('is-valid').addClass('is-invalid');
                            $('#validateHelpBlock').hide();
                            $('#validInputBlock').hide();
                            $('#invalidInputBlock').show();
                            btn_delete_selection.prop("disabled", true);
                            isValid=false;
                            index = list.length;    // break loop since there is invalid input already
                        }
                    }

                    if(isValid){
                        //  Set to valid
                        $('.list-input-step').removeClass('is-invalid').addClass('is-valid');
                        $('#validateHelpBlock').hide();
                        $('#invalidInputBlock').hide();
                        $('#validInputBlock').show();
                        btn_delete_selection.prop("disabled", false);
                        renderCustomList(list);
                        setSelection()
                    }

                } else {
                    //  Back to default
                    $('.list-input-step').removeClass('is-valid is-invalid');
                    btn_delete_selection.prop("disabled", true);
                    $('#validateHelpBlock').show();
                    $('#invalidInputBlock').hide();
                    $('#validInputBlock').hide();
                }

            })

            $('#arm-select').on('change', (e) => {
                arm_id = e.target.value;
                $('.list-input-step').prop("disabled", false);
                setView('fetch');
            })
    
            $('#btn-fetch-records').on('click', fetchRecords )
    
            $('.sel').click( function() {
                var state = $(this).data('choice') == 'all';
                $('input[name="records[]"]').prop('checked', state);
                setSelection();

                return false;
            });

            $('#btn-delete-selection').click( function() {

                var num_selected = selection.length;
                var num_selected_forms = $('input[name="form_event[]"]:checked').length;
                var confirm_text;
                if (num_selected_forms > 0) {
                    confirm_text = "Are you sure you want to delete "+num_selected_forms+" forms for "+num_selected+" records? This is permanent.";
                } else {
                    confirm_text = "Are you sure you want to delete the selected "+num_selected+" records? This is permanent.";
                }
                
                initDialog("confirmDeletion");
                $('#confirmDeletion')
                    .html(confirm_text)
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
    
                return false;
            });

            // The checkboxes created from REDCap do not have a value set so no value comes through the form submit
            // create a value attribute to be the same as the id.
            var all_ckbx = $('#choose_select_forms_events_div_sub input[id^="ef-"]');
            $.each(all_ckbx, function (key, val) {
                var value = $(val).attr('id');
                $(val).attr("value", value);
                $(val).attr("name", "form_event[]")

            });

            // Take off the buttons and title that we don't want with the checkboxes.
            $("#select_links_forms button").remove();
            $("#select_links_forms a").first().css("margin-left", "5px");
            var title = $("#choose_select_forms_events_div_sub div").first();
            title.html("");

        })

        function renderCustomList(records) {

            $('#custom-output').empty();

            records.forEach(function(record){
                var inputnode = document.createElement("input");
                inputnode.type = "hidden";
                inputnode.value = record;
                inputnode.name = "records[]";
                document.getElementById("custom-output").appendChild(inputnode);
            })
        }


        function fetchRecords() {

            setView('fetching');
            $.ajax({
                method: 'POST',
                url: Stanford_MassDelete.requestHandlerUrl + '&type=triggerFetchRecords',
                dataType: 'json',
                data: {
                    arm_id: arm_id,
                    group_id: group_id
                },
                success: function(data) {
                    renderRecordList(data.records);
                    record_list = data.records.toString().split(",");
                },
                error: function(data) {
                    alert("Unknown Error: Could not fetch records.")
                }
            });

        }

        function renderRecordList(records) {

            $('.card').removeClass("hidden");
            records.forEach(function(chunk, index){
    
                setTimeout(() => {
                    chunk.forEach(function(value){                         
                        var node = document.createElement("LI");
                        var textnode = document.createTextNode(" "+value);
                        var inputnode = document.createElement("input");
                        inputnode.type = "checkbox";
                        inputnode.value = value;
                        inputnode.name = "records[]";
                        node.appendChild(inputnode);
                        node.appendChild(textnode);
                        document.getElementById("record-output").appendChild(node);
                    })
                }, 0.001);
            })
    
            setTimeout(() => {
                setView('fetched');
                registerListenerRecordList();
            }, 0.01);        
        }

        function registerListenerRecordList(){
            $('input[name="records[]"]').on('change', setSelection )
        }

        function setSelection() {

            var mode = $('input[name="mode"]').val();

            if(mode == 'record-list') {
                selection = $('input[name="records[]"]:checked').map(function(){
                    return $(this).val();
                  }).get();
            } else {
                selection = $('input[name="records[]"]').map(function(){
                    return $(this).val();
                  }).get();
            }

            if( selection.length > 0 ) {
                btn_delete_selection.prop("disabled", false);
            } else {
                btn_delete_selection.prop("disabled", true);
            }

        }

        function setView(mode) {
            if(mode == 'fetch') {
                $('#btn-fetch-records .after-spinner-text').text("Fetch Records");
                $('.card').addClass("hidden");
                $('.card').addClass("loading");
                $('#record-output').empty();
            } 
            if(mode == 'fetching') {
                $('#btn-fetch-records .spinner-border').removeClass('hidden');
                $('#btn-fetch-records .after-spinner-text').text("Fetching..");
                $('#arm-select').prop("disabled", true);
            }
            if(mode == 'fetched') {
                $('.card').removeClass("loading");
                $('#select-nav').removeClass("hidden");
                $('#btn-fetch-records .spinner-border').addClass('hidden');
                $('#btn-fetch-records .after-spinner-text').text("Fetched");
                $('#btn-fetch-records').prop("disabled", true);
                $('#arm-select').prop("disabled", false);
            }
        }

})


function selectAllInEvent(event_name,ob) {
    $('#choose_select_forms_events_div_sub input[id^="ef-'+event_name+'-"]').prop('checked',$(ob).prop('checked'));
}

function selectAllFormsEvents(select_all) {
    $('#choose_select_forms_events_div_sub input[type="checkbox"]').prop('checked',select_all);
}

var Stanford_MassDelete = {};