$(document).ready(function(){

    /**
     * Initialise application when OAuth authentication is done
     * @param  {String} access_token valid OAuth2 access token
     * @return {undefined}
     */
    var initApplication = function(access_token) {

        // Check for authorisation
        if (! access_token) {
            showNotification('error', 'Geen toegang', 'Je bent uitgelogd of je bent geen bestuur. Herlaad de pagina om opnieuw te proberen.');
            return;
        }
        else {
            hideNotifications();
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
                    $('#passes tbody').append(templates.user(this));
                });
                $('#spinner').remove();
            },
            error: showError
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
            error: showError
        });

        // Bind event handlers
        $('#passes').on('click', '.status.access', changeAccess);
        $('#passes').on('click', '.status.pass', changePass);
        $('#valid_pass').on('click', checkPass);
        $('#new_pass').on('submit', addPass);
        $('body').on('click', '.seen', hideNotifications);
    }

    /**
     * Show an API error message
     * @param  {Error} XMLHttpRequest error object
     * @return {undefined}
     */
    var showError = function(xhr) {
        var error = JSON.parse(xhr.response);
        var message = error.details + '<br><br> Meer informatie: <a href="'+error.href+'">'+error.href+'</a>';
        showNotification('error', error.title, message);
    };

    /**
     * Show a notification to the user
     * @param  {String} type    either "error", "warning", or "success"
     * @param  {String} title   title of the message to show
     * @param  {String} message body text with explanation
     * @return {undefined}
     */
    var showNotification = function(type, title, message)
    {
        $('body').append(templates.notification({
            type: type,
            title: title,
            message: message
        }));
    };

    /**
     * Hide the error messages and notifications
     * @param  {Event}     optional event handler to cancel (usually a link)
     * @return {undefined}
     */
    var hideNotifications = function(event) {
        if (event !== undefined) {
            event.preventDefault();
        }
        $('.notification').remove();
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
                error: showError
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
                error: showError
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

                // Hide previous answer colouring
                result.removeClass('wrong okay');

                // Update response text
                if (answer.check == 'door_response_not_okay') {
                    result.html('Kan de deur niet bereiken').addClass('wrong');

                }
                else if (answer.check == 'pass_mismatch') {
                    result.html('Laatste twee passen niet hetzelfde').addClass('wrong');
                }
                else if (answer.check == 'entries_too_old') {
                    result.html('Pas meer dan 10 minuten geleden gescand').addClass('wrong');
                }
                else if (answer.check == 'pass_okay') {
                    result.html('Pas is correct').addClass('okay');
                    $('#submit').prop('disabled', false);
                }
            },
            error: showError
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
                var new_row = templates.user(user);
                var existing_row = $('tr[data-uid="'+uid+'"]', '#passes tbody');

                if (existing_row.length > 0) {
                    existing_row.replaceWith(new_row);
                }
                else {
                    $('#passes tbody').append(new_row);
                }

                // Clear pass check form interface
                $('#pass_result').html('');

                // Show notification
                showNotification('success', 'Pas toevoegd', 'Nieuwe pas toevoegd.');
            },
            error: showError
        });
    };

    // Compile templates
    var templates = {
        user:         Handlebars.compile($("#user").html()),
        notification: Handlebars.compile($("#notification").html())
    };

    // Start by authenticating to OAuth
    var oauth = new OAuth(config);
    oauth.authenticate(initApplication);
});
