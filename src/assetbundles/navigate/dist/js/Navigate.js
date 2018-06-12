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

            locale: null,
            siteHandle: null,
            siteUrl: 'craft3.local',
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
                this.siteHandle = 'default';
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
                            site: this.siteHandle
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
                        parentId: parentId === undefined ? 0 : parentId
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
                    console.log('blaa');
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
                    .replace(/%%typeLabel%%/ig, data.elementType ? data.elementType : "")

                $node = $(nodeHtml);

                // Add it to the structure
                this.addElement($node, data.parentId);

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
            addElement: function ($element, parentId) {
                var $appendTo = this.$container,
                    level = 1;

                // Add node to the structure
                var $li = $('<li data-level="' + level + '"/>').appendTo($appendTo),
                    indent = this.getIndent(level),
                    $row = $('<div class="node__node row" style="margin-' + Craft.left + ': -' + indent + 'px; padding-' + Craft.left + ': ' + indent + 'px;">').appendTo($li);

                $row.append($element);

                this.dragdrop.addItems($li);

                this.$container.find('.settings').on('click', $.proxy(function(ev) {
                    console.log('blaa');
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
                console.log($element);
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
                this.$node.removeClass('loading');

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
                    $status    = this.$node.find('.status');
                    $blank    = this.$node.find('.blank');


                    updateUrl = Craft.getActionUrl('navigate/nodes/update');
                    Craft.postActionRequest(updateUrl, data, $.proxy(function(response, textStatus) {
                    this.$spinner.addClass('hidden');

                    if (textStatus == 'success') {
                        if (textStatus == 'success' && response.success) {
                            Craft.cp.displayNotice(response.message);

                            // Update name
                            this.$node.data('label', response.nodeData.name);
                            this.$node.find('.title').text(response.nodeData.name);
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


    Craft.NavigateDragDrop = Garnish.Drag.extend(
        {
            structure: null,
            maxLevels: null,
            draggeeLevel: null,

            $helperLi: null,
            $targets: null,
            draggeeHeight: null,

            init: function(structure, maxLevels) {
                this.structure = structure;
                this.maxLevels = maxLevels;

                this.$insertion = $('<li class="draginsertion"/>');

                var $items = this.structure.$container.find('li');

                this.base($items, {
                    handle: '.element:first, .move:first',
                    helper: $.proxy(this, 'getHelper')
                });
            },

            getHelper: function($helper) {
                this.$helperLi = $helper;
                var $ul = $('<ul class="structure draghelper"/>').append($helper);
                $helper.css('padding-' + Craft.left, this.$draggee.css('padding-' + Craft.left));
                $helper.find('.move').removeAttr('title');
                return $ul;
            },

            onDragStart: function() {
                this.$targets = $();

                // Recursively find each of the targets, in the order they appear to be in
                this.findTargets(this.structure.$container);

                // How deep does the rabbit hole go?
                this.draggeeLevel = 0;
                var $level = this.$draggee;
                do {
                    this.draggeeLevel++;
                    $level = $level.find('> ul > li');
                } while ($level.length);

                // Collapse the draggee
                this.draggeeHeight = this.$draggee.height();
                this.$draggee.velocity({
                    height: 0
                }, 'fast', $.proxy(function() {
                    this.$draggee.addClass('hidden');
                }, this));
                this.base();

                this.addListener(Garnish.$doc, 'keydown', function(ev) {
                    if (ev.keyCode === Garnish.ESC_KEY) {
                        this.cancelDrag();
                    }
                });
            },

            findTargets: function($ul) {
                var $lis = $ul.children().not(this.$draggee);

                for (var i = 0; i < $lis.length; i++) {
                    var $li = $($lis[i]);
                    this.$targets = this.$targets.add($li.children('.row'));

                    if (!$li.hasClass('collapsed')) {
                        this.findTargets($li.children('ul'));
                    }
                }
            },

            onDrag: function() {
                if (this._.$closestTarget) {
                    this._.$closestTarget.removeClass('draghover');
                    this.$insertion.remove();
                }

                // First let's find the closest target
                this._.$closestTarget = null;
                this._.closestTargetPos = null;
                this._.closestTargetYDiff = null;
                this._.closestTargetOffset = null;
                this._.closestTargetHeight = null;
                for (this._.i = 0; this._.i < this.$targets.length; this._.i++) {
                    this._.$target = $(this.$targets[this._.i]);
                    this._.targetOffset = this._.$target.offset();
                    this._.targetHeight = this._.$target.outerHeight();
                    this._.targetYMidpoint = this._.targetOffset.top + (this._.targetHeight / 2);
                    this._.targetYDiff = Math.abs(this.mouseY - this._.targetYMidpoint);
                    if (this._.i === 0 || (this.mouseY >= this._.targetOffset.top + 5 && this._.targetYDiff < this._.closestTargetYDiff)) {
                        this._.$closestTarget = this._.$target;
                        this._.closestTargetPos = this._.i;
                        this._.closestTargetYDiff = this._.targetYDiff;
                        this._.closestTargetOffset = this._.targetOffset;
                        this._.closestTargetHeight = this._.targetHeight;
                    }
                    else {
                        // Getting colder
                        break;
                    }
                }

                if (!this._.$closestTarget) {
                    return;
                }

                // Are we hovering above the first row?
                if (this._.closestTargetPos === 0 && this.mouseY < this._.closestTargetOffset.top + 5) {
                    this.$insertion.prependTo(this.structure.$container);
                }
                else {
                    this._.$closestTargetLi = this._.$closestTarget.parent();
                    this._.closestTargetLevel = this._.$closestTargetLi.data('level');

                    // Is there a next row?
                    if (this._.closestTargetPos < this.$targets.length - 1) {
                        this._.$nextTargetLi = $(this.$targets[this._.closestTargetPos + 1]).parent();
                        this._.nextTargetLevel = this._.$nextTargetLi.data('level');
                    }
                    else {
                        this._.$nextTargetLi = null;
                        this._.nextTargetLevel = null;
                    }

                    // Are we hovering between this row and the next one?
                    this._.hoveringBetweenRows = (this.mouseY >= this._.closestTargetOffset.top + this._.closestTargetHeight - 5);

                    /**
                     * Scenario 1: Both rows have the same level.
                     *
                     *     * Row 1
                     *     ----------------------
                     *     * Row 2
                     */
                    if (this._.$nextTargetLi && this._.nextTargetLevel == this._.closestTargetLevel) {
                        if (this._.hoveringBetweenRows) {
                            if (!this.maxLevels || this.maxLevels >= (this._.closestTargetLevel + this.draggeeLevel - 1)) {
                                // Position the insertion after the closest target
                                this.$insertion.insertAfter(this._.$closestTargetLi);
                            }

                        }
                        else {
                            if (!this.maxLevels || this.maxLevels >= (this._.closestTargetLevel + this.draggeeLevel)) {
                                this._.$closestTarget.addClass('draghover');
                            }
                        }
                    }

                    /**
                     * Scenario 2: Next row is a child of this one.
                     *
                     *     * Row 1
                     *     ----------------------
                     *         * Row 2
                     */

                    else if (this._.$nextTargetLi && this._.nextTargetLevel > this._.closestTargetLevel) {
                        if (!this.maxLevels || this.maxLevels >= (this._.nextTargetLevel + this.draggeeLevel - 1)) {
                            if (this._.hoveringBetweenRows) {
                                // Position the insertion as the first child of the closest target
                                this.$insertion.insertBefore(this._.$nextTargetLi);
                            }
                            else {
                                this._.$closestTarget.addClass('draghover');
                                this.$insertion.appendTo(this._.$closestTargetLi.children('ul'));
                            }
                        }
                    }

                    /**
                     * Scenario 3: Next row is a child of a parent node, or there is no next row.
                     *
                     *         * Row 1
                     *     ----------------------
                     *     * Row 2
                     */

                    else {
                        if (this._.hoveringBetweenRows) {
                            // Determine which <li> to position the insertion after
                            this._.draggeeX = this.mouseX - this.targetItemMouseDiffX;

                            if (Craft.orientation === 'rtl') {
                                this._.draggeeX += this.$helperLi.width();
                            }

                            this._.$parentLis = this._.$closestTarget.parentsUntil(this.structure.$container, 'li');
                            this._.$closestParentLi = null;
                            this._.closestParentLiXDiff = null;
                            this._.closestParentLevel = null;


                            for (this._.i = 0; this._.i < this._.$parentLis.length; this._.i++) {
                                this._.$parentLi = $(this._.$parentLis[this._.i]);
                                this._.parentLiX = this._.$parentLi.offset().left;

                                if (Craft.orientation === 'rtl') {
                                    this._.parentLiX += this._.$parentLi.width();
                                }

                                this._.parentLiXDiff = Math.abs(this._.parentLiX - this._.draggeeX);
                                this._.parentLevel = this._.$parentLi.data('level');

                                if ((!this.maxLevels || this.maxLevels >= (this._.parentLevel + this.draggeeLevel - 1)) && (
                                    !this._.$closestParentLi || (
                                        this._.parentLiXDiff < this._.closestParentLiXDiff &&
                                        (!this._.$nextTargetLi || this._.parentLevel >= this._.nextTargetLevel)
                                    )
                                )) {
                                    this._.$closestParentLi = this._.$parentLi;
                                    this._.closestParentLiXDiff = this._.parentLiXDiff;
                                    this._.closestParentLevel = this._.parentLevel;
                                }
                            }
                            if (this._.$closestParentLi) {
                                this.$insertion.insertAfter(this._.$closestParentLi);
                            }
                        }
                        else {
                            if (!this.maxLevels || this.maxLevels >= (this._.closestTargetLevel + this.draggeeLevel)) {
                                this._.$closestTarget.addClass('draghover');
                            }
                        }
                    }
                }
            },

            cancelDrag: function() {
                this.$insertion.remove();

                if (this._.$closestTarget) {
                    this._.$closestTarget.removeClass('draghover');
                }

                this.onMouseUp();
            },

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
                                var $toggle = $('<div class="toggle" title="' + Craft.t('app', 'Show/hide children') + '"/>').prependTo(this._.$closestTarget);
                                this.structure.initToggle($toggle);

                                $ul = $('<ul>').appendTo(this._.$closestTargetLi);
                            }
                            else if (this._.$closestTargetLi.hasClass('collapsed')) {
                                this._.$closestTarget.children('.toggle').trigger('click');
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
                        var $element = this.$draggee.children('.row').children('.element');
                        console.log($element);
                        var data = {
                            navId:    this.structure.navId,
                            nodeId:   $element.data('id'),
                            prevId:   $element.closest('li').prev().children('.row').children('.element').data('id'),
                            parentId: this.$draggee.parent('ul').parent('li').children('.row').children('.element').data('id')
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
