

/**
 * Moves template contents to loginbox (after submit button)
 * Because there are no blocks in loginbox template to override
 *
 * @param lawNoticeTemplate
 */
function moveLawNotice(lawNoticeTemplate){
    var submitButton = document.querySelector("#loginBox button[type='submit']");

    if (lawNoticeTemplate && submitButton) {
        submitButton.insertAdjacentHTML('beforebegin', lawNoticeTemplate);
    }
}

