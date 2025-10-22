

$('.row-label').click(function () {
    var $parent = $(this).parent();
    $parent.find('.rows-wrapper:first').toggle(400);
    $parent.find('.sign').toggleClass('minus');
    $parent.toggleClass('bg-grey');
});

$('input.radio_type').click(function(){

    var $choicesPlanes =  $(this).closest('.config-options').find('.rows-wrapper');
    /** radio style toggle switch */
    $(this)
        .closest('table')
        .find('input.radio_type')
        .each(
            (function(i, e){
                var $plane = $($choicesPlanes[i]);
                if(e === this && e.checked) {
                    $plane.show(400)
                        .find('input[type=radio]')[0]
                        .checked = e.checked ? true : false;
                } else {
                    e.checked = false;
                    $plane.hide(400);
                }
            }).bind(this));
});