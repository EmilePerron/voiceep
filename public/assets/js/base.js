let body = document.querySelector('body');

window.addEventListener('scroll', function(){
    if (window.pageYOffset > 0) {
        body.classList.add('scrolling');
    } else {
        body.classList.remove('scrolling');
    }
});

// Mobile navigation
$("header .menu-toggle").click(function(e){
    e.preventDefault();
    $(this).toggleClass('open');
});

// Signup modal submission
$("body").on("submit", ".modal.signup form", function(e) {
    e.preventDefault();

	let $form = $(this);
    let $formErrorsWrapper = $form.find('.errors').empty();

    $.post($form.attr('action'), $form.serialize(), function(data) {
		if (typeof data.redirect_url != 'undefined') {
            window.location = data.redirect_url;
        } else {
            for (let error of data.errors) {
                $formErrorsWrapper.append('<div class="msg error">' + error + '</div>');
            }
        }
    }, 'JSON');
});

// Confirm link clicks from data attribute
$("body").on("click", "[confirm-link]", function(e) {
    e.preventDefault();

    let link = $(this).attr('href');
    UI.yesNoConfirm($(this).attr('confirm-link'),
                    function(){
                        window.location.href = link;
                    });
});
