$(document).ready(function(){

    /**
     * Show an error message
     * @param  {Error} XMLHttpRequest error object
     * @return {undefined}
     */
    var showError = function(error) {
        console.log(Error(error));
        $('#error').html('Fatale fout bij communiceren met server.<br><br>Technische details: '+error.status+' '+error.responseURL).show();
    };

    /**
     * Hide the error message
     * @return {undefined}
     */
    var hideError = function() {
        $('#error').html('').hide();
    };

    // Load all passes
    $.ajax({
        url: '/passes',
        type: 'GET',
        dataType: 'json',
        success: function(passes) {
            $(passes).each(function(pass){
                var row = $('<tr><td>'+pass.name+'</td><td>'+pass.id+'</td></tr>');
                row.appendTo('#passes tbody');
            });
        },
        error: showError
    });
});
