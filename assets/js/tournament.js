// Auto-dismiss alerts after 5 seconds
$(document).ready(function() {
    setTimeout(function() {
        $('#successAlert, #errorAlert').alert('close');
    }, 5000);
});
