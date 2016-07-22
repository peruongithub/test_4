(function ($) {
    $.showmessage = function (options) {
        var defaultOp = {};
        defaultOp.target = 'body';
        defaultOp.bjQueryUi = true;
        defaultOp.fadeInSpeed = 500;
        defaultOp.fadeOutSpeed = 500;
        defaultOp.removeTimer = 4000;
        defaultOp.isSticky = false;
        defaultOp.usingTransparentPNG = false;
        defaultOp.messageboxClass = 'messagebox';

        options = options || {};
        options.type = options.type || 'info';
        options.message = options.message || 'Some message';
        options.target = options.target || 'body';
        options.bjQueryUi = options.bjQueryUi || true;
        options.fadeInSpeed = options.fadeInSpeed || 500;
        options.fadeOutSpeed = options.fadeOutSpeed || 500;
        options.removeTimer = options.removeTimer || 4000;
        options.isSticky = options.isSticky || false;
        options.usingTransparentPNG = options.usingTransparentPNG || false;
        options.messageboxClass = options.messageboxClass || 'messagebox';
        if (options.type == 'info' || options.type == 'success' || options.type == 'warning' || options.type == 'error' || options.type == 'validation') {
            var messagebox_close = '';
            var typeClass = ' ' + options.type + ' ';
            var isStickyClass = '';
            var bjQueryUiClass = '';
            if (options.isSticky) {
                isStickyClass = ' sticky_messagebox ';
                messagebox_close = '<a href="#message_close" class="message_close">Close</a>';
            }
            if (options.bjQueryUi) {
                bjQueryUiClass = ' ui-corner-all ';
                if (options.isSticky) {
                    messagebox_close = '<span class="message_close ui-icon ui-icon-closethick "></span>';
                }

                var messagebox = '<div class="messagebox-container ' + isStickyClass + ' ui-state-default ui-corner-all">' + messagebox_close + '<div class="' + options.messageboxClass + typeClass + bjQueryUiClass + '">' + options.message + '</div></div>';
            } else {
                var messagebox = '<div class="' + options.messageboxClass + typeClass + isStickyClass + '">' + messagebox_close + options.message + '</div>';
            }

            new $.purr($(messagebox), options, defaultOp);
        }

    };
    $.purr = function (messagebox, options, defaultOp) {
        // Convert messagebox to a jQuery object
        messagebox = $(messagebox);
        // Get the container element from the page
        var cont = $('#messageboxes-container');

        // If the container doesn't yet exist, we need to create it
        if (cont.length == 0 || options.target !== defaultOp.target) {
            var targetID = 'messageboxes-container';
            if (options.target !== defaultOp.target) {
                targetID = 'free_messageboxes-container';
            }

            $(options.target).prepend('<div id="' + targetID + '"></div>');
            var cont = $('#' + targetID + '');
        }

        // Convert cont to a jQuery object
        cont = $(cont);
        notify();

        function notify() {
            // Set up the close button
            if (options.bjQueryUi) {
                $(messagebox).hover(
                    function (event) {
                        $(this).addClass('ui-state-hover');
                    },
                    function (event) {
                        $(this).removeClass('ui-state-hover');
                    }
                );
            }
            $('span.message_close', $(messagebox))
                .click(function () {
                        removeNotice();

                        return false;
                    }
                );

            // Add the messagebox to the page and keep it hidden initially
            messagebox.appendTo(cont)
                .hide();

            //if ( $.browser.msie && options.usingTransparentPNG )
            //{
            // IE7 and earlier can't handle the combination of opacity and transparent pngs, so if we're using transparent pngs in our
            // messagebox style, we'll just skip the fading in.
            //messagebox.show();
            //}
            //else
            //{
            //Fade in the messagebox we just added
            messagebox.fadeIn(options.fadeInSpeed);
            //}

            // Set up the removal interval for the added messagebox if that messagebox is not a sticky
            if (!options.isSticky) {
                var topSpotInt = setInterval(function () {
                    // Stop checking once the condition is met
                    clearInterval(topSpotInt);

                    // Call the close action after the timeout set in options
                    setTimeout(function () {
                            removeNotice();
                        }, options.removeTimer
                    );
                }, 200);
            }
        }

        function removeNotice() {
            // IE7 and earlier can't handle the combination of opacity and transparent pngs, so if we're using transparent pngs in our
            // messagebox style, we'll just skip the fading out.
            /*if ( jQuery.browser.msie && options.usingTransparentPNG )
             {
             messagebox.css( { opacity: 0	} )
             .animate( 
             { 
             height: '0px' 
             }, 
             { 
             duration: options.fadeOutSpeed, 
             complete:  function ()
             {
             var parent = $(messagebox).parent();
             messagebox.remove();
             if($(parent).html().length == 0){
             $(parent).remove();
             }
             } 
             } 
             );
             }
             else
             {*/
            // Fade the object out before reducing its height to produce the sliding effect
            messagebox.animate(
                {
                    opacity: '0'
                },
                {
                    duration: options.fadeOutSpeed,
                    complete: function () {
                        messagebox.animate(
                            {
                                height: '0px'
                            },
                            {
                                duration: options.fadeOutSpeed,
                                complete: function () {
                                    var parent = $(messagebox).parent();
                                    messagebox.remove();
                                    if ($(parent).html().length == 0) {
                                        $(parent).remove();
                                    }
                                }
                            }
                        );
                    }
                }
            );
            //}
        }
    };
})(jQuery);

