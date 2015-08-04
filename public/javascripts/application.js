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

    /**
     * Change the access grant of a user
     * @param  {Event} event click event of the link
     * @return {undefined}
     */
    var changeAccess = function(event) {
        event.preventDefault();

        // Determine whether to grant or deny
        if ($(this).hasClass('yes')) {

            // Optimistic interface update
            $(this).removeClass('yes').addClass('no').html('&cross; geen toegang');

            // Send call
            $.ajax({
                url: '/users/'+$(this).attr('data-uid'),
                type: 'DELETE',
                dataType: 'json',
                error: showError
            });
        }
        else {

            // Optimistic interface update
            $(this).removeClass('no').addClass('yes').html('&check; krijgt toegang');

            // Send call
            $.ajax({
                url: '/users/'+$(this).attr('data-uid'),
                type: 'POST',
                dataType: 'json',
                error: showError
            });
        }
    }

    /**
     * Add or remove the pass of a users
     * @param  {Event} event click event of the link
     * @return {undefined}
     */
    var changePass = function(event) {
        event.preventDefault();

        // Determine whether to grant or deny
        if ($(this).hasClass('yes')) {

            // Optimistic interface update
            $(this).removeClass('yes').addClass('no').html('&cross; geen pas');

            // Send call
            $.ajax({
                url: '/users/'+$(this).attr('data-uid')+'/pass',
                type: 'DELETE',
                dataType: 'json',
                error: showError
            });
        }
        else {

            // Optimistic interface update
            $(this).removeClass('no').addClass('yes').html('&check; heeft pas');

            // Send call
            $.ajax({
                url: '/users/'+$(this).attr('data-uid')+'/pass',
                type: 'POST',
                dataType: 'json',
                error: showError
            });
        }
    }

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

    // Event handler:
    $('#passes').on('click', '.status.access', changeAccess);
    $('#passes').on('click', '.status.pass', changePass);
});

