(function($) {

    Craft.Navigate = Garnish.Base.extend(
        {
            id: null,
            entryModal: null,
            categoryModal: null,
            assetModal: null,
            currentElementType: null,
            structure: null,

            locale: null,
            siteHandle: null,
            siteUrl: '',
            savingNode: false,
            entrySources: '',

            $template: $('#navigate__node').html(),
            $buildContainer: $('.navigate__builder'),
            $parentContainer: $('.node__parent'),
            $newWindowElement: $('.navigate .field input[name="blank"]'),
            $addElementButton: $('#navigate-nodeTypes').children(),

            $addElementLoader: $('.navigate .buttons .spinner'),

            init: function (id, entrySources) {
                this.entrySources = '*';
                this.siteHandle = 'default';

                this.addListener(this.$addElementButton, 'activate', 'showModal');
                this.addListener(this.$manualForm, 'submit', 'onManualSubmit');
                this.addListener(this.$displayIdsButton, 'click', 'showNodeIds');
            },

            /**
             * Display ElementSelectorModal.
             */
            showModal: function(ev, ) {
                this.currentElementType = $(ev.currentTarget).data('type');
                if (this.currentElementType == 'entry') {
                    if (! this.entryModal) {
                        this.entryModal = this.createModal("craft\\elements\\Entry", '*');
                    }
                    else {
                        this.entryModal.show();
                    }
                }
            },

            /**
             * Create ElementSelectorModal.
             */
            createModal: function(elementType, elementSources) {
                return Craft.createElementSelectorModal(elementType, {
                    criteria: {
                        site: this.siteHandle
                    },
                    sources: elementSources,
                    multiSelect: false,
                    onSelect: $.proxy(this, 'onModalSelect')
                });
            },

        })

})(jQuery);
