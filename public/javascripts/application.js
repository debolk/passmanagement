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

        // Load members for form
        $.ajax({
            url: 'https://people.debolk.nl/members/list?access_token='+window.access_token,
            type: 'GET',
            dataType: 'json',
            success: function(members) {
                // Sort by name
                members.sort(function(a,b){
                    return a.name.toLowerCase() > b.name.toLowerCase() ? 1 : -1;
                });
                // Add to select
                $(members).each(function(){
                    $('#user_id').append($('<option>').val(this.uid).html(this.name));
                });
            },
            error: function(error) {
                showError('Foutmelding bij communicatie met server', error.status + '-' + error.responseURL);
            }
        });

        // Bind event handlers
        $('#passes').on('click', '.status.access', changeAccess);
        $('#passes').on('click', '.status.pass', changePass);
        $('#valid_pass').on('click', checkPass);
        $('#new_pass').on('submit', addPass);
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

            // This action is irreversible without the pass
            if (! confirm('Je kunt deze pas niet meer toevoegen zonder de pas opnieuw te scannen. '
                                +'Weet je zeker dat je de pas wilt verwijderen?')) {
                return;
            }

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
            alert('Je kunt geen pas toevoegen op deze manier. Gebruik het formulier onderaan de pagina');
        }
    };

    /**
     * Check the pass has been scanned
     * @param  {Event} event click event of the link
     * @return {undefined}
     */
    var checkPass = function(event) {

        event.preventDefault();

        var button = $(this);
        var result = $('#pass_result');
        var form_submit = $('#submit');

        result.html('<img src="images/spinner.gif" width="16" height="16">');
        button.prop('disabled', true);
        form_submit.prop('disabled', true);

        // Send call
        $.ajax({
            url: '/deur/checkpass?access_token='+window.access_token,
            type: 'GET',
            dataType: 'json',
            success: function(answer) {

                // Enable button for recheck
                button.prop('disabled', false);

                // Update response text
                if (answer.check == 'door_response_not_okay') {
                    result.html('Kan de deur niet bereiken');

                }
                else if (answer.check == 'pass_mismatch') {
                    result.html('Laatste twee passen niet hetzelfde');
                }
                else if (answer.check == 'entries_too_old') {
                    result.html('Pas meer dan 10 minuten geleden gescand');
                }
                else if (answer.check == 'pass_okay') {
                    result.html('Pas is correct');
                    $('#submit').prop('disabled', false);
                }
            },
            error: function(error) {
                showError('Foutmelding bij communicatie met server', error.status + '-' + error.responseURL);
            }
        });
    }

    /**
     * Store the pass on a user
     * @param  {Event} event click event of the link
     * @return {undefined}
     */
    var addPass = function(event) {

        event.preventDefault();

        var uid = $('#user_id').val();

        // Send call
        $.ajax({
            url: '/users/'+uid+'/pass?access_token='+window.access_token,
            type: 'POST',
            dataType: 'json',
            success: function(user) {

                // Update current row, or add a new one
                var new_row = template_row(user);
                var existing_row = $('tr[data-uid="'+uid+'"]', '#passes tbody');

                if (existing_row.length > 0) {
                    existing_row.replaceWith(new_row);
                }
                else {
                    $('#passes tbody').append(new_row);
                }

                // Clear pass check form interface
                $('#pass_result').html('');
            },
            error: function(error) {
                //FIXME show error on double-adding passes
                showError('Foutmelding bij communicatie met server', error.status + '-' + error.responseURL);
            }
        });
    };

    // Start by authenticating to OAuth
    var oauth = new OAuth(config);
    oauth.authenticate(initApplication);

    // Compile template
    var template_row = Handlebars.compile($("#row").html());
});
