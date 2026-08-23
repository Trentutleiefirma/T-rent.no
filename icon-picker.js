/* Icon Picker */

(function($) {

    $.fn.iconPicker = function(options) {
        var options = ['fa', 'fa']; // default font set
        var icons;
        $list = $('');

        function font_set() {

            if (options[0] == 'fa') {
                icons = ["blank",
                // Mail
                "inbox", "envelope", "envelope-o", "paperclip", "reply-all", "mail-reply-all", "mail-forward", "mail-reply", "reply",
                // Media
                "music", "film", "step-backward", "fast-backward", "backward", "play", "play-circle", "play-circle-o", "pause", "stop", "forward", "fast-forward", "step-forward", "eject", "repeat", "refresh", "random", "headphones", "volume-off", "volume-down", "volume-up",
                // Arrows
                "angle-double-left", "angle-double-right", "angle-double-up", "angle-double-down", "angle-left", "angle-right", "angle-up", "angle-down", "arrows", "arrow-left", "arrow-right", "arrow-up", "arrow-down", "arrows-alt", "arrows-v", "arrows-h", "arrow-circle-left", "arrow-circle-right", "arrow-circle-up", "arrow-circle-down", "arrow-circle-o-down", "arrow-circle-o-up", "arrow-circle-o-right", "arrow-circle-o-left", "caret-down", "caret-up", "caret-left", "caret-right", "chevron-left", "chevron-right", "chevron-up", "chevron-down", "chevron-circle-left", "chevron-circle-right", "chevron-circle-up", "chevron-circle-down", "expand", "compress", "hand-o-right", "hand-o-left", "hand-o-up", "hand-o-down", "level-up", "level-down", "long-arrow-down", "long-arrow-up", "long-arrow-left", "long-arrow-right", "rotate-right", "toggle-left", "toggle-down", "toggle-up", "toggle-right",
                // Search
                "search", "search-plus", "search-minus",
                // File Editing
                "cut", "crop", "copy", "paste", "font", "bold", "italic", "anchor", "link", "unlink", "chain-broken", "external-link", "external-link-square", "text-height", "text-width", "align-left", "align-center", "align-right", "align-justify", "list", "quote-left", "quote-right", "outdent", "indent", "undo", "adjust", "tint", "edit", "list-ul", "list-ol", "list-alt", "th-large", "th", "th-list", "strikethrough", "underline", "magic", "superscript", "subscript", "eraser", "pagelines",
                // Punctuation
                "asterisk", "question", "info", "exclamation",
                // Emoticons
                "smile-o", "frown-o", "meh-o",
                // Math + Geometry
                "check", "times", "plus", "minus", "crosshairs", "spinner", "circle", "circle-o", "dot-circle-o", "minus-circle", "times-circle", "check-circle", "exclamation-circle", "question-circle", "info-circle", "plus-circle", "plus-square", "plus-square-o", "square", "square-o", "h-square", "share-square", "share-square-o", "check-square-o", "times-circle-o", "check-circle-o", "ellipsis-h", "ellipsis-v", "minus-square", "check-square", "bullseye",
                // Rate
                "thumbs-o-up", "thumbs-o-down", "star", "star-o", "star-half", "star-half-o", "heart", "heart-o", "lemon-o", "trophy", "thumbs-up", "thumbs-down",
                // Accounts
                "user", "user-md", "group", "sign-in", "sign-out", "key", "lock", "unlock", "unlock-alt", "gear", "gears", "ban", "female", "male", "comment", "comments", "ticket", "tasks", "calendar", "calendar-o",
                // Time
                "sun-o", "moon-o", "clock-o",
                // Site
                "home", "comment-o", "comments-o", "sitemap",
                // File Operations
                "upload", "download", "exchange", "file-o", "files-o", "file", "file-text", "file-text-o", "folder", "folder-o", "folder-open", "hdd-o", "cloud", "cloud-download", "cloud-upload", "save", "trash-o", "print",
                // Social Networks
                "adn", "dribbble", "dropbox", "facebook", "facebook-square", "flickr", "foursquare", "github", "github-square", "github-alt", "gittip", "google-plus", "google-plus-square", "instagram", "linkedin", "linkedin-square", "pinterest", "pinterest-square", "renren", "rss", "rss-square", "skype", "stack-exchange", "stack-overflow", "trello", "tumblr", "tumblr-square", "twitter", "twitter-square", "retweet", "vimeo-square", "vk", "weibo", "xing", "xing-square", "youtube", "youtube-square", "youtube-play",
                // Computer
                "desktop", "laptop", "tablet", "mobile-phone", "phone", "phone-square", "microphone", "microphone-slash", "apple", "windows", "android", "linux", "html5", "css3", "gamepad", "keyboard-o", "signal", "power-off", "terminal", "code", "code-fork", "bug",
                // Maps
                "glass", "globe", "map-marker", "thumb-tack", "building-o", "hospital-o", "location-arrow", "compass", "road",
                // Tools & Objects
                "bell", "book", "bookmark", "bookmark-o", "bullhorn", "camera", "camera-retro", "video-camera", "picture-o", "pencil", "pencil-square", "flask", "briefcase", "table", "truck", "wrench", "plane", "lightbulb-o", "stethoscope", "suitcase", "bell-o", "coffee", "cutlery", "umbrella", "ambulance", "medkit", "fighter-jet", "beer", "wheelchair", "gift", "leaf", "fire", "eye", "eye-slash", "warning", "magnet", "flag", "flag-o", "flag-checkered", "fire-extinguisher", "rocket", "shield", "puzzle-piece", "legal", "dashboard", "flash", "bars", "bar-chart-o",
                // Sorting
                "columns", "filter", "sort", "sort-down", "sort-up", "sort-alpha-asc", "sort-alpha-desc", "sort-amount-asc", "sort-amount-desc", "sort-numeric-asc", "sort-numeric-desc",
                // e-Commerce
                "money", "certificate", "credit-card", "shopping-cart", "euro", "gbp", "dollar", "rupee", "yen", "ruble", "won", "bitcoin", "bitbucket-square", "turkish-lira", "tag", "tags", "qrcode", "barcode"];
                options[1] = "fa";
            } else {
                icons = ["blank",

                "asterisk", "plus", "euro", "eur", "minus", "cloud", "envelope", "pencil", "glass", "music",

                "search", "heart", "euro", "star", "star-empty", "user", "film", "th-large", "th", "th-list",

                "ok", "remove", "zoom-in", "zoom-out", "off", "signal", "cog", "trash", "home", "file",

                "time", "road", "download-alt", "download", "upload", "inbox", "play-circle", "repeat", "refresh", "list-alt",

                "lock", "flag", "headphones", "volume-off", "volume-down", "volume-up", "qrcode", "barcode", "tag", "tags",

                "book", "bookmark", "print", "camera", "font", "bold", "italic", "text-height", "text-width", "align-left",

                "align-center", "align-right", "align-justify", "list", "indent-left", "indent-right", "facetime-video", "picture", "map-marker", "adjust",

                "tint", "edit", "share", "check", "move", "step-backward", "backward", "backward", "play", "pause",

                "stop", "forward", "forward", "step-forward", "eject", "chevron-left", "chevron-right", "plus-sign", "minus-sign", "remove-sign",

                "ok-sign", "question-sign", "info-sign", "screenshot", "remove-circle", "ok-circle", "ban-circle", "arrow-left", "arrow-right", "arrow-up",

                "arrow-down", "share-alt", "resize-full", "resize-small", "exclamation-sign", "gift", "leaf", "fire", "eye-open", "eye-close",

                "warning-sign", "plane", "calendar", "random", "comment", "magnet", "chevron-up", "chevron-down", "retweet", "shopping-cart",

                "folder-close", "folder-open", "resize-vertical", "resize-horizontal", "hdd", "bullhorn", "bell", "certificate", "thumbs-up", "thumbs-down",

                "hand-right", "hand-left", "hand-up", "hand-down", "circle-arrow-right", "circle-arrow-left", "circle-arrow-up", "circle-arrow-down", "globe", "wrench",

                "tasks", "filter", "briefcase", "fullscreen", "dashboard", "paperclip", "heart-empty", "link", "phone", "pushpin",

                "usd", "gbp", "sort", "sort-by-alphabet", "sort-by-alphabet-alt", "sort-by-order", "sort-by-order-alt", "sort-by-attributes", "sort-by-attributes-alt", "unchecked",

                "expand", "collapse-down", "collapse-up", "log-in", "flash", "log-out", "new-window", "record", "save", "open",

                "saved", "import", "export", "send", "floppy-disk", "floppy-saved", "floppy-remove", "floppy-save", "floppy-open", "credit-card",

                "transfer", "cutlery", "header", "compressed", "earphone", "phone-alt", "tower", "stats", "sd-video", "hd-video",

                "subtitles", "sound-stereo", "sound-dolby", "sound-5-1", "sound-6-1", "sound-7-1", "copyright-mark", "registration-mark", "cloud-download", "cloud-upload",

                "tree-conifer", "tree-deciduous", "cd", "save-file", "open-file", "level-up", "copy", "paste", "alert", "equalizer",

                "king", "queen", "pawn", "bishop", "knight", "baby-formula", "tent", "blackboard", "bed", "apple",

                "erase", "hourglass", "lamp", "duplicate", "piggy-bank", "scissors", "bitcoin", "btc", "xbt", "yen",

                "jpy", "ruble", "rub", "scale", "ice-lolly", "ice-lolly-tasted", "education", "option-horizontal", "option-vertical", "menu-hamburger",

                "modal-window", "oil", "grain", "sunglasses", "text-size", "text-color", "text-background", "object-align-top", "object-align-bottom", "object-align-horizontal",

                "object-align-left", "object-align-vertical", "object-align-right", "triangle-right", "triangle-left", "triangle-bottom", "triangle-top", "console", "superscript", "subscript",

                "menu-left", "menu-right", "menu-down", "menu-up",

                ];
                options[1] = 'glyphicon';
            };
        };
        font_set();

        function build_list($popup, $button, clear) {
            $list = $popup.find('.icon-picker-list');
            if (clear == 1) {
                $list.empty(); // clear list //
            }
            for (var i in icons) {
                $list.append('<li data-icon="' + icons[i] + '"><a href="#" title="' + icons[i] + '"><span class="' + options[0] + ' ' + options[1] + '-' + icons[i] + '"></span></a></li>');
            };
            $('a', $list).click(function(e) {
                e.preventDefault();
                var title = $(this).attr("title");
                
                var iconName = options[0] + ' ' + options[1] + '-' + title;
                $('.up-icon-picker-text').val(iconName);
                $button.html('<i class="'+iconName+'"></i>');
                // $target.val(options[0] + "|" + options[1] + "-" + title);
                // $button.removeClass().addClass("button icon-picker " + options[0] + " " + options[1] + "-" + title);
                removePopup();
            });
        };

        function removePopup() {
            $(".icon-picker-container").remove();
        }


        $button = $('.up-icon-picker');
        $button.each(function() {
            $(this).on('click.iconPicker', function() {
                createPopup($(this));
            });
        });


        function createPopup($button) {
            $target = $($button.data('target'));
            $popup = $('<div class="icon-picker-container"> \
						<div class="icon-picker-control" /> \
						<ul class="icon-picker-list" /> \
					</div>')
                .css({
                'top': $button.offset().top,
                'left': $button.offset().left
            });
            build_list($popup, $button, 0);
            var $control = $popup.find('.icon-picker-control');
            $control.html('<p>Select Font: <select><option value="fa">Font Awesome</option><option value="glyphicon">Glyphicons</option></select></p>' + '<a data-direction="back" href="#"><span class="dashicons dashicons-arrow-left-alt2"></span></a> ' + '<input type="text" class="" placeholder="Search" />' + '<a data-direction="forward" href="#"><span class="dashicons dashicons-arrow-right-alt2"></span></a>' + '');

            $('select', $control).on('change', function(e) {
                e.preventDefault();
                if (this.value != options[0]) {
                    options[0] = this.value;
                    font_set();
                    build_list($popup, $button, 1);
                };
            });

            $('a', $control).click(function(e) {
                e.preventDefault();
                if ($(this).data('direction') === 'back') {
                    //move last 25 elements to front
                    $('li:gt(' + (icons.length - 26) + ')', $list).each(function() {
                        $(this).prependTo($list);
                    });
                } else {
                    //move first 25 elements to the end
                    $('li:lt(25)', $list).each(function() {
                        $(this).appendTo($list);
                    });
                }
            });

            $popup.appendTo('body').show();

            $('input', $control).on('keyup', function(e) {
                var search = $(this).val();
                if (search === '') {
                    //show all again
                    $('li:lt(25)', $list).show();
                } else {
                    $('li', $list).each(function() {
                        if ($(this).data('icon').toString().toLowerCase().indexOf(search.toLowerCase()) !== -1) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                }
            });



            $(document).mouseup(function(e) {
                if (!$popup.is(e.target) && $popup.has(e.target).length === 0) {
                    removePopup();
                }
            });
        }
    }


    // $(function() {
    //     $('.icon-picker').iconPicker();
    // });

}(jQuery));