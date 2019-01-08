$(function(){
    // ArrayType inputs handling
    $(".text-array-inputs").on("blur", "input", function(e){
    	let input = this;
    	let value = input.value.trim();

    	if (!value.length && !input.matches("*:last-child")) {
    		input.remove();
        }
    }).on("keyup", "input", function(e){
    	let input = this;
    	let value = input.value.trim();

    	if (value && input.matches("*:last-child")) {
    		$(input).after("<input type='text' placeholder='" + input.getAttribute('placeholder') + "'>");
        }
    }).on("change", "input", function(e){
        let values = [];
        let $wrapper = $(this).parent();

        $wrapper.children().each(function(){
            let value = this.value.trim();
            if (value.length) {
                values.push(value);
            }
        });

        $wrapper.prev('input[type="hidden"]').val(JSON.stringify(values));
    });

    // Top-bar search functionnalities
    let $searchInput = $("#app-header .search input");
    let $searchableElements = $("[data-search]");
    if ($searchableElements.length) {
        $searchInput.on("keyup", function(){
            let query = this.value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, "");

            if (query.length) {
                $searchableElements.addClass('hide').filter("[data-search*='" + query + "']").removeClass('hide');
            } else {
                $searchableElements.removeClass('hide');
            }
        }).on("change", function(){
            $(this).trigger('keyup');

            let newUrl = window.location.origin + window.location.pathname;
            let newSearchQuery = window.location.search.replace(/[?&]q=[^&]*&?/, '');

            newSearchQuery += (newSearchQuery ? '&' : '?') + 'q=' + encodeURIComponent(this.value);
            if (newSearchQuery.substr(0, 1) != '?') {
                newSearchQuery = '?' + newSearchQuery;
            }

            window.history.pushState(null,
                                    document.title,
                                    newUrl + newSearchQuery);
        });

        if ($searchInput.val()) {
            $searchInput.trigger('change');
        }

        $searchInput.next('.button').on('click', function(e){
            e.preventDefault();
            $searchInput.val('').trigger('change');
        });
    } else {
        $searchInput.parent('.search').remove();
    }

    // Back button functionnalities
    if (document.referrer && document.referrer.indexOf('https://voiceep.com') != -1) {
        let $backButton = $("<a href='" + document.referrer + "' class='back'>Back</a>");
    	$("#app-header").prepend($backButton);
        $backButton.click(function(e){
            if (history.length) {
                e.preventDefault();
                history.back();
                // Fallback in case history.back() doesn't work
                setTimeout(function(){
                	window.location.href = document.referrer;
                }, 100);
            }
        });
    }

    // Sidebar toggle
    if (window.outerWidth < 992) {
        $("#app-header .back").remove();
        let $siderbarToggleButton = $("<a href='#' class='sidebar-toggle'>Menu</a>");
    	$("#app-header").prepend($siderbarToggleButton);

        $(".sidebar-toggle").click(function(e){
            $("#sidebar").toggleClass("open");
        });
    }
});
