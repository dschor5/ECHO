// Get the modal
var modal = document.getElementById('loginform');

// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

$(document).ready(function() {
    $('#butlogin').on('click', function() {
		var username = $('#uname').val();
		var password = $('#upass').val();
		if(username != '' && password != '') {
			$.ajax({
				url:  base_url.concat('/login'),
				type: "POST",
				data: {
					uname: username,
					upass: password						
				},
				statusCode: {
                    200: function() {
                        location.href = base_url.concat('/chat');
                    },
                    201: function() {
                        $('#response').text('Invalid username or password.');
                        $('#response').show();
                    }
                }
			});
		}
		else{
			$('#response').text('Invalid username or password.');
            $('#response').show();
		}
	});
});