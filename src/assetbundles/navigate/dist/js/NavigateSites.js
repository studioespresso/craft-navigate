(function($) {
    /** global: Craft */
    /** global: Garnish */
    Craft.NavigateSites = Garnish.Base.extend(
        {
            currentSite: null,
            allSites: null,
            hud: null,
            loading: false,

            init: function(currentSite, allSites) {
                this.site = currentSite;
                this.allSites = allSites;

                this.addListener(this.$editBtn, 'click', 'showHud');
            },

            showHud: function() {
                if (!this.hud) {
                    var $hudBody = $('<div/>');


                    this.onHudShow();
                }
                else {
                    this.hud.show();
                }

                if (!Garnish.isMobileBrowser(true)) {
                    this.$nameInput.trigger('focus');
                }
            },

            onHudShow: function() {
                this.$editBtn.addClass('active');
            },


            shakeHud: function() {
                Garnish.shake(this.hud.$hud);
            }

        });
})(jQuery);
