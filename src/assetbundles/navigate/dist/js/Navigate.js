(function ($) {

    Craft.Navigate = Garnish.Base.extend(
        {
            id: null,
            site: null,
            entryModal: null,
            categoryModal: null,
            assetModal: null,
            currentElementType: null,
            structure: null,
            levels: null,

            siteHandle: null,
            savingNode: false,
            entrySources: '',

            $buildContainer: $('.navigate__builder'),
            $parentContainer: $('.node__parent'),
            $newWindowElement: $('.navigate .field input[name="blank"]'),
            $addElementButton: $('#navigate-nodeTypes').children(),

            $addElementLoader: $('.navigate .buttons .spinner'),

            init: function (id, entrySources, nav, site, levels) {
                this.id = id;
                this.site = site;
                this.entrySources = '*';
                this.levels = levels;
                this.structure = new Craft.NavigateStructure(this.id, '#navigate__nav', '.navigate__nav', settings, this.levels);

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
                } else if (this.currentElementType == 'url') {
                    this.urlModal = this.createModal('url');
                }
            },

            /**
             * Create ElementSelectorModal.
             */
            createModal: function (elementType, elementSources) {
                if (elementType === 'url') {
                    $modal = new Craft.NavigateUrlModal(this.structure, this.id, this.site);
                    return $modal;
                } else {
                    return Craft.createElementSelectorModal(elementType, {
                        criteria: {
                            enabledForSite: null,
                            siteId: this.site
                        },
                        sources: elementSources,
                        multiSelect: true,
                        onSelect: $.proxy(this, 'onModalSelect')
                    });
                }
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

                    var data = {
                        navId: this.id,
                        siteId: parseInt(this.site),
                        status: element.status,
                        name: element.label,
                        enabled: element.status == 'live',
                        url: element.url,
                        type: "element",
                        elementType: elementType,
                        blank: this.$newWindowElement.val() == '1',
                        elementId: element.id,
                        parent: parentId === undefined ? null : parentId
                    };
                    this.saveNewNode(data, 'element');
                }
            },

            /**
             * Save a new node to the database.
             *
             * @param array  data
             * @param string nodeType
             */
            saveNewNode: function(data, nodeType) {
                // Make sure we can only save one node at a time
                this.savingNode = true;
                if (nodeType == 'manual') {
                    this.$manualLoader.removeClass('hidden');
                }
                else {
                    this.$addElementLoader.removeClass('hidden');
                }

                var url = Craft.getActionUrl('navigate/nodes/add');
                Craft.postActionRequest(url, data, $.proxy(function(response, textStatus) {
                    if (textStatus == 'success') {
                        this.savingNode = false;
                        if (nodeType == 'manual') {
                            this.$manualLoader.addClass('hidden');
                        }
                        else {
                            this.$addElementLoader.addClass('hidden');
                        }

                        if (response.success) {
                            if (nodeType == 'manual') {
                                // Reset fields
                                this.$manualForm.find('#name').val('');
                                this.$manualForm.find('#url').val('');
                            }

                            // Add node to structure!
                            this.structure.addNode(response.nodeData, 'element');

                            // Display parent options
                            this.$parentContainer.html(response.parentOptions);

                            Craft.cp.displayNotice(response.message);
                        }
                        else {
                            Craft.cp.displayError(response.message);
                        }
                    }
                }, this));
            },

        })

    Craft.NavigateUrlModal = Garnish.Modal.extend(
        {
            body: null,
            $subjectInput: null,
            $bodyInput: null,
            $spinner: null,
            $nameInput: null,
            site : null,
            nav: null,

            init: function (structure, navId, currentSite) {
                this.site = currentSite;
                this.nav = navId;

                this.structure = structure,
                    this.body = $('#node__url').html(),

                    this.base(null, {
                        resizable: false
                    });

                this.loadContainer(this.body);


            },

            loadContainer: function ($body) {
                var $container = $('<form class="modal fitted" accept-charset="UTF-8">' + $body + '</form>').appendTo(Garnish.$bod);
                this.setContainer($container);
                this.show();

                this.$nameInput = $container.find('.node-name:first');
                this.$urlInput = this.$container.find('.node-url:first');

                this.$cancelBtn = $container.find('.cancel:first');
                this.addListener(this.$cancelBtn, 'click', 'cancel');

                this.$submitBtn = $container.find('.submit:first');
                this.addListener(this.$container, 'submit', 'addNode');

                this.$spinner = this.$container.find('.spinner:first');


            },

            show: function() {
                // Close other modals as needed
                if (this.settings.closeOtherModals && Garnish.Modal.visibleModal && Garnish.Modal.visibleModal !== this) {
                    Garnish.Modal.visibleModal.hide();
                }

                if (this.$container) {
                    // Move it to the end of <body> so it gets the highest sub-z-index
                    this.$shade.appendTo(Garnish.$bod);
                    this.$container.appendTo(Garnish.$bod);

                    this.$container.show();
                    this.updateSizeAndPosition();

                    this.$shade.velocity('fadeIn', {
                        duration: 50,
                        complete: $.proxy(function() {
                            this.$container.velocity('fadeIn', {
                                complete: $.proxy(function() {
                                    this.updateSizeAndPosition();
                                    this.$nameInput.trigger('focus');
                                    this.onFadeIn();
                                }, this)
                            });
                        }, this)
                    });

                    if (this.settings.hideOnShadeClick) {
                        this.addListener(this.$shade, 'click', 'hide');
                    }

                    this.addListener(Garnish.$win, 'resize', 'updateSizeAndPosition');
                }

                this.enable();

                if (this.settings.hideOnEsc) {
                    Garnish.escManager.register(this, 'hide');
                }

                if (!this.visible) {
                    this.visible = true;
                    Garnish.Modal.visibleModal = this;

                    this.trigger('show');
                    this.settings.onShow();
                }
            },

            addNode: function (event) {
                event.preventDefault();

                if (this.loading) {
                    return;
                }

                var data = {
                    navId: this.nav,
                    siteId: this.site,
                    type: 'url',
                    enabled: 'live',
                    parentId: 0,
                    name: this.$nameInput.val(),
                    url: this.$urlInput.val()
                };


                this.$nameInput.removeClass('error');
                this.$urlInput.removeClass('error');


                if (!data.name || !data.url) {
                    if (!data.name) {
                        this.$nameInput.addClass('error');
                    }

                    if (!data.url) {
                        this.$urlInput.addClass('error');
                    }

                    Garnish.shake(this.$container);
                    return;
                }

                // var re = /^(http[s]?:\/\/){0,1}(www\.){0,1}[a-zA-Z0-9\.\-]+\.[a-zA-Z]{2,5}[\.]{0,1}/;
                // if(!re.test(data.url)) {
                //     this.$urlInput.addClass('error');
                //     Garnish.shake(this.$container);
                //     return;
                // }


                var url = Craft.getActionUrl('navigate/nodes/add');
                Craft.postActionRequest(url, data, $.proxy(function(response, textStatus) {
                    if (textStatus == 'success') {
                        this.savingNode = false;

                        if (response.success) {
                            // Add node to structure!
                            this.loading =  true;

                            this.$spinner.show();
                            this.structure.addNode(response.nodeData, 'url');
                            this.$spinner.hide();
                            this.hide();

                            // Display parent options
                            Craft.cp.displayNotice(response.message);
                        }
                        else {
                            Craft.cp.displayError(response.message);
                        }
                    }
                }, this));
                
            },


            cancel: function () {
                this.hide();

                if (this.message) {
                    this.message.modal = null;
                }
            }


        }
    )

    Craft.NavigateStructure = Craft.Structure.extend(
        {
            navId: null,

            $emptyContainer: $('.navigate__empty'),
            $template: $('#navigate__node').html(),

            /**
             *
             * @param int    navId
             * @param string id
             * @param string container
             * @param array  settings
             */
            init: function (navId, id, container, settings, levels) {
                this.levels = levels;
                this.navId = navId;
                this.base(id, container, settings);

                this.dragdrop = new Craft.NavigateDragDrop(this, this.levels);

                this.$container.find('.settings').on('click', $.proxy(function(ev) {
                    this.getNodeEditor($(ev.currentTarget));
                }, this));

                this.$container.find('.delete').on('click', $.proxy(function (ev) {
                    this.removeElement($(ev.currentTarget));
                }, this));

            },

            getNodeEditor: function($element) {
                new Craft.NavigateEditor($element);
            },


            /**
             * Save a new node to the database.
             *
             * @param array  data
             * @param string nodeType
             */
            addNode: function (data, nodeType) {
                var count = $('#navigate__nav').children().length;
                var nodeHtml = this.$template
                    .replace(/%%siteId%%/ig, data.siteId ? data.siteId : "")
                    .replace(/%%elementId%%/ig, data.elementId ? data.elementId : "")
                    .replace(/%%count%%/ig, count + 1)
                    .replace(/%%status%%/ig, data.enabled ? "live" : "expired")
                    .replace(/%%label%%/ig, data.name)
                    .replace(/%%id%%/ig, data.id)
                    .replace(/%%type%%/ig, nodeType)
                    .replace(/%%url%%/ig, nodeType == 'url' ? data.url : '')
                    .replace(/%%elementType%%/ig, data.elementType ? data.elementType : '' )
                    .replace(/%%type%%/ig, data.elementType ? data.elementType.toLowerCase() : "url")
                    .replace(/%%typeLabel%%/ig, data.elementType ? data.elementType : "url")

                $node = $(nodeHtml);

                // Add it to the structure
                this.addElement($node, data.parentId, data);

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
            addElement: function ($element, parentId, data) {
                var $appendTo = this.$container,
                    level = 1;

                var $li = $('<li data-level="' + level + '"/>').appendTo($appendTo),
                    // indent = 8 + (level - 1) * 35 %}
                    indent = this.getIndent(level),
                    $row = $('<div class="row" style="margin-left: -'+ indent + 'px; padding-left: ' + indent + 'px;" data-id="' + data.id + '">').appendTo($li);

                $row.append($element);

                this.dragdrop.addItems($li);

                this.$container.find('.settings').on('click', $.proxy(function(ev) {
                    this.getNodeEditor($(ev.currentTarget));
                }, this));

                this.$container.find('.delete').on('click', $.proxy(function (ev) {
                    this.removeElement($(ev.currentTarget));
                }, this));

                if (this.$container.length) {
                    this.$emptyContainer.addClass('hidden');
                }
            },

            /**
             * Remove an element from the structure.
             *
             * @param object $element
             */
            removeElement: function ($element) {
                var $li = $element.closest('li');
                confirmation = confirm(Craft.t('navigate', 'Are you sure you want to delete “{name}” and its descendants?', {name: $li.find('.node__node').data('label')}));

                if (confirmation) {
                    this.deleteNode($element.parent());
                    this.dragdrop.removeItems($li);

                    if (!$li.siblings().length) {
                        var $parentUl = $li.parent();
                    }

                    $li.css('visibility', 'hidden').velocity({marginBottom: -$li.height()}, 'fast', $.proxy(function () {
                        $li.remove();
                    }, this));
                }
            },

            deleteNode: function($element) {
                var nodeId = $element.data('id'),
                    url = Craft.getActionUrl('navigate/nodes/delete'),
                    data = { nodeId: nodeId };

                Craft.postActionRequest(url, data, $.proxy(function(response, textStatus) {
                    if (textStatus == 'success' && response.success) {
                        Craft.cp.displayNotice(response.message);
                    }
                }, this));
            }
        });

    Craft.NavigateEditor = Garnish.Base.extend(
        {
            $node: null,
            nodeId: null,

            $form: null,
            $fieldsContainer: null,
            $cancelBtn: null,
            $saveBtn: null,
            $spinner: null,

            hud: null,

            init: function($node) {
                this.$node = $node;
                this.nodeId = $node.data('id');

                this.$node.addClass('loading');

                var data = {
                    nodeId: this.nodeId
                };

                Craft.postActionRequest('navigate/nodes/editor', data, $.proxy(this, 'showEditor'));
            },

            showEditor: function(response, textStatus) {

                if (textStatus == 'success') {
                    var $hudContents = $();

                    this.$form = $('<form/>');
                    $('<input type="hidden" name="nodeId" value="'+this.nodeId+'">').appendTo(this.$form);
                    this.$fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

                    this.$fieldsContainer.html(response.html)
                    Craft.initUiElements(this.$fieldsContainer);

                    var $buttonsOuterContainer = $('<div class="footer"/>').appendTo(this.$form);

                    this.$spinner = $('<div class="spinner left hidden"/>').appendTo($buttonsOuterContainer);

                    var $buttonsContainer = $('<div class="buttons right"/>').appendTo($buttonsOuterContainer);
                    this.$cancelBtn = $('<div class="btn">'+Craft.t('navigate','Cancel')+'</div>').appendTo($buttonsContainer);
                    this.$saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('navigate','Save')+'"/>').appendTo($buttonsContainer);

                    $hudContents = $hudContents.add(this.$form);

                    this.hud = new Garnish.HUD(this.$node, $hudContents, {
                        bodyClass: 'body elementeditor elementeditor--navigate',
                        closeOtherHUDs: false
                    });

                    this.hud.on('hide', $.proxy(function() {
                        delete this.hud;
                    }, this));

                    this.addListener(this.$saveBtn, 'click', 'save');
                    this.addListener(this.$cancelBtn, 'click', function() {
                        this.hud.hide()
                    });
                }
            },

            save: function(e) {
                e.preventDefault();

                this.$spinner.removeClass('hidden');

                var data = this.$form.serialize(),
                    $status    = this.$node.parent().find('.status');
                    $blank    = this.$node.find('.blank');

                    updateUrl = Craft.getActionUrl('navigate/nodes/update');
                    Craft.postActionRequest(updateUrl, data, $.proxy(function(response, textStatus) {
                    this.$spinner.addClass('hidden');

                    if (textStatus == 'success') {
                        if (textStatus == 'success' && response.success) {
                            Craft.cp.displayNotice(response.message);

                            // Update name
                            this.$node.parent().data('label', response.nodeData.name);
                            this.$node.parent().find('.title').text(response.nodeData.name);
                            // Update status
                            if (response.nodeData.enabled) {
                                $status.addClass('live');
                                $status.removeClass('expired');
                            } else {
                                $status.addClass('expired');
                                $status.removeClass('live');
                            }
                            // Update new window icon
                            if (response.nodeData.blank) {
                                $blank.removeClass('visuallyhidden');
                            } else {
                                $blank.addClass('visuallyhidden');
                            }

                            this.closeHud();
                        }
                        else
                        {
                            Garnish.shake(this.hud.$hud);
                        }
                    }
                }, this));

            },

            closeHud: function() {
                this.hud.hide();
                delete this.hud;
            }
        });


    Craft.NavigateDragDrop = Craft.StructureDrag.extend(
        {
            onDragStop: function() {
                // Are we repositioning the draggee?
                if (this._.$closestTarget && (this.$insertion.parent().length || this._.$closestTarget.hasClass('draghover'))) {
                    var $draggeeParent,
                        moved;

                    // Are we about to leave the draggee's original parent childless?
                    if (!this.$draggee.siblings().length) {
                        $draggeeParent = this.$draggee.parent();
                    }

                    if (this.$insertion.parent().length) {
                        // Make sure the insertion isn't right next to the draggee
                        var $closestSiblings = this.$insertion.next().add(this.$insertion.prev());

                        if ($.inArray(this.$draggee[0], $closestSiblings) === -1) {
                            this.$insertion.replaceWith(this.$draggee);
                            moved = true;
                        }
                        else {
                            this.$insertion.remove();
                            moved = false;
                        }
                    }
                    else {
                        var $ul = this._.$closestTargetLi.children('ul');

                        // Make sure this is a different parent than the draggee's
                        if (!$draggeeParent || !$ul.length || $ul[0] !== $draggeeParent[0]) {
                            if (!$ul.length) {


                                $ul = $('<ul>').appendTo(this._.$closestTargetLi);
                            }
                            else if (this._.$closestTargetLi.hasClass('collapsed')) {
                            }

                            this.$draggee.appendTo($ul);
                            moved = true;
                        }
                        else {
                            moved = false;
                        }
                    }

                    // Remove the class either way
                    this._.$closestTarget.removeClass('draghover');
                    if (moved)
                    {
                        // Now deal with the now-childless parent
                        if ($draggeeParent)
                        {
                            this.structure._removeUl($draggeeParent);
                        }

                        // Has the level changed?
                        var newLevel = this.$draggee.parentsUntil(this.structure.$container, 'li').length + 1;

                        if (newLevel != this.$draggee.data('level'))
                        {
                            // Correct the helper's padding if moving to/from level 1
                            if (this.$draggee.data('level') == 1)
                            {
                                var animateCss = {};
                                animateCss['padding-'+Craft.left] = 38;
                                this.$helperLi.velocity(animateCss, 'fast');
                            }
                            else if (newLevel == 1)
                            {
                                var animateCss = {};
                                animateCss['padding-'+Craft.left] = Craft.Structure.baseIndent;
                                this.$helperLi.velocity(animateCss, 'fast');
                            }

                            this.setLevel(this.$draggee, newLevel);
                        }

                        // Make it real
                        var $element = this.$draggee.find('.node__node');

                        var data = {
                            navId:    this.structure.navId,
                            nodeId:   $element.data('id'),
                            prevId:   $element.closest('li').prev().find('.node__node').data('id'),
                            parentId: this.$draggee.parent('ul').parent('li').find('.node__node').data('id')
                        };

                        var url = Craft.getActionUrl('navigate/nodes/move');
                        Craft.postActionRequest(url, data, function(response, textStatus)
                        {
                            if (textStatus == 'success')
                            {
                                Craft.cp.displayNotice(response.message);
                            }
                        });
                    }
                }

                // Animate things back into place
                this.$draggee.velocity('stop').removeClass('hidden').velocity({
                    height: this.draggeeHeight
                }, 'fast', $.proxy(function() {
                    this.$draggee.css('height', 'auto');
                }, this));

                this.returnHelpersToDraggees();

                this.base();
            },

            setLevel: function($li, level) {
                $li.data('level', level);

                var indent = this.structure.getIndent(level);

                var css = {};
                css['margin-' + Craft.left] = '-' + indent + 'px';
                css['padding-' + Craft.left] = indent + 'px';
                this.$draggee.children('.row').css(css);

                var $childLis = $li.children('ul').children();

                for (var i = 0; i < $childLis.length; i++) {
                    this.setLevel($($childLis[i]), level + 1);
                }
            }

        });

})(jQuery);
