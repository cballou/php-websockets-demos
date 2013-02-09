window.Todo = window.Todo || {};

(function($, Todo) {

    var url = '127.0.0.1:8091',
        conn,
        user_id = null,
        currentTodo = null,
        isMobile = 'ontouchstart' in document.documentElement,
        isSubmitting = false,
        ws = null;

    /**
     * The initialization function which triggers the creation of your own
     * personal tracking dot as well as creates the WS connection and defines
     * the callbacks.
     */
    Todo.init = function() {

        // initialize the console wrapper
        Todo.Console.init();

        // check for websocket support
        if (!('WebSocket' in window)) {
            alert('Your browser does not support native WebSockets.');
            return;
        }

        console.log('Attempting to establish a WS connection.');

        // initialize autobahn
        conn = new ab.Session(
            // the websocket host
            'ws://' + url,
            // callback on connection established
            Todo.Ws.onConnect,
            // callback on connection close
            Todo.Ws.onClose,
            // additional AB parameters
            { 'skipSubprotocolCheck' : true }
        );

        // initialize todo app
        Todo.App.init();

    };

    /**
     * Todo websocket handler.
     */
    Todo.Ws = {

        /**
         * On successful connection, we need to initialize any of the WAMP
         * pubsub subscribers.
         */
        onConnect: function() {
            user_id = conn._session_id;

            console.log('Connection established, unique user id ' + conn._session_id);
            console.log('Adding set of WAMP subscribers.');

            conn.subscribe('connected', Todo.App.userConnected);
            conn.subscribe('disconnected', Todo.App.userDisconnected);

            conn.subscribe('create', Todo.Items.create);
            conn.subscribe('update', Todo.Items.update);
            conn.subscribe('delete', Todo.Items.remove);
            conn.subscribe('sort', Todo.Items.sort);
            conn.subscribe('lock', Todo.Items.lock);
            conn.subscribe('unlock', Todo.Items.unlock);
            conn.subscribe('reposition', Todo.Items.reposition);
            conn.subscribe('finish-reposition', Todo.Items['finish-reposition']);
        },

        onClose: function() {
            console.log('Websocket connection is closed. Are you sure the server is up?');
        }

    };

    /**
     * The frontend UI handlers for actionable todo items. These are what trigger
     * DB changes in addition to sending out ZeroMQ messages to the other
     * listening socket connections to report back to all other users.
     */
    Todo.App = {

        /**
         * Handle any event bindings.
         */
        init: function() {
            var $wrapper = $('#wrapper');

            // initialize the sortable list, which also entails sort locking
            Todo.Sortable.init();

            // handle events on todo items specifically
            $wrapper.on('click', '.todo a', Todo.Item.setCurrent);
            $wrapper.on('click', '#btn-add', Todo.Item.dbCreate);
            $wrapper.on('click', '.todo a.saveChanges', Todo.Item.dbUpdate);
            $wrapper.on('click', '.todo a.delete', Todo.Item.dbDelete);
            $wrapper.on('click', '.todo a.edit', Todo.Item.obtainEditLock);
            $wrapper.on('click', '.todo a.discardChanges', Todo.Item.releaseEditLock);
        },

        /**
         * WAMP event notification when a new user connects.
         */
        userConnected: function(e, data) {
            console.log('WAMP Event: Todo.App.userConnected triggered.');
        },

        /**
         * WAMP event notification when a user disconnects.
         */
        userDisconnected: function(e, data) {
            console.log('WAMP Event: Todo.App.userDisconnected triggered.');

            // remove any locks if held
            for (i in Todo.Items.locked) {
                if (user_id == Todo.Items.locked[i]) {
                    $('#todo-' . i).removeClass('locked');
                    Todo.Items.locked[i] = false;
                }
            }

            // also remove the repositioning / sorting lock
            if (Todo.Items.repositioning == user_id) {
                $('#todoList').sortable( "option", "disabled", false);
                Todo.Items.repositioning = false;
            }
        }

    };

    Todo.Item = {

        /**
         * On click of a todo item, set it as the current.
         */
        setCurrent: function(e) {
            e.preventDefault();

            currentTodo = $(this).closest('.todo');
            currentTodo.data('id', currentTodo.attr('id').replace('todo-', ''));
            console.log('Setting current todo item to ' + currentTodo.data('id'));
        },

        /**
         * Handles creation of a todo item in the database.
         */
        dbCreate: function(e) {
            if (isSubmitting) {
                return false;
            }

            isSubmitting = true;

            // handle attempt to create a new todo
            $.get("ajax.php", {
                action: 'create',
                text: '[New item] Double click to edit.',
                rand: Math.random()
            }, function(response) {
                isSubmitting = false;
/*
                if (response.status && response.status == 'success') {
                    Todo.Item.create(response.data);
                }
*/
            }).error(function() {
                isSubmitting = false;
                console.log('An unknown error occurred attempting to create your item.');
            });
        },

        /**
         * The client side handler to updating a todo item.
         */
        dbUpdate: function(e) {
            if (isSubmitting) {
                return false;
            }

            isSubmitting = true;

            // trigger edit save
            $.get("ajax.php", {
                action: 'update',
                id: currentTodo.data('id'),
                text: currentTodo.find("input[type=text]").val()
            }, function(data) {
                isSubmitting = false;

                if (!data || !data.status) {
                    console.log('An unknown error occurred attempting to save your edit.');
                } else if (data.status == 'error') {
                    if (data.msg) {
                        console.log('Server response: ' + data.msg);
                    } else {
                        console.log('An unknown error occurred attempting to save your edit.');
                    }
                }
                /*
                else {
                    Todo.Item.update(data.data.id, data.data.text);
                }
                */
            }).error(function() {
                isSubmitting = false;
                console.log('An unknown error occurred attempting to save your edit.');
            });

            return false;
        },

        /**
         * Handles verifying deletion before making it official.
         */
        dbDelete: function(e) {
            if (!confirm('Are you sure you want to delete this todo item?')) {
                return false;
            }

            if (isSubmitting) {
                return false;
            }

            isSubmitting = true;

            // trigger deletion
            $.get('ajax.php', {
                action: 'delete',
                id: currentTodo.data('id')
            }, function(data) {
                isSubmitting = false;

                if (!data || !data.status) {
                    console.log('An unknown error occurred and the item could not be deleted.');
                } else if (data.status == 'error') {
                    if (data.msg) {
                        console.log('Server response: ' + data.msg);
                    } else {
                        console.log('An unknown error occurred and an exclusive edit lock could not be obtained.');
                    }
                } else {
                    // handle deletion
                    Todo.Item.remove(currentTodo.data('id'));
                }
            }).error(function() {
                isSubmitting = false;
                console.log('An unknown error occurred attempting to delete your item.');
            });

            return false;
        },

        /**
         * Add a new todo item.
         */
        create: function(item) {
            var pos = $('#todoList li').length - 1;
            var html = [
                '<li id="todo-' + parseInt(item.data.id) + '" class="ui-state-default todo">',
                '<div class="text">' + item.data.text + '</div>',
                '<div class="actions">',
                '<a href="#" class="edit">Edit</a>',
                '<a href="#" class="delete">Delete</a>',
                '</div>',
                '</li>'
            ].join("\n");

            if (pos < 0) {
                pos = 0;
            }

            // now add item to sortable with given position and refresh
            $('#todoList li').eq(pos).after(html);
            $('#todoList').sortable('refresh');

            // prevent default
            return false;
        },

        /**
         * Update todo text.
         */
        update: function(data) {
            console.log(data);

            // successful edit
            $('#todo-' + data.data.id)
                .removeData('origText')
                .find(".text")
                .text(data.data.text);


            // unlock the todo item if we hold the lock
            if (Todo.Items.locked[data.data.id] && Todo.Items.locked[data.data.id] == user_id) {
                console.log('Attempting to unlock todo item.');
                conn.publish('unlock', { id: data.data.id, user_id: user_id });
            }
        },

        /**
         * Remove an existing todo item from the DOM.
         */
        remove: function(id) {
            $('#todo-' + id).remove();
        },

        /**
         * Handles checking for and obtaining a lock before editing.
         */
        obtainEditLock: function(e) {
            var id = currentTodo.data('id'),
                $todo = currentTodo.find('.text');

            // if already open, block
            if (currentTodo.data('origText')) {
                return false;
            }

            // if already locked, block
            if (Todo.Items.locked[id]) {
                console.log('Todo item is already locked by another user.');
                return false;
            }

            // acquire an exclusive lock before edit
            console.log('Attempting to obtain an exclusive edit lock.');
            conn.publish('lock', {
                id: currentTodo.data('id'),
                user_id: user_id
            });

            return false;
        },

        /**
         * Cancels the current edit.
         */
        releaseEditLock: function(e) {
            currentTodo
                .find('.text')
                .text(currentTodo.data('origText'))
                .end()
                .removeData('origText');

            currentTodo.removeClass('active');

            // unlock the todo item
            conn.publish('unlock', {
                id: currentTodo.data('id'),
                user_id: user_id
            }, true);

            return false;
        }

    };

    /**
     * UI handlers for management of todo items. These are all triggered via the
     * Autobahn PubSub notifiers/publishers.
     */
    Todo.Items = {

        // holds locked items
        locked: {},
        repositioning: false,

        /**
         * Create a new todo item.
         */
        create: function(event, data) {
            console.log('WAMP Event: Todo.Items.create triggered.');

            try {
                data = JSON.parse(data);
            } catch (e) {
                console.log(e);
                return;
            }

            Todo.Item.create(data);

            // prevent default
            return false;
        },

        /**
         * Update an existing todo item.
         */
        update: function(event, data) {
            console.log('WAMP Event: Todo.Items.update triggered.');

            try {
                data = JSON.parse(data);
            } catch (e) {
                console.log(e);
                return;
            }

            Todo.Item.update(data);

            // prevent default
            return false;
        },

        /**
         * Remove an existing todo item.
         */
        remove: function(event, data) {
            console.log('WAMP Event: Todo.Items.remove triggered.');

            try {

                data = JSON.parse(data);
                if (data && data.data.id) {
                    Todo.Item.remove(data.data.id);
                }

            } catch (e) {
                console.log(e);
                return;
            }

            // prevent default
            return false;
        },

        /**
         * Re-arrange todo item order.
         */
        sort: function(event, data) {
            var $todoList = $('#todoList'),
                $elem,
                i;

            console.log('WAMP Event: Todo.Items.sort triggered.');

            try {
                data = JSON.parse(data);
            } catch (e) {
                console.log(e);
                return;
            }

            // if we triggered sort, don't bother
            if (data.user_id == user_id) {
                return;
            }

            console.log(data);

            // iterate over items to update the orders
            for (i in data.data) {
                $elem = $('#todo-' + data.data[i]);
                console.log($elem);
                $elem.appendTo($todoList);
            }

            // trigger sort update on todolist
            $todoList.sortable('refresh');
        },

        /**
         * When a lock gets obtained by a user.
         */
        lock: function(event, data) {
            var $todo,
                $text;

            console.log('WAMP Event: Todo.Items.lock triggered.');

            try {
                data = JSON.parse(data);
            } catch (e) {
                console.log(e);
                return;
            }

            // if failed, do nothing
            if (!data || data.error) {
                if (data.msg) {
                    console.log(data.msg);
                } else {
                    console.log('An unknown error occurred and an exclusive edit lock could not be obtained.');
                }
                return;
            }

            // grab todo item
            $todo = $('#todo-' + data.id);

            // set locked flag
            Todo.Items.locked[data.id] = data.user_id;

            // if we locked it, don't add locked class to prevent editing
            if (data.user_id != user_id) {
                console.log('An exclusive lock was obtained for todo item ' + data.id);
                $todo.addClass('locked');
            } else {
                // lock obtained successfully, continue with edit
                console.log('We obtained an exclusive lock obtained for the todo item ' + data.id);

                // highlight
                $todo.addClass('active');

                // save old text
                $text = $todo.find('.text');
                $todo.data('origText', $text.text());

                // add edit input
                $('<input type="text">')
                    .val($text.text())
                    .appendTo($text.empty())
                    .focus();

                // add save and cancel links
                $text.append([
                    '<div class="editTodo">',
                    '<a class="saveChanges" href="#">Save</a> or ',
                    '<a class="discardChanges" href="#">Cancel</a>',
                    '</div>'
                ].join("\n"));
            }
        },

        /**
         * When a lock gets released by a user.
         */
        unlock: function(event, data) {
            console.log('WAMP Event: Todo.Items.unlock triggered.');

            try {
                data = JSON.parse(data);
            } catch (e) {
                console.log(e);
                return;
            }

            // if failed, do nothing
            if (!data || data.error) {
                if (data.msg) {
                    console.log(data.msg);
                } else {
                    console.log('An unknown error occurred and an exclusive edit lock could not be released.');
                }
                return;
            }

            // unlock the item
            Todo.Items.locked[data.id] = null;

            // if we don't hold lock, remove the locked class
            if (data.user_id != user_id) {
                console.log('An exclusive lock was released on item ' + data.id);
                $('#todo-' + data.id).removeClass('locked');
            } else {
                console.log('Our exclusive lock was released on item ' + data.id);
                $('#todo-' + data.id).removeClass('locked').removeClass('active');
            }
        },

        /**
         * When a user obtains a reposition (sort) lock.
         */
        reposition: function(event, data) {
            console.log('WAMP Event: Todo.Items.reposition triggered.');

            try {
                data = JSON.parse(data);
            } catch (e) {
                console.log(e);
                return;
            }

            // if failed, do nothing
            if (!data || data.error) {
                if (data.msg) {
                    console.log(data.msg);
                } else {
                    console.log('An unknown error occurred and an exclusive sort lock could not be obtained.');
                }
                return;
            }

            Todo.Sortable.locked = data.user_id;

            // disable sortable if we don't hold lock
            if (Todo.Sortable.locked != user_id) {
                $('#todoList').sortable("option", "disabled", true);
                $('#todoList').addClass('locked');
            }
        },

        /**
         * When a user releases the reposition (sort) lock.
         */
        'finish-reposition': function(event, data) {
            console.log('WAMP Event: Todo.Items.finish-reposition triggered.');

            try {
                data = JSON.parse(data);
            } catch (e) {
                console.log(e);
                return;
            }

            // if failed, do nothing
            if (!data || data.error) {
                if (data.msg) {
                    console.log(data.msg);
                } else {
                    console.log('An unknown error occurred and an exclusive sort lock could not be released.');
                }
                return;
            }

            // enable sortable if we don't hold lock
            if (Todo.Sortable.locked != user_id) {
                $('#todoList').sortable( "option", "disabled", false);
                $('#todoList').removeClass('locked');
            }

            // set flag as unlocked
            Todo.Sortable.locked = false;
        }

    };

    /**
     * Container for sortable functionality.
     */
    Todo.Sortable = {

        locked: false,

        /**
         * Handles initializing sorting on the todo list.
         */
        init: function() {
            $('#todoList').sortable({
                axis: 'y',
                containment: '#wrapper',
                start: Todo.Sortable.obtainLock,
                update: Todo.Sortable.sort,
                stop: Todo.Sortable.releaseLock
            });
        },

        /**
         * Obtain an exclusive sortable lock.
         */
        obtainLock: function(e, ui) {
            console.log('Todo.Sortable.obtainLock: Attempting to obtain lock.');

            // if we already hold the lock, skip
            if (Todo.Sortable.locked && Todo.Sortable.locked == user_id) {
                console.log('We already hold the sortable lock.');
                return false;
            }

            // attempt to obtain the reposition lock
            conn.publish('reposition', { user_id: user_id });
        },

        /**
         * Release the exclusive sortable lock.
         */
        releaseLock: function(e, ui) {
            console.log('Todo.Sortable.releaseLock: Attempting to release lock.');

            ui.item.css({ top: 0, left: 0 });

            // if we already hold the lock, skip
            if (!Todo.Sortable.locked || Todo.Sortable.locked != user_id) {
                console.log('We arent holding a lock to release.');
                return false;
            }

            // attempt to release the reposition lock
            conn.publish('finish-reposition', { user_id: user_id });
        },

        /**
         * Handle sending the new sort order to the server.
         */
        sort: function(e, ui) {
            var arr = $("#todoList").sortable('toArray');

            arr = $.map(arr,function(val,key) {
                return val.replace('todo-','');
            });

            console.log('Todo.Sortable.sort: Attempting to update sort order.');

            $.ajax({
                url: 'ajax.php',
                data: { action: 'sort', positions: arr },
                async: false,
                done: function(data, textStatus, jqXHR) {
                    if (!data.status || data.status != 'success') {
                        if (data.msg) {
                            console.log('Server response: ' + data.msg);
                        } else {
                            console.log('An unkown error occurred attempting to update sort server side.');
                        }
                    } else {
                        console.log('Sort was successfully updated server side.');
                        console.log('Attempting to push updated sort to other clients.');
                        conn.publish('sort', { user_id: user_id, positions: arr });
                    }
                },
                fail: function(jqXHR, textStatus, errorThrown) {
                    console.log('An unkown error occurred attempting to update sort server side.');
                }
            });
        }
    };

    /**
     * Console log wrapper for displaying items on screen.
     */
    Todo.Console = {

        elems: {
            console: null,
            toggle: null,
            list: null,
        },

        init: function() {
            var that = this;

            this.elems.console = $('#console');
            this.elems.toggle = $('#toggleConsole');
            this.elems.list = $('#console ul');

            // bind console override
            this.override();

            // watch for show/hide of console
            $('body').on('click', '#toggleConsole', function() {
                var css = {},
                    plusMinus = '+',
                    removeClass = false;

                if (that.elems.console.hasClass('active')) {
                    css.bottom = 0;
                    removeClass = true;
                } else {
                    plusMinus = '-';
                    css.bottom = 120;
                }

                that.elems.console.stop(true, true).animate(css, 250, function() {
                    that.elems.toggle.find('span').text(plusMinus);

                    if (removeClass) {
                        that.elems.console.removeClass('active');
                    } else {
                        that.elems.console.addClass('active');
                    }
                });
            });
        },

        /**
         * Override console.log with our own special sauce.
         */
        override: function() {
            var that = this,
                exists = typeof console != 'undefined',
                _console = exists ? console.log : null;

            // new console log function
            console.log = function() {
                var msg;

                if (
                    (Array.prototype.slice.call(arguments)).length == 1
                    && typeof Array.prototype.slice.call(arguments)[0] == 'string'
                ) {
                    msg = (Array.prototype.slice.call(arguments)).toString();
                } else {
                    try {
                        msg = JSON.stringify(Array.prototype.slice.call(arguments));
                    } catch (e) {
                        msg = Array.prototype.slice.call(arguments);
                    }
                }

                that.elems.list.append([
                    '<li>',
                    msg,
                    '</li>'
                ].join("\n"));

                // force scroll to bottom
                that.elems.list.scrollTop(that.elems.list.height());

                // flash to indicate new message
                that.elems.toggle
                    .css({ backgroundColor: '#666' })
                    .delay(1000)
                    .css({ backgroundColor: '#222' });

                if (_console) {
                    _console.apply(console, arguments);
                }
            }
        }

    };

    // initialize
    Todo.init();

})(jQuery, window.Todo);
