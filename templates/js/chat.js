/*
$(document).ready(setTimeout(refreshContents, 1000));

function refreshContents() {
    var response = $.ajax({
        url: '%http%%site_url%/chat',
        type: 'POST',
        data: {
            subaction: 'refresh',
        },
        dataType: 'json',
        success: function(data) {
            $('#time-mcc-value').text(data.time_mcc);
            $('#time-hab-value').text(data.time_hab);  
               
            setTimeout(refreshContents, 1000)
        }
    });
}

function receiveMessage(data) {

}

$(document).ready(function() {
    $('#newmsg-send-btn').on('click', function() {
		var msgRoom = $('#newmsg-room').val();
        var msgBody = $('#newmsg-text').val();
        
		if(msgTo != '' && msgBody != '') {
			$.ajax({
				url:  '%http%%site_url%/chat/send',
				type: "POST",
				data: {
					msgRoom: msgRoom,
					msgBody: msgBody						
				},
                dataType: 'json'
			});
		}
	});
});
*/

const evtSource = new EventSource("%http%%site_url%/chat/refresh");

evtSource.onopen = function () {
    console.info("EventSource connected.");
};
  
evtSource.onerror = function (err) {
    console.error("EventSource failed:", err);
};

evtSource.addEventListener("time", function(event) {
    const data = JSON.parse(event.data);
    $('#time-mcc-value').text(data.time_mcc);
    $('#time-hab-value').text(data.time_hab);  
});
