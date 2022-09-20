function saveConfig(subaction){
    let dataObj = {
        'action': 'admin', 
        'subaction': 'save_' + subaction,
    };

    $('input').each(function() {
        let objName = $(this).attr('name');
        console.log(objName);
        if(objName.indexOf('[]') >= 0) {
            objName = objName.substring(0, objName.length-2);
            console.log('Remove [] -> ' + objName);
            if(dataObj[objName] == undefined) {
                dataObj[objName] = [];
            }
            dataObj[objName][dataObj[objName].length] = $(this).val();
        }
        else if($(this).prop('type') == "checkbox") {
            dataObj[$(this).attr('name')] = $(this).prop('checked') ? '1' : '0';
        }
        else {
            dataObj[$(this).attr('name')] = $(this).val();
        }
    });

    $('select').each(function() {
        let selectName = $(this).attr('name');
        let selectValue = $('#' + selectName + ' option:selected').val();
        dataObj[selectName] = selectValue;
    });

    $('.dialog-response').hide();
    $('.dialog-success').hide();

    $.ajax({
        url:  BASE_URL + "/ajax",
        type: "POST",
        data: dataObj,
        dataType: 'json',
        success: function(resp) {
            if(resp.success) {
                $('.dialog-success').show('highlight');
            }
            else {
                $('.dialog-response').html(resp.error.join('<br>'));
                $('.dialog-response').show('highlight');
            }
        },
    });
}

$(document).ready(function() {
    if($('#date_start').length > 0) {
        var fromDate = $('#date_start').datepicker({
            dateFormat: 'yy-mm-dd'
        }).on('change', function() {
            toDate.datepicker('option', 'minDate', getDate(this));
        }),
        toDate = $('#date_end').datepicker({
            dateFormat: 'yy-mm-dd'
        }).on('change', function() {
            fromDate.datepicker('option', 'maxDate', getDate(this));
        });
    }
});

function getDate(element) {
    let date;
    try {
        date = $.datepicker.parseDate('yy-mm-dd', element.value);
    }
    catch(error) {
        date = null;
    }
    return date;
}

$(document).ready(function () {
    if($('#delay_type').length > 0) {

        $("body").on("click", ".add_delay_btn", function (e) {
            var template = document.querySelector('#delay_auto_template');
            if('content' in document.createElement('template'))
            {
                var objClone = template.content.cloneNode(true);
                document.querySelector('#delay-config-opts').appendChild(objClone);
                $('.delay_date').last().uniqueId();
                $('.delay_date').last().datepicker({ dateFormat: 'yy-mm-dd' });
                $('.delay_time').last().uniqueId();
                $('.delay_time').last().timespinner();
                $('.delay_time').last().width(65);
            }

            $('.del_delay_btn').prop('disabled', false);
            $('.del_delay_btn').first().prop('disabled', true);
        });

        $('.delay_date').each( function() {
            $(this).uniqueId();
            $(this).datepicker({ dateFormat: 'yy-mm-dd' });
        });

        $( function() {
            Globalize.culture( 'de-DE' );

            $.widget( "ui.timespinner", $.ui.spinner, {
              options: {
                // seconds
                step: 60 * 1000,
                // hours
                page: 60
              },
         
              _parse: function( value ) {
                if ( typeof value === "string" ) {
                  // already a timestamp
                  if ( Number( value ) == value ) {
                    return Number( value );
                  }
                  return +Globalize.parseDate( value );
                }
                return value;
              },
         
              _format: function( value ) {
                return Globalize.format( new Date(value), "t" );
              }
            });

            $('.delay_time').each( function() {
                $(this).uniqueId();
                $(this).timespinner();
                $(this).width(65);
            });
        });
        
        $('.del_delay_btn').prop('disabled', false);
        $('.del_delay_btn').first().prop('disabled', true);

        $("body").on("click", ".del_delay_btn", function () {
            $(this).closest(".delay_config_row").remove();
            console.log("success");
        });

        showDelayOptions();
    }
});

function showDelayOptions() {
    $('.delay_timed').css('display', 'none');
    $('.delay_manual').css('display', 'none');
    $('.delay_mars').css('display', 'none');
    
    if($('#delay_type').val() == 'mars') {
        $('.delay_mars').css('display', 'block');
    }
    else if($('#delay_type').val() == 'timed') {
        $('.delay_timed').css('display', 'block');
    }
    else 
    {
        $('.delay_manual').css('display', 'block');
    }
}

function threadSuboptions() {
    if($('#feat_convo_threads').prop('checked')) {
        $('#feat_convo_threads_all').removeAttr('disabled');
    }
    else {
        $('#feat_convo_threads_all').attr('disabled', true);
    }
}