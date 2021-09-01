function saveConfig(){
    var dataObj = {'action': 'settings', 'subaction': 'save'};
    $('input').each(function() {
        dataObj[$(this).attr('name')] = $(this).val();
    });

    $('select').each(function() {
        let selectName = $(this).attr('name');
        let selectValue = $('#' + selectName + ' option:selected').val();
        dataObj[selectName] = selectValue;
    });

    $.ajax({
        url:  BASE_URL,
        type: "POST",
        data: dataObj,
        dataType: 'json',
        success: function(resp) {
            if(resp.success) {
                //location.href = BASE_URL + '/settings';
            }
            else {
                console.log(resp.success);
                $('.dialog-response').html(resp.error.join('<br>'));
                $('.dialog-response').show('highlight');
            }
        },
    });
}