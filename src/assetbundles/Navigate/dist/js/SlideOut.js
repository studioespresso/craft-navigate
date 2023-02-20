(function ($) {
    // Create a new object on the window we can call from our template
    window.MyModuleSlideoutTrigger = Garnish.Base.extend({
        // Called when a new widget is created
        init: function (elementId) {
            // Find the trigger element
            console.log(elementId);
            this.$triggerElement = $('#' + elementId);
            // Attach an onclick handler
            this.$triggerElement.on('click', $.proxy(this, 'onClick'));
        },
        onClick: function () {
            const slideout = new Craft.CpScreenSlideout('navigate/nodes/add-slide-out');
            // Open the slideout
            slideout.open();
            // Listen fro the submit event
            slideout.on('submit', function (e) {
                alert(JSON.stringify(e.response.data));
            })
        },
    });
})(jQuery);