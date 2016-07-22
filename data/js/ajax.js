$(document).ajaxComplete(function (event, jqXHR, ajaxOptions) {
    if (jqXHR.status >= 300 && jqXHR.status <= 303) {
        window.location = jqXHR.getResponseHeader("Location");
        return false;
    } else {
        var requestedURL = jqXHR.getResponseHeader("X-requested-url");
        if (ajaxOptions.url !== requestedURL) {
            if(requestedURL.length >0){//prevent "you host name"/null redirection
                window.location = requestedURL;
            }

            return false;
        }
    }
});

$(document).ajaxError(function (event, jqXHR, ajaxOptions, thrownError) {
    var message = thrownError;

    if (jqXHR.status === 0) {
        message = 'Not connect.\n Verify Network.';
    } else if (jqXHR.status == 404) {
        message = 'Requested page not found. [404]';
    } else if (jqXHR.status == 500) {
        message = 'Internal Server Error [500].';
    } else if (thrownError === 'parsererror') {
        message = 'Requested JSON parse failed.';
    } else if (thrownError === 'timeout') {
        message = 'Time out error.';
    } else if (thrownError === 'abort') {
        message = 'Ajax request aborted.';
    }


    if (jqXHR.responseJSON != null) {
        message = jqXHR.responseJSON.error || jqXHR.responseJSON.message;
    } else if (jqXHR.responseText != null) {
        message = jqXHR.responseText;
    }
    $.showmessage({
        type: 'error',
        isSticky: false,
        message: message
    });
});
