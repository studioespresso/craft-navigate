(function ($) {
    Craft.NavigateInput = Garnish.Base.extend({

        id: null,
        nodeTypes: null,
        nodeTypesByHandle: null,

        init: function (id, nodeTypes) {

            this.id = id;
            this.nodeTypes = nodeTypes;

            this.$container = $('#' + this.id);
            this.$blockContainer = this.$container.children('.blocks');
            this.$addBlockBtnContainer = this.$container.children('.buttons');
            this.$addBlockBtnGroup = this.$addBlockBtnContainer.children('.btngroup');
            this.$addBlockBtnGroupBtns = this.$addBlockBtnGroup.children('.btn');
            this.$addBlockMenuBtn = this.$addBlockBtnContainer.children('.menubtn');


            this.nodeTypesByHandle = {};

            var i;

            for (i = 0; i < this.nodeTypes.length; i++) {
                var nodeType = this.nodeTypes[i];
                this.nodeTypesByHandle[nodeType.handle] = nodeType;
            }

            var $blocks = this.$blockContainer.children(),
                collapsedBlocks = Craft.NavigateInput.getCollapsedBlockIds();


            this.blockSort = new Garnish.DragSort($blocks, {
                handle: '> .actions > .move',
                axis: 'y',
                filter: $.proxy(function() {
                    // Only return all the selected items if the target item is selected
                    if (this.blockSort.$targetItem.hasClass('sel')) {
                        return this.blockSelect.getSelectedItems();
                    }
                    else {
                        return this.blockSort.$targetItem;
                    }
                }, this),
                collapseDraggees: true,
                magnetStrength: 4,
                helperLagBase: 1.5,
                helperOpacity: 0.9,
                onSortChange: $.proxy(function() {
                    this.blockSelect.resetItemOrder();
                }, this)
            });

            this.blockSelect = new Garnish.Select(this.$blockContainer, $blocks, {
                multi: true,
                vertical: true,
                handle: '> .checkbox, > .titlebar',
                checkboxMode: true
            });

            for (i = 0; i < $blocks.length; i++) {
                var $block = $($blocks[i]),
                    blockId = $block.data('id');

                // Is this a new block?
                var newMatch = (typeof blockId === 'string' && blockId.match(/new(\d+)/));

                if (newMatch && newMatch[1] > this.totalNewBlocks) {
                    this.totalNewBlocks = parseInt(newMatch[1]);
                }

                var block = new MatrixBlock(this, $block);

                if (block.id && $.inArray('' + block.id, collapsedBlocks) !== -1) {
                    block.collapse();
                }
            }

            this.addListener(this.$addBlockBtnGroupBtns, 'click', function(ev) {
                var type = $(ev.target).data('type');
                this.addBlock(type);
            });

            new Garnish.MenuBtn(this.$addBlockMenuBtn,
                {
                    onOptionSelect: $.proxy(function(option) {
                        var type = $(option).data('type');
                        this.addBlock(type);
                    }, this)
                });

            this.updateAddBlockBtn();

            this.addListener(this.$container, 'resize', 'setNewBlockBtn');
            Garnish.$doc.ready($.proxy(this, 'setNewBlockBtn'));


        },




        updateAddBlockBtn: function () {
            var i, block;

            console.log(this.blockSelect);
            for (i = 0; i < this.blockSelect.$items.length; i++) {
                block = this.blockSelect.$items.eq(i).data('block');

                if (block) {
                    block.$actionMenu.find('a[data-action=add]').parent().removeClass('disabled');
                }
            }

        },

        addBlock: function(type, $insertBefore) {
             this.totalNewBlocks++;

                var id = 'new' + this.totalNewBlocks;

                var html =
                    '<div class="matrixblock" data-id="' + id + '" data-type="' + type + '">' +
                    '<input type="hidden" name="' + this.inputNamePrefix + '[' + id + '][type]" value="' + type + '"/>' +
                    '<input type="hidden" name="' + this.inputNamePrefix + '[' + id + '][enabled]" value="1"/>' +
                    '<div class="titlebar">' +
                    '<div class="blocktype">' + this.getBlockTypeByHandle(type).name + '</div>' +
                    '<div class="preview"></div>' +
                    '</div>' +
                    '<div class="checkbox" title="' + Craft.t('app', 'Select') + '"></div>' +
                    '<div class="actions">' +
                    '<div class="status off" title="' + Craft.t('app', 'Disabled') + '"></div>' +
                    '<a class="settings icon menubtn" title="' + Craft.t('app', 'Actions') + '" role="button"></a> ' +
                    '<div class="menu">' +
                    '<ul class="padded">' +
                    '<li><a data-icon="collapse" data-action="collapse">' + Craft.t('app', 'Collapse') + '</a></li>' +
                    '<li class="hidden"><a data-icon="expand" data-action="expand">' + Craft.t('app', 'Expand') + '</a></li>' +
                    '<li><a data-icon="disabled" data-action="disable">' + Craft.t('app', 'Disable') + '</a></li>' +
                    '<li class="hidden"><a data-icon="enabled" data-action="enable">' + Craft.t('app', 'Enable') + '</a></li>' +
                    '</ul>' +
                    '<hr class="padded"/>' +
                    '<ul class="padded">' +
                    '<li><a class="error" data-icon="remove" data-action="delete">' + Craft.t('app', 'Delete') + '</a></li>' +
                    '</ul>' +
                    '<hr class="padded"/>' +
                    '<ul class="padded">';

                for (var i = 0; i < this.blockTypes.length; i++) {
                    var blockType = this.blockTypes[i];
                    html += '<li><a data-icon="plus" data-action="add" data-type="' + blockType.handle + '">' + Craft.t('app', 'Add {type} above', {type: blockType.name}) + '</a></li>';
                }

                html +=
                    '</ul>' +
                    '</div>' +
                    '<a class="move icon" title="' + Craft.t('app', 'Reorder') + '" role="button"></a> ' +
                    '</div>' +
                    '</div>';

                var $block = $(html);

                if ($insertBefore) {
                    $block.insertBefore($insertBefore);
                }
                else {
                    $block.appendTo(this.$blockContainer);
                }

                var $fieldsContainer = $('<div class="fields"/>').appendTo($block),
                    bodyHtml = this.getParsedBlockHtml(this.blockTypesByHandle[type].bodyHtml, id),
                    footHtml = this.getParsedBlockHtml(this.blockTypesByHandle[type].footHtml, id);

                $(bodyHtml).appendTo($fieldsContainer);

                // Animate the block into position
                $block.css(this.getHiddenBlockCss($block)).velocity({
                    opacity: 1,
                    'margin-bottom': 10
                }, 'fast', $.proxy(function() {
                    $block.css('margin-bottom', '');
                    Garnish.$bod.append(footHtml);
                    Craft.initUiElements($fieldsContainer);
                    new MatrixBlock(this, $block);
                    this.blockSort.addItems($block);
                    this.blockSelect.addItems($block);
                    this.updateAddBlockBtn();

                    Garnish.requestAnimationFrame(function() {
                        // Scroll to the block
                        Garnish.scrollContainerToElement($block);
                    });
                }, this));
            },

            getBlockTypeByHandle: function(handle) {
                for (var i = 0; i < this.blockTypes.length; i++) {
                    if (this.blockTypes[i].handle === handle) {
                        return this.blockTypes[i];
                    }
                }
            },

            collapseSelectedBlocks: function() {
                this.callOnSelectedBlocks('collapse');
            },

            expandSelectedBlocks: function() {
                this.callOnSelectedBlocks('expand');
            },

            disableSelectedBlocks: function() {
                this.callOnSelectedBlocks('disable');
            },

            enableSelectedBlocks: function() {
                this.callOnSelectedBlocks('enable');
            },

            deleteSelectedBlocks: function() {
                this.callOnSelectedBlocks('selfDestruct');
            },

            callOnSelectedBlocks: function(fn) {
                for (var i = 0; i < this.blockSelect.$selectedItems.length; i++) {
                    this.blockSelect.$selectedItems.eq(i).data('block')[fn]();
                }
            },

            getHiddenBlockCss: function($block) {
                return {
                    opacity: 0,
                    marginBottom: -($block.outerHeight())
                };
            },

            getParsedBlockHtml: function(html, id) {
                if (typeof html === 'string') {
                    return html.replace(/__BLOCK__/g, id);
                }
                else {
                    return '';
                }
            }

        },
    {
        collapsedBlockStorageKey: 'Craft-' + Craft.systemUid + '.NavigateInput.collapsedBlocks',

            getCollapsedBlockIds: function() {
            if (typeof localStorage[Craft.NavigateInput.collapsedBlockStorageKey] === 'string') {
                return Craft.filterArray(localStorage[Craft.NavigateInput.collapsedBlockStorageKey].split(','));
            }
            else {
                return [];
            }
        },

        setCollapsedBlockIds: function(ids) {
            localStorage[Craft.NavigateInput.collapsedBlockStorageKey] = ids.join(',');
        },

        rememberCollapsedBlockId: function(id) {
            if (typeof Storage !== 'undefined') {
                var collapsedBlocks = Craft.NavigateInput.getCollapsedBlockIds();

                if ($.inArray('' + id, collapsedBlocks) === -1) {
                    collapsedBlocks.push(id);
                    Craft.NavigateInput.setCollapsedBlockIds(collapsedBlocks);
                }
            }
        },

        forgetCollapsedBlockId: function(id) {
            if (typeof Storage !== 'undefined') {
                var collapsedBlocks = Craft.NavigateInput.getCollapsedBlockIds(),
                    collapsedBlocksIndex = $.inArray('' + id, collapsedBlocks);

                if (collapsedBlocksIndex !== -1) {
                    collapsedBlocks.splice(collapsedBlocksIndex, 1);
                    Craft.NavigateInput.setCollapsedBlockIds(collapsedBlocks);
                }
            }
        }
    });
})(jQuery);
