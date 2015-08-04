$(document).ready(function(){

    /**
     * Initialise application when OAuth authentication is done
     * @param  {String} access_token valid OAuth2 access token
     * @return {undefined}
     */
    var initApplication = function(access_token) {

        // Check for authorisation
        if (! access_token) {
            showError('Geen toegang: je bent uitgelogd of je bent geen bestuur.<br> Herlaad de pagina om opnieuw te proberen.');
            return;
        }
        else {
            hideError();
        }

        // Store access token
        window.access_token = access_token;

        // Compile template
        var template_row = Handlebars.compile($("#row").html());

        // Load all passes
        $.ajax({
            url: '/users?access_token='+window.access_token,
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
            error: function(error) {
                showError('Foutmelding bij communicatie met server', error.status + '-' + error.responseURL);
            }
        });

        // Event handlers
        $('#passes').on('click', '.status.access', changeAccess);
        $('#passes').on('click', '.status.pass', changePass);
    }

    /**
     * Show an error message
     * @param  {Error} XMLHttpRequest error object
     * @return {undefined}
     */
    var showError = function(message, technical) {
        if (technical === undefined) {
            $('#error').html(message).show();
        }
        else {
            $('#error').html(message+'<br><br>Technische details: '+technical).show();
        }
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
                url: '/users/'+$(this).attr('data-uid')+'?access_token='+window.access_token,
                type: 'DELETE',
                dataType: 'json',
                error: function(error) {
                    showError('Foutmelding bij communicatie met server', error.status + '-' + error.responseURL);
                }
            });
        }
        else {

            // Optimistic interface update
            $(this).removeClass('no').addClass('yes').html('&check; krijgt toegang');

            // Send call
            $.ajax({
                url: '/users/'+$(this).attr('data-uid')+'?access_token='+window.access_token,
                type: 'POST',
                dataType: 'json',
                error: function(error) {
                    showError('Foutmelding bij communicatie met server', error.status + '-' + error.responseURL);
                }
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
                url: '/users/'+$(this).attr('data-uid')+'/pass?access_token='+window.access_token,
                type: 'DELETE',
                dataType: 'json',
                error: function(error) {
                    showError('Foutmelding bij communicatie met server', error.status + '-' + error.responseURL);
                }
            });
        }
        else {

            // Optimistic interface update
            $(this).removeClass('no').addClass('yes').html('&check; heeft pas');

            // Send call
            $.ajax({
                url: '/users/'+$(this).attr('data-uid')+'/pass?access_token='+window.access_token,
                type: 'POST',
                dataType: 'json',
                error: function(error) {
                    showError('Foutmelding bij communicatie met server', error.status + '-' + error.responseURL);
                }
            });
        }
    }

    // Start by authenticating to OAuth
    var oauth = new OAuth(config);
    oauth.authenticate(initApplication);
});
