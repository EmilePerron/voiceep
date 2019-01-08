var UI = {

    htmlToElement: function(html) {
        var template = document.createElement('template');
        html = html.trim();
        template.innerHTML = html;
        return template.content.firstChild;
    },

    showModal: function(content, classes) {
        classes = typeof classes != "undefined" ? classes : "";
        let html = `
            <div class="modal-wrapper">
                <div class="modal ${classes}">
                    ${content}
                </div>
            </div>`;

        let element = UI.htmlToElement(html);
        document.querySelector("body").prepend(element);

        element.addEventListener("click", function(e){
            if (e.target.classList.contains("modal-wrapper")) {
                e.preventDefault();
                element.remove();
            }
        });

        setTimeout(function(){
            element.classList.add("open");
        }, 100);
    },

    showModalFromUrl: function(url, classes) {
        $.ajax({
            url: url,
            type: 'GET',
            success: function(html) {
                if (typeof html != 'undefined') {
                    UI.showModal(html, classes);
                }
            }
        })
    },

    triggerEvent: function(element, event, parameters) {
        let eventTrigger = null;

        if (typeof parameters == "undefined") {
            eventTrigger = document.createEvent('HTMLEvents');
            eventTrigger.initEvent(event, true, true);
        } else {
            eventTrigger = new CustomEvent(event, { detail: parameters });
        }

        element.dispatchEvent(eventTrigger);
    },

	confirm: function(text, callback, cancelCallback, yesNo) {
		yesNo = typeof yesNo != "undefined" ? yesNo : false;

		let html = `
			<div class="modal-wrapper">
				<div class="modal confirm">
					<div class="text">${text}</div>
					<div class="button-container">
						<button class="gray" role="cancel">${yesNo ? "No" : "Cancel"}</button>
						<button class="" role="okay">${yesNo ? "Yes" : "Okay"}</button>
					</div>
				</div>
			</div>`;

		let element = UI.htmlToElement(html);
		document.querySelector("body").prepend(element);

		element.querySelector("button[role='cancel']").addEventListener("click", function(e){
			e.preventDefault();
			element.remove();

			if (typeof cancelCallback != "undefined") {
				cancelCallback();
			}
		});

		element.querySelector("button[role='okay']").addEventListener("click", function(e){
			e.preventDefault();
			element.remove();

			if (typeof callback != "undefined") {
				callback();
			}
		});

		setTimeout(function(){
			element.classList.add("open");
		}, 100);
	},

	yesNoConfirm: function(text, callback, cancelCallback) {
		callback = typeof callback != "undefined" ? callback : function(){};
		cancelCallback = typeof cancelCallback != "undefined" ? cancelCallback : function(){};
		UI.confirm(text, callback, cancelCallback, true);
	},

};
