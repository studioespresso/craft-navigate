(function ($) {

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
            siteUrl: 'craft3.local',
            savingNode: false,
            entrySources: '',

            $template: $('#navigate__node').html(),
            $buildContainer: $('.navigate__builder'),
            $parentContainer: $('.node__parent'),
            $newWindowElement: $('.navigate .field input[name="blank"]'),
            $addElementButton: $('#navigate-nodeTypes').children(),

            $addElementLoader: $('.navigate .buttons .spinner'),

            init: function (id, entrySources) {
                this.id = id;
                console.log(id);
                this.entrySources = '*';
                this.siteHandle = 'default';
                this.structure = new Craft.NavigateStructure(this.id, '#navigate__nav', '.navigate__nav', settings);


                this.addListener(this.$addElementButton, 'activate', 'showModal');
                this.addListener(this.$manualForm, 'submit', 'onManualSubmit');
                this.addListener(this.$displayIdsButton, 'click', 'showNodeIds');
            },

            /**
             * Display ElementSelectorModal.
             */
            showModal: function (ev,) {
                this.currentElementType = $(ev.currentTarget).data('type');
                if (this.currentElementType == 'entry') {
                    if (!this.entryModal) {
                        this.entryModal = this.createModal("craft\\elements\\Entry", '*');
                    }
                    else {
                        this.entryModal.show();
                    }
                } else if (this.currentElementType == 'asset') {
                    if (!this.assetModal) {
                        this.assetModal = this.createModal('craft\\elements\\Asset', '*');
                    }
                    else {
                        this.assetModal.show();
                    }
                } else if (this.currentElementType == 'category') {
                    if (!this.categoryModal) {
                        this.categoryModal = this.createModal('craft\\elements\\Category', '*');
                    }
                    else {
                        this.categoryModal.show();
                    }
                }
            },

            /**
             * Create ElementSelectorModal.
             */
            createModal: function (elementType, elementSources) {
                return Craft.createElementSelectorModal(elementType, {
                    criteria: {
                        site: this.siteHandle
                    },
                    sources: elementSources,
                    multiSelect: false,
                    onSelect: $.proxy(this, 'onModalSelect')
                });
            },

            /**
             * Handle selected elements from the ElementSelectorModal.
             */
            onModalSelect: function (elements) {
                var parentId = this.$parentContainer.find('#parent').val(),
                    elementType = this.currentElementType;

                for (var i = 0; i < elements.length; i++) {
                    var element = elements[i];

                    // Unselect element in modal
                    if (elementType == 'Entry') {
                        this.entryModal.$body.find('.element[data-id="' + element.id + '"]').closest('tr').removeClass('sel');
                    }
                    else if (elementType == 'Category') {
                        this.categoryModal.$body.find('.element[data-id="' + element.id + '"]').closest('tr').removeClass('sel');
                    }
                    else if (elementType == 'Asset') {
                        this.assetModal.$body.find('.element[data-id="' + element.id + '"]').closest('tr').removeClass('sel');
                    }
                    console.log(elementType);
                    var data = {
                        navId: this.id,
                        name: element.label,
                        enabled: element.status == 'live',
                        elementId: element.id,
                        url: element.url,
                        elementType: elementType,
                        blank: this.$newWindowElement.val() == '1',
                        locale: this.locale,
                        parentId: parentId === undefined ? 0 : parentId
                    };
                    this.addNode(data, elementType);
                }
            },

            /**
             * Handle manual node form submission.
             *
             * @param object ev
             */
            onManualSubmit: function (ev) {
                if (!this.savingNode) {
                    var parentId = this.$parentContainer.find('#parent').val(),
                        data = {
                            navId: this.id,
                            name: this.$manualForm.find('#name').val(),
                            url: this.$manualForm.find('#url').val(),
                            blank: this.$newWindowElement.val() == '1',
                            locale: this.locale,
                            parentId: parentId === undefined ? 0 : parentId
                        };
                    this.addNode(data, 'manual');
                }
                ev.preventDefault();
            },

            /**
             * Save a new node to the database.
             *
             * @param array  data
             * @param string nodeType
             */
            addNode: function (data, nodeType) {
                console.log(data.elementType);
                var nodeHtml = this.$template
                        .replace(/%%id%%/ig, data.id)
                        .replace(/%%status%%/ig, (data.enabled ? "live" : "expired"))
                        .replace(/%%label%%/ig, data.name)
                        .replace(/%%type%%/ig, data.elementType ? data.elementType.toLowerCase() : "manual")
                        .replace(/%%typeLabel%%/ig, data.elementType ? data.elementType : Craft.t("Manual"))
                        .replace(/%%url%%/ig, data.url.replace('{siteUrl}', this.siteUrl))
                        .replace(/%%urlless%%/ig, data.url.replace('{siteUrl}', ''))

                    $node = $(nodeHtml);

                // Add it to the structure
                this.structure.addElement($node, data.parentId);

            },

        })

    Craft.NavigateStructure = Craft.Structure.extend(
        {
            navId: null,

            $emptyContainer: $('.navigate__empty'),

            /**
             * Initiate AmNavStructure.
             *
             * @param int    navId
             * @param string id
             * @param string container
             * @param array  settings
             */
            init: function(navId, id, container, settings) {
                this.navId = navId;
                this.base(id, container, settings);

            },
            /**
             * Add an element to the structure.
             *
             * @param object $element
             */
            /**
             * Add an element to the structure.
             *
             * @param object $element
             */
            addElement: function($element, parentId) {
                var $appendTo = this.$container,
                    level = 1;

                // Add node to the structure
                var $li = $('<li data-level="'+level+'"/>').appendTo($appendTo),
                    indent = this.getIndent(level),
                    $row = $('<div class="row" style="margin-'+Craft.left+': -'+indent+'px; padding-'+Craft.left+': '+indent+'px;">').appendTo($li);

                $row.append($element);

                if (this.$container.length) {
                    this.$emptyContainer.addClass('hidden');
                }
            },
        });

})(jQuery);
