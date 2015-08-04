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

    // Compile template
    var template_row = Handlebars.compile($("#row").html());

    // Load all passes
    $.ajax({
        url: '/users',
        type: 'GET',
        dataType: 'json',
        success: function(passes) {
            // Sort by name
            passes.sort(function(a,b){
                return a.name.toLowerCase() > b.name.toLowerCase() ? 1 : -1;
            });
            // Add to UI
            $(passes).each(function(){
                $('#passes tbody').append(template_row(this));
            });
            $('#spinner').remove();
        },
        error: showError
    });
});
