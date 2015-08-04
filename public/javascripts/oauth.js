var OAuth = (function(options){

    // Supply default options
    options = $.extend({
        url: 'https://auth.debolk.nl/',
    }, options);

    var getAuthorisationCodeFromURL = function() {
        regex = RegExp(/code=(.+?)(\&|$)/).exec(location.search);

        if (regex === null) {
            return null;
        }
        else {
            return regex[1];
        }
    };

    var userhasRefused = function() {
        regex = RegExp(/error=access_denied/).exec(location.search);
        return (regex !== null);
    }

    var access_token = null;
    var authorization_token = null;

    return {
        authenticate: function(callback){
            var authorization_token = getAuthorisationCodeFromURL();
            if (authorization_token === null) {
                // Have we refused authentication before?
                if (userhasRefused()) {
                    callback(false);
                    return;
                }
                else {
                    // Not authenticated, must login
                    window.location = options.url
                                        + 'authenticate?response_type=code'
                                        + '&client_id=' + options.client
                                        + '&client_pass=' + options.secret
                                        + '&redirect_uri=' +options.callback
                                        + '&state=1';
                }
            }
            else {
                // Logged in, request access_token to access services
                $.ajax({
                    type: 'POST',
                    url: options.url+'token',
                    dataType: 'json',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        grant_type: 'authorization_code',
                        code: authorization_token,
                        redirect_uri: options.callback,
                        client_id: options.client,
                        client_secret: options.secret,
                    }),
                    success: function(result){
                        // Store the access token for later usage
                        this.access_token = result.access_token;
                        // Clear the browser URL for cleaner reloads
                        history.pushState(null, '', options.callback);

                        // Check for authorization
                        $.ajax({
                            type: 'GET',
                            url: options.url+options.resource+'?access_token='+this.access_token,
                            success: callback(this.access_token),
                        });
                    },
                    error: callback(false),
                });
            }
        },
    };
});
