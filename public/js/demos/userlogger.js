window.UserLogger = window.UserLogger || {};

(function($, UserLogger) {

    var user_id = null,
        connected = new Date(),
        user_details = {},
        isMobile = 'ontouchstart' in document.documentElement,
        ws = null;

    /**
     * Initialize the user logger.
     */
    UserLogger.init = function() {

        // check for websocket support
        if (!('WebSocket' in window)) {
            alert('Your browser does not support native WebSockets.');
            return;
        }

        // get own details
        UserLogger.getOwnDetails();


        // init websocket in the private global scope
        ws = new WebSocket('ws://127.0.0.1:8092');

        /**
         * Once a websocket connection has been established, we begin tracking
         * mouse movements and sending them to the server.
         */
        ws.onopen = function() {
            console.log('WebSocket opened:');
            console.log(ws);

            try {
                ws.send(JSON.stringify(user_details));
            } catch (e) {
                console.log('An error occurred.');
                console.log(e);
            }
        };

        /**
         * The close event. We don't do anything here.
         */
        ws.onclose = function(e) {
			console.log('Close event triggered.');
			console.log(e);
        };

        /**
         * The messages coming from the server formatted in JSON.
         */
        ws.onmessage = function(e) {
			// convert data to JSON
			try {
				var data = JSON.parse(e.data);

                // display the details
                UserLogger.displayDetails(data);
			} catch (e) {
				console.log('An error occurred attempting to parse JSON response.');
				console.log(e);
			}
        };

        /**
         * Handle errors, i.e. do nothing.
         */
        ws.onerror = function(e) {
			console.log('An error occurred.');
			console.log(e);
        };

    };

    /**
     * Retrieves the current clients details.
     */
    UserLogger.getOwnDetails = function() {
        var name = prompt('What\'s your name?');

        try {

            user_details = {
                'name': name,
                'connected': connected.toString(),
                'codeName': navigator.appCodeName,
                'browserName': navigator.appName,
                'browserVersion': navigator.appVersion,
                'browserVersionMajor': parseInt(navigator.appVersion, 10),
                'screenWidth': document.documentElement.clientWidth,
                'screenHeight': document.documentElement.clientHeight,
                'colorDepth' : screen.colorDepth || screen.pixelDepth,
                'os': navigator.platform,
                'userAgent': navigator.userAgent,
                'systemLanguage': navigator.systemLanguage || navigator.userLanguage || navigator.language || 'n/a'
            };

            // display users own details
            UserLogger.displayDetails(user_details);

        } catch (e) {
			console.log('An exception occurred.');
			console.log(e);
        }
    };

    /**
     * A very simple method of showing user information.
     */
    UserLogger.displayDetails = function(details) {
        // if no name, give them one
        if (!details.name || !details.name.length) {
            details.name = UserLogger.giveName();
        }

        $('#users').prepend([
            '<div class="user">',
            '<h4>' + details.name + '</h4>',
            '<small>Connected ' + UserLogger.prettyDate(details.connected) + '</small>',
            '<ul>',
            '<li><strong>OS:</strong> ' + details.os + '</li>',
            '<li><strong>Browser:</strong> ' + details.codeName + '</li>',
            '<li><strong>Screen Size:</strong> ' + details.screenWidth + 'x' + details.screenHeight + '</li>',
            '<li><strong>Color Depth:</strong> ' + details.colorDepth + ' bits</li>',
            '<li><strong>System Language:</strong> ' + details.systemLanguage + '</li>',
            '<li><strong>User Agent:</strong> ' + details.userAgent + '</li>',
            '</ul>',
            '</div>'
        ].join("\n"));
    };

    /**
     * Randomly assigned usernames.
     */
    UserLogger.giveName = function() {
        var names = ['Leroy Jenkins', 'Dudley', 'Velociraptor', 'T-Rex',
                     'T-Bone', 'J-Money', 'A-Money', 'K-Money', 'T-Money',
                     'C-Dot', 'The Iceman', 'A-Train', 'Agent Zero', 'Agent Smith',
                     'Big Baby', 'Big Red', 'Birdman', 'Blaze', 'Booger', 'Cornbread',
                     'Diesel', 'Hondo', 'K-Love', 'Neo', 'Murph', 'Tiny', 'Big C',
                     'Big J', 'Big K', 'Superman', 'Spiderman', 'Jolly Green Giant',
                     'Fruitloops', 'Tony The Tiger', 'Ghostbuster', 'The Villain',
                     'nullboy', 'infinite looper'];

        return names[Math.floor(Math.random() * names.length)];
    };

    /**
     * Copyright (c) 2008 John Resig (jquery.com)
     * Licensed under the MIT license.
     */
    UserLogger.prettyDate = function(date) {
        var date = new Date(date),
            diff = ((date.getTime() - (new Date(connected)).getTime()) / 1000),
            day_diff = Math.floor(diff / 86400);

        if (diff < 0) {
            return "just now";
        }

        return day_diff == 0 && (
            diff < 1 && "just now" ||
            diff < 120 && diff + " seconds after you" ||
            diff < 3600 && Math.floor(diff / 60) + " minutes after you" ||
            diff < 7200 && "1 hour ago" ||
            diff < 86400 && Math.floor(diff / 3600) + " hours after you") ||
            day_diff == 1 && "Yesterday" ||
            day_diff < 7 && day_diff + " days after you" ||
            day_diff < 31 && Math.ceil(day_diff / 7) + " weeks after you";
    };

    // initialize
    UserLogger.init();

})(jQuery, window.UserLogger);
