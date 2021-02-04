$(function() {
    'use strict';

        var arm_id;
        var group_id;
        var selection;

        var btn_delete_selection = $('#btn-delete-selection');
        var btn_validate_input = $('#btn-validate-input');

        // A $( document ).ready() block.
        $( document ).ready(function() {    
            group_id = $('input[name="group_id"]').val();

            $('#arm-select').on('change', (e) => {
                arm_id = e.target.value;
                $('.list-input-step').prop("disabled", false);
                setView('fetch');
            })
    
            $('.list-input-step').on('input', () => {
                btn_validate_input.prop("disabled", false);
            })
    
            btn_validate_input.on('click', fetchInputs )
    
            $('#btn-fetch-records').on('click', fetchRecords )
    
            $('.sel').click( function() {
                var state = $(this).data('choice') == 'all';
                $('input[name="records[]"]').prop('checked', state);
                setSelection();

                return false;
            });

            $('#btn-delete-selection').click( function() {

                var num_selected = selection.length;
                
                initDialog("confirmDeletion");
                $('#confirmDeletion')
                    .html("Are you sure you want to delete the selected "+num_selected+" records? This is permanent.")
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

        })

        function fetchInputs() {
            var list = $('.list-input-step').val();
            var items = $.map(list.split(/\n|,/), $.trim).filter(Boolean);
            renderCustomList(items);
            setSelection()

            if(items.length > 0 ) {
                $('.list-input-step').addClass('is-valid');
            } else {
                $('.list-input-step').addClass('is-invalid');
            }
        }

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

var Stanford_MassDelete = {};